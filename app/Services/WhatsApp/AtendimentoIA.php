<?php

namespace App\Services\WhatsApp;

use App\Models\Procedure;
use App\Models\Professional;
use App\Models\WhatsAppAlert;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\Agenda\DisponibilidadeService;
use App\Services\AuditLogger;
use App\Services\Tenant\TenantManager;
use Illuminate\Support\Facades\Log;

/**
 * O atendimento do WhatsApp com IA.
 *
 * A IA INTERPRETA e PROPÕE; este serviço DECIDE. A proposta do modelo (intenção,
 * texto, dados soltos) é tratada como opinião de um terceiro, nunca como ordem:
 * nenhuma ação de agenda nasce dela. Tudo que escreve em nome da clínica passa
 * pela mesma máquina de estados determinística (`FluxoAtendimento`) e pelos
 * mesmos serviços de agenda que já existiam — o que a IA acrescenta é a leitura
 * de texto livre e uma resposta mais natural, não autoridade nova.
 *
 * O caminho feliz é curto, mas cada atalho tem que continuar fechando:
 *
 * - Sem chave, sem cota ou com o padrão de mensagens por conversa estourado, a
 *   IA nem é chamada e o fluxo determinístico assume. Um atendimento com IA não
 *   pode ser um atendimento que para quando a IA falta.
 * - Consentimento (LGPD), cadastro e seleções numéricas vão direto para o fluxo:
 *   são passos com contrato próprio e não ganham nada sendo "interpretados".
 * - Pedir humano pausa o bot de verdade (`aguardando_atendente`), para a
 *   recepção assumir conversa a conversa.
 *
 * O resultado carrega `handled_by` para o n8n e o painel saberem quem respondeu:
 * `ia`, `fluxo`, `humano` (bot silencioso por atendimento humano) ou `pausado`.
 */
class AtendimentoIA
{
    public function __construct(
        protected TenantManager $tenants,
        protected ClienteIA $cliente,
        protected CotaIA $cota,
        protected FluxoAtendimento $fluxo,
        protected DisponibilidadeService $disponibilidade,
        protected Triagem $triagem,
        protected AlertaRecepcao $alertas,
    ) {}

    /**
     * Processa um turno com IA, caindo no fluxo determinístico em tudo que não
     * seja interpretação de texto livre.
     *
     * @return array{
     *     ok: bool,
     *     auto_resposta: bool,
     *     etapa_de: string,
     *     etapa_para: string,
     *     resposta: string,
     *     handled_by: string,
     *     intencao: string|null
     * }
     */
    public function processar(string $remoteJid, string $texto, string $nomeClinica = ''): array
    {
        $texto = trim($texto);
        $conversa = $this->abrirConversa($remoteJid);
        $etapa = $conversa->etapa ?? 'consentimento';

        /**
         * A precedência do atendimento automático mora em um lugar só
         * (`Triagem`): opt-out, pausa/humano, expediente e regra da clínica vêm
         * antes da IA e do fluxo. Sem isso, cada caminho teria a sua cópia da
         * ordem e um deles responderia por cima do atendente.
         */
        $triagem = $this->triagem->decidir($conversa, $texto, $nomeClinica);

        if ($triagem !== null) {
            $conversa->refresh();

            $this->registrarNoHistorico($remoteJid, null, $triagem['auto_resposta'], null);

            return $triagem;
        }

        // Passos que não ganham nada com IA: consentimento (LGPD), cadastro,
        // comandos globais e seleção de item por número.
        if ($this->vaiParaFluxo($etapa, $texto)) {
            return $this->delegarAoFluxo($remoteJid, $texto, $nomeClinica, $conversa);
        }

        $clinica = $this->tenants->current();

        if ($clinica === null || ! $this->cliente->configurado() || ! $this->cota->disponivel($clinica)) {
            return $this->delegarAoFluxo($remoteJid, $texto, $nomeClinica, $conversa);
        }

        if ($conversa->ia_mensagens >= (int) config('services.atendimento_ia.max_por_conversa', 30)) {
            return $this->delegarAoFluxo($remoteJid, $texto, $nomeClinica, $conversa);
        }

        if (! $this->cota->reservar($clinica)) {
            return $this->delegarAoFluxo($remoteJid, $texto, $nomeClinica, $conversa);
        }

        $conversa->ia_mensagens = $conversa->ia_mensagens + 1;
        $conversa->ia_ultimo_erro = null;
        $conversa->save();

        $proposta = $this->cliente->extrairIntencao($texto, [
            'etapa' => $etapa,
            'nome' => is_string($conversa->dados['nome'] ?? null) ? $conversa->dados['nome'] : null,
            'clinica' => $nomeClinica,
        ]);

        // A reserva já contou a chamada; sem proposta, é o caminho determinístico
        // que atende, e o erro fica registrado sem derrubar a conversa.
        if ($proposta === null) {
            $conversa->ia_ultimo_erro = 'A IA não devolveu uma proposta válida.';
            $conversa->save();

            return $this->delegarAoFluxo($remoteJid, $texto, $nomeClinica, $conversa, erroIa: true);
        }

        $this->cota->registrarTokens($clinica, $proposta['tokens_in'], $proposta['tokens_out']);

        return $this->aplicarProposta($remoteJid, $texto, $nomeClinica, $conversa, $proposta);
    }

