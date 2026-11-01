<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppAlert;
use App\Models\WhatsAppConversation;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Log;

/**
 * A porta de entrada do atendimento automático.
 *
 * Implementa a precedência, do mais restritivo para o mais permissivo:
 *
 * ```
 * opt-out / silêncio  >  pausa ou humano  >  fora do expediente
 *     >  regra automática  >  IA  >  fluxo determinístico
 * ```
 *
 * Sem um lugar único que decida isso, cada caminho (a rota do n8n, a rota da
 * IA, o job de lembrete) acaba com a sua própria cópia da ordem — e um deles
 * responde por cima do atendente. É o que esta classe evita: quem chama
 * `decidir()` recebe `null` para seguir, ou a resposta pronta e o motivo.
 *
 * As decisões daqui NÃO escrevem na agenda nem alteram consulta. Só estado da
 * conversa, alerta para a recepção e texto de resposta.
 */
class Triagem
{
    public function __construct(
        protected MotorDeRegras $regras,
        protected Expediente $expediente,
        protected ConfiguracaoWhatsApp $configuracao,
        protected AlertaRecepcao $alertas,
    ) {}

    /**
     * @return array{ok: bool, auto_resposta: bool, etapa_de: string, etapa_para: string, resposta: string, handled_by: string, intencao: string|null}|null
     */
    public function decidir(WhatsAppConversation $conversa, string $texto, string $nomeClinica = ''): ?array
    {
        $conversa->sincronizarPausa();

        $etapa = $conversa->etapa ?? 'consentimento';
        $botAtivo = $conversa->botAtivo();

        if ($this->pediuParaParar($texto)) {
            return $this->optOut($conversa, $etapa, $botAtivo);
        }

        // Silêncio: pausa, humano ou aguardando atendente. A mensagem continua
        // entrando no histórico; quem não responde é o bot.
        if (! $botAtivo) {
            return $this->silencioso($conversa, $etapa);
        }

        if (! $this->expediente->abertoAgora()) {
            return $this->foraDoExpediente($conversa, $etapa);
        }

        // Passos com contrato próprio, comandos globais e escolha por número
        // seguem direto para o fluxo. O texto ali é DADO do passo, não pergunta
        // livre — uma regra que casasse "1" sequestraria a escolha do menu.
        if ($this->segueParaFluxo($etapa, $texto)) {
            return null;
        }

        $achada = $this->regras->procurar($texto, $etapa);

        if ($achada !== null) {
            return $this->respostaDeRegra($conversa, $etapa, $achada['resposta'], (int) $achada['regra']->id);
        }

        return null;
    }

    /**
     * O texto é dado de um passo do fluxo, não uma pergunta em linguagem livre?
     */
    protected function segueParaFluxo(string $etapa, string $texto): bool
    {
        if (in_array($etapa, MotorDeRegras::ETAPAS_COM_CONTRATO, true)) {
            return true;
        }

        if (in_array($etapa, ['encerrado', 'recepcao'], true)) {
            return true;
        }

        if (in_array(mb_strtolower(trim($texto)), FluxoAtendimento::COMANDOS_GLOBAIS, true)) {
            return true;
        }

        return preg_match('/\p{L}/u', $texto) !== 1;
    }