    /**
     * Decide o que fazer com a proposta do modelo.
     *
     * @param  array{intencao: string, resposta: string, dados: array<string, mixed>, tokens_in: int, tokens_out: int}  $proposta
     * @return array{ok: bool, auto_resposta: bool, etapa_de: string, etapa_para: string, resposta: string, handled_by: string, intencao: string|null}
     */
    protected function aplicarProposta(string $remoteJid, string $texto, string $nomeClinica, WhatsAppConversation $conversa, array $proposta): array
    {
        $etapa = $conversa->etapa ?? 'consentimento';
        $intencao = $proposta['intencao'];
        $textoIA = $proposta['resposta'];

        switch ($intencao) {
            case 'falar_humano':
                $conversa->bot_status = 'aguardando_atendente';
                $conversa->humano_iniciado_em = now();
                $conversa->save();

                $this->alertas->abrir($conversa, WhatsAppAlert::HANDOFF, 'IA: pedido de atendimento humano.');

                AuditLogger::log('handoff_ia', 'whatsapp_conversations', $conversa->id, depois: [
                    'bot_status' => 'aguardando_atendente',
                ]);

                $this->registrarNoHistorico($remoteJid, $intencao, true, null);

                return $this->resultado(
                    $etapa,
                    $etapa,
                    $textoIA !== '' ? $textoIA : 'Claro! Já avisei a equipe da clínica — alguém vai te responder em breve. 😊',
                    'ia',
                    $intencao,
                );

            case 'agendar':
            case 'horarios':
            case 'profissionais':
            case 'procedimentos':
                /**
                 * Ação de agenda NUNCA sai daqui direto. No menu, delega a
                 * abertura do fluxo (mostra a lista de profissionais); fora do
                 * menu, o passo atual é que decide o próximo, então a mensagem
                 * volta ao fluxo com o próprio texto. O que não se faz é tratar
                 * os `dados` do modelo como escolha: eles são um palpite.
                 */
                if ($etapa === 'menu') {
                    return $this->delegarAoFluxo($remoteJid, '1', $nomeClinica, $conversa, intencao: $intencao);
                }

                return $this->responderInformacao($remoteJid, $conversa, $intencao, $textoIA);

            case 'saudacao':
            case 'duvida':
                $conversa->save();
                $this->registrarNoHistorico($remoteJid, $intencao, true, null);

                return $this->resultado(
                    $etapa,
                    $etapa,
                    $textoIA !== '' ? $textoIA : $this->menuPadrao($conversa),
                    'ia',
                    $intencao,
                );

            default:
                // 'desconhecido' e qualquer intenção inesperada: o fluxo conduz.
                // Sem isso, uma intenção que o Laravel não conhece viraria
                // silêncio, e silêncio no atendimento é paciente perdido.
                return $this->delegarAoFluxo($remoteJid, $texto, $nomeClinica, $conversa, intencao: $intencao);
        }
    }

    /**
     * Resposta informativa ancorada no banco, para quando a intenção não é uma
     * seleção e não pode virar ação.
     */
    protected function responderInformacao(string $remoteJid, WhatsAppConversation $conversa, string $intencao, string $textoIA): array
    {
        $etapa = $conversa->etapa ?? 'menu';
        $conversa->save();

        $corpo = match ($intencao) {
            'profissionais' => $this->listaProfissionais(),
            'procedimentos' => $this->listaProcedimentos(),
            'horarios' => $this->listaHorarios($conversa),
            default => '',
        };

        $resposta = trim(($textoIA !== '' ? $textoIA."\n\n" : '').$corpo);

        if ($resposta === '') {
            $resposta = 'Me diga como posso ajudar: responder "1" para agendar ou "2" para falar com a recepção.';
        }

        $this->registrarNoHistorico($remoteJid, $intencao, true, null);

        return $this->resultado($etapa, $etapa, $resposta, 'ia', $intencao);
    }

    protected function listaProfissionais(): string
    {
        $linhas = Professional::where('is_active', true)
            ->orderBy('nome')
            ->pluck('nome')
            ->map(fn (string $nome, int $i) => ($i + 1).'. '.$nome)
            ->implode("\n");

        return $linhas !== '' ? "Nossos profissionais:\n\n{$linhas}" : 'Não há profissionais disponíveis agora.';
    }

    protected function listaProcedimentos(): string
    {
        $linhas = Procedure::where('is_active', true)
            ->orderBy('nome')
            ->take(10)
            ->get()
            ->map(fn (Procedure $p, int $i) => ($i + 1).'. '.$p->nome.' ('.$p->duracao_minutos.' min)')
            ->implode("\n");

        return $linhas !== '' ? "Nossos procedimentos:\n\n{$linhas}\n\nValores são informados pela recepção." : 'Não há procedimentos disponíveis agora.';
    }

    /**
     * Horários só quando o profissional e a duração já foram escolhidos no fluxo.
     * Sem eles a conta de disponibilidade não existe, e inventar uma seria o
     * defeito — então pede o que falta.
     */
    protected function listaHorarios(WhatsAppConversation $conversa): string
    {
        $dados = is_array($conversa->dados) ? $conversa->dados : [];
        $profissionalId = (int) ($dados['profissional_id'] ?? 0);
        $duracao = (int) ($dados['duracao'] ?? 0);

        if ($profissionalId <= 0 || $duracao <= 0) {
            return 'Para ver horários, primeiro escolha o profissional e o procedimento respondendo "1" no menu.';
        }

        $dias = collect($this->disponibilidade->horariosLivres($profissionalId, $duracao, FluxoAtendimento::DIAS_DE_AGENDA))
            ->filter(fn (array $dia) => $dia['horarios'] !== [])
            ->map(fn (array $dia) => '• '.$dia['data'].' ('.$dia['dia_semana'].') — '.count($dia['horarios']).' horário(s)')
            ->implode("\n");

        return $dias !== '' ? "Horários disponíveis:\n\n{$dias}\n\nResponda \"menu\" para agendar." : 'Sem horários livres nos próximos dias.';
    }

    /**
     * Chama o fluxo determinístico e normaliza o retorno.
     *
     * Quando o fluxo leva a conversa para a recepção, o bot é pausado: pedir um
     * humano é o gatilho da pausa, e deixar o bot ativo ali faria ele voltar a
     * responder no meio do atendimento.
     */
    protected function delegarAoFluxo(
        string $remoteJid,
        string $texto,
        string $nomeClinica,
        WhatsAppConversation $conversa,
        ?string $intencao = null,
        bool $erroIa = false,
    ): array {
        if ($erroIa) {
            $conversa->save();
        }

        $resultado = $this->fluxo->processar($remoteJid, $texto, $nomeClinica);

        if ($resultado['etapa_para'] === 'recepcao') {
            $conversa->refresh();

            if ($conversa->botAtivo()) {
                $conversa->bot_status = 'aguardando_atendente';
                $conversa->humano_iniciado_em = now();
                $conversa->save();
            }

            $this->alertas->abrir($conversa, WhatsAppAlert::HANDOFF, 'Fluxo encaminhou para a recepção.');
        }

        $this->registrarNoHistorico($remoteJid, $intencao, $intencao !== null, $erroIa ? 'A IA não devolveu uma proposta válida.' : null);

        return $this->resultado(
            $resultado['etapa_de'],
            $resultado['etapa_para'],
            $resultado['resposta'],
            'fluxo',
            $intencao,
        );
    }