    /**
     * O paciente pediu para não receber mais mensagem automática?
     */
    public function pediuParaParar(string $texto): bool
    {
        $texto = mb_strtolower(trim($texto));

        if ($texto === '') {
            return false;
        }

        // Comparação por palavra inteira: "parar" precisa casar, mas "apara" ou
        // "comparar" não. Sem isso a palavra dentro de outra dispara opt-out
        // sem o paciente ter pedido.
        foreach ($this->configuracao->palavrasDeOptOut() as $palavra) {
            $padrao = '/(?<![\p{L}\p{N}])'.preg_quote($palavra, '/').'(?![\p{L}\p{N}])/u';

            if (preg_match($padrao, $texto) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{ok: bool, auto_resposta: bool, etapa_de: string, etapa_para: string, resposta: string, handled_by: string, intencao: string|null}
     */
    protected function optOut(WhatsAppConversation $conversa, string $etapa, bool $botAtivo): array
    {
        $jaPausadoSemPrazo = $conversa->estaPausado() && $conversa->pausado_ate === null;

        if (! $jaPausadoSemPrazo) {
            $conversa->pausarSemPrazo();
        }

        AuditLogger::log('opt_out', 'whatsapp_conversations', $conversa->id, depois: [
            'bot_status' => $conversa->bot_status,
            'bot_ativo' => $botAtivo,
        ]);

        Log::info('Paciente pediu para parar o atendimento automático.', [
            'conversa_id' => $conversa->id,
        ]);

        // Numa conversa que já está com um humano, confirmar o opt-out pelo bot
        // seria o bot falando por cima de quem está atendendo. O pedido é
        // registrado e a conversa fica pausada sem prazo; a resposta é silêncio.
        if (! $botAtivo) {
            return $this->resultado($etapa, $etapa, '', 'opt_out');
        }

        return $this->resultado(
            $etapa,
            $etapa,
            (string) $this->configuracao->texto(
                ConfiguracaoWhatsApp::MENSAGEM_OPT_OUT,
                'Tudo bem! Não vou mais enviar mensagens automáticas. Se precisar, é só chamar a clínica.',
            ),
            'opt_out',
        );
    }

    /**
     * @return array{ok: bool, auto_resposta: bool, etapa_de: string, etapa_para: string, resposta: string, handled_by: string, intencao: string|null}
     */
    protected function silencioso(WhatsAppConversation $conversa, string $etapa): array
    {
        return $this->resultado($etapa, $etapa, '', $conversa->bot_status === 'pausado' ? 'pausado' : 'humano');
    }

    /**
     * @return array{ok: bool, auto_resposta: bool, etapa_de: string, etapa_para: string, resposta: string, handled_by: string, intencao: string|null}
     */
    protected function foraDoExpediente(WhatsAppConversation $conversa, string $etapa): array
    {
        $proxima = $this->expediente->proximaAbertura();

        if ($proxima !== null) {
            $conversa->pausarAte($proxima);
        }

        if (! $this->expediente->deveAvisarAusencia((string) $conversa->remote_jid)) {
            return $this->resultado($etapa, $etapa, '', 'ausente');
        }

        // Pausa com prazo na próxima abertura: o bot volta sozinho no horário
        // certo, sem alguém precisar lembrar de retomar. Enquanto isso, a
        // conversa entra na fila da recepção — paciente esperando é trabalho
        // humano, mesmo fora do expediente.
        $this->alertas->abrir($conversa, WhatsAppAlert::FORA_EXPEDIENTE, 'Mensagem recebida fora do expediente.');

        return $this->resultado($etapa, $etapa, $this->expediente->mensagemAusencia(), 'ausente');
    }

    /**
     * @return array{ok: bool, auto_resposta: bool, etapa_de: string, etapa_para: string, resposta: string, handled_by: string, intencao: string|null}
     */
    protected function respostaDeRegra(WhatsAppConversation $conversa, string $etapa, string $resposta, int $regraId): array
    {
        AuditLogger::log('regra_automatica', 'whatsapp_rules', $regraId, depois: [
            'conversa_id' => $conversa->id,
        ]);

        return $this->resultado($etapa, $etapa, $resposta, 'regra');
    }

    /**
     * @return array{ok: bool, auto_resposta: bool, etapa_de: string, etapa_para: string, resposta: string, handled_by: string, intencao: string|null}
     */
    protected function resultado(string $de, string $para, string $resposta, string $origem): array
    {
        return [
            'ok' => true,
            'auto_resposta' => $resposta !== '',
            'etapa_de' => $de,
            'etapa_para' => $para,
            'resposta' => $resposta,
            'handled_by' => $origem,
            'intencao' => null,
        ];
    }
}