    /**
     * Abre a conversa, resetando o ESTADO quando expirada.
     *
     * O reset não toca em `bot_status`: uma conversa sob atendimento humano que
     * fica 4h parada continua sendo do humano, e zerar o status faria o bot
     * voltar a responder no meio de um atendimento que ninguém encerrou.
     */
    protected function abrirConversa(string $remoteJid): WhatsAppConversation
    {
        return WhatsAppConversation::abrirPara($remoteJid, FluxoAtendimento::VALIDADE_HORAS);
    }

    /**
     * Deixa a intenção e o erro no histórico da última mensagem recebida.
     *
     * Best-effort de propósito: numa chamada de API de teste ou num turno sem
     * mensagem gravada não há o que atualizar, e falhar por causa disso
     * derrubaria a resposta em vez de só perder o metadado.
     */
    protected function registrarNoHistorico(string $remoteJid, ?string $intencao, bool $tratado, ?string $erro): void
    {
        try {
            WhatsAppMessage::query()
                ->where('remote_jid', $remoteJid)
                ->where('from_me', false)
                ->latest('id')
                ->limit(1)
                ->update([
                    'ai_intent' => $intencao,
                    'ai_handled' => $tratado,
                    'ai_error' => $erro === null ? null : mb_substr($erro, 0, 255),
                ]);
        } catch (\Throwable $exception) {
            Log::debug('Não foi possível registrar metadados de IA no histórico.', [
                'excecao' => $exception::class,
            ]);
        }
    }

    /**
     * Passos que não passam pela IA: identidade (consentimento/cadastro),
     * comandos globais e escolha por número. Os números são do fluxo — "2" no
     * menu é a recepção, e adivinhar isso com o modelo seria arriscar a escolha.
     */
    protected function vaiParaFluxo(string $etapa, string $texto): bool
    {
        if (in_array($etapa, ['consentimento', 'cadastro', 'encerrado', 'recepcao'], true)) {
            return true;
        }

        if (in_array(mb_strtolower($texto), FluxoAtendimento::COMANDOS_GLOBAIS, true)) {
            return true;
        }

        // Sem nenhuma letra, é seleção de opção (dígitos, emoji de dígito, teclado
        // numérico). Texto com letra vai para a IA, mesmo que contenha número.
        return preg_match('/\p{L}/u', $texto) !== 1;
    }

    protected function menuPadrao(WhatsAppConversation $conversa): string
    {
        $nome = $conversa->dados['nome'] ?? null;
        $saudacao = is_string($nome) && $nome !== '' ? ", {$nome}" : '';

        return "Olá{$saudacao}! 👋 Como posso ajudar?\n\n1️⃣ Agendar consulta\n2️⃣ Falar com a recepção";
    }

    /**
     * @return array{ok: bool, auto_resposta: bool, etapa_de: string, etapa_para: string, resposta: string, handled_by: string, intencao: string|null}
     */
    protected function resultado(
        string $de,
        string $para,
        string $resposta,
        string $handledBy,
        ?string $intencao = null,
        bool $autoResposta = true,
    ): array {
        return [
            'ok' => true,
            'auto_resposta' => $autoResposta,
            'etapa_de' => $de,
            'etapa_para' => $para,
            'resposta' => $resposta,
            'handled_by' => $handledBy,
            'intencao' => $intencao,
        ];
    }
}
