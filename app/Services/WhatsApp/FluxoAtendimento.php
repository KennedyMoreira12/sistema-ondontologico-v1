<?php

namespace App\Services\WhatsApp;

use App\Exceptions\HorarioOcupadoException;
use App\Models\Anamnese;
use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\ClinicProfile;
use App\Models\Paciente;
use App\Models\Procedure;
use App\Models\Professional;
use App\Models\Specialty;
use App\Models\WhatsAppConversation;
use App\Services\Agenda\AgendamentoService;
use App\Services\Agenda\DisponibilidadeService;
use App\Services\AuditLogger;
use App\Services\Tenant\TenantManager;
use App\Support\AnamnesePreAtendimento;
use App\Support\WhatsAppJid;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * A conversa do WhatsApp: consentimento, cadastro, profissional, procedimento,
 * data, horário e confirmação. Menu, sem IA.
 *
 * A máquina mora aqui, e não no n8n, por dois motivos que já custaram caro:
 *
 * 1. O Code node do n8n, nesta instalação, não tem HTTP. `fetch`, `$helpers` e
 *    `require('https')` não existem ali, então a versão da conversa que chamava
 *    a API por dentro do n8n não executava. Aqui a máquina fala direto com o
 *    banco: sem HTTP e sem token no meio do caminho.
 * 2. Estado de conversa testável é estado em PHP. Um `switch` em JavaScript
 *    dentro de um Code node só se verifica lendo registro de execução do n8n; o
 *    mesmo `switch` aqui é um teste de feature comum.
 *
 * O n8n continua sendo o orquestrador: ele aplica os guards (token, allowlist,
 * `from_me`), chama este fluxo e envia a resposta. Nenhuma regra de negócio fica
 * no workflow.
 */
class FluxoAtendimento
{
    /**
     * Atalho que vale em QUALQUER etapa.
     *
     * Sem isto, um paciente que se perde no meio do fluxo precisa terminar a
     * mensagem para sair dele — e a chance de isso acontecer é maior do que
     * parece, porque a lista de profissionais e a de procedimentos são longas.
     *
     * @var list<string>
     */
    public const COMANDOS_GLOBAIS = ['menu', 'inicio', 'cancelar', 'sair', 'voltar', 'confirmar', 'reagendar'];

    /**
     * Quantos dias à frente o bot oferece agenda.
     *
     * Cinco é o que o paciente consegue percorrer em uma conversa de WhatsApp;
     * oferecer dois meses transforma a lista em algo que ninguém lê.
     */
    public const DIAS_DE_AGENDA = 5;

    /**
     * Quantos horários aparecem na tela.
     *
     * O limite existe porque um turno de 4 h com passos de 30 min tem 16 linhas,
     * e o paciente escolhe pelo número: uma lista longa vira chute.
     */
    public const MAXIMO_HORARIOS = 10;

    /**
     * Quantos procedimentos são mostrados de uma vez.
     */
    public const MAXIMO_PROCEDIMENTOS = 12;

    /**
     * Quantas especialidades aparecem de uma vez.
     */
    public const MAXIMO_ESPECIALIDADES = 15;

    /**
     * Mapeamento de sintomas/queixas para especialidades.
     *
     * A chave é o termo que o paciente pode digitar (sinônimos separados por
     * pipe). O valor são fragmentos do nome da especialidade cadastrada na
     * clínica, tentados na ordem. Fragmento, e não nome exato, porque cada
     * clínica nomeia a própria tabela ("Implantes", "ESPECIALISTA EM
     * IMPLANTES", "DENTÍSTICA RESTAURADORA E ESTÉTICA") e um casamento exato
     * faria o recurso funcionar só na clínica em que foi escrito.
     *
     * @var array<string, list<string>>
     */
    public const SINTOMAS_POR_ESPECIALIDADE = [
        'dor de dente|dente doendo|dente latejando|molar doendo|cárie|carie|canal' => ['Endodontia'],
        'dente quebrado|dente trincado|fratura dentária|dente lascado|restauração|restauracao' => ['Restauradora', 'Estética', 'Clínica geral'],
        'limpeza|tártaro|tartaro|remoção de tártaro|profilaxia|higiene bucal' => ['Clínica geral', 'Periodontia'],
        'aparelho|aparelho dentário|brackets|alinhador|mordida torta|dente desalinhado' => ['Ortodontia'],
        'implante|implante dentário|prótese|protese|coroa|ponte fixa|dente perdido|falta dente' => ['Implante'],
        'cirurgia|extração|extracao|siso|siso doendo|cirurgia oral|biópsia|biopsia' => ['Cirurgia'],
        'clareamento|dente amarelado|dente manchado|estética dental|estetica dental|facetas' => ['Estética', 'Restauradora'],
        'gengiva sangrando|gengiva inchada|periodontite|gengivite|recesso gengival' => ['Periodontia'],
        'criança|crianca|dente de leite|primeira consulta criança|odontopediatria' => ['Odontopediatria'],
        'dor de cabeça|dor de cabeca|ATM|articulação|articulacao|má oclusão|ma oclusao|ranger de dente|bruxismo' => ['DTM', 'Clínica geral'],
    ];

    /**
     * Quanto tempo uma conversa abandonada sobrevive.
     *
     * É também o prazo de validade das listas guardadas em `dados`: depois
     * disso os horários da conversa não correspondem mais à agenda real, e o
     * bot tem que reconsultar.
     */
    public const VALIDADE_HORAS = 4;

    public function __construct(
        protected TenantManager $tenants,
        protected DisponibilidadeService $disponibilidade,
        protected AgendamentoService $agendamentos,
        protected ?ConfiguracaoWhatsApp $configuracao = null,
    ) {
        $this->configuracao ??= app(ConfiguracaoWhatsApp::class);
    }

    /**
     * Processa uma mensagem recebida e devolve o que o n8n precisa fazer.
     *
     * @return array{ok: bool, etapa_de: string, etapa_para: string, resposta: string}
     */
    public function processar(string $remoteJid, string $texto, string $nomeClinica = ''): array
    {
        // Defesa em profundidade: `telefone()` reduz o JID a dígitos e um grupo
        // viraria um número inexistente. O bloqueio tem de acontecer antes.
        if (WhatsAppJid::ehGrupo($remoteJid)) {
            return ['ok' => false, 'etapa_de' => '', 'etapa_para' => '', 'resposta' => ''];
        }

        $telefone = $this->telefone($remoteJid);
        $texto = trim($texto);
        $conversa = $this->abrirConversa($remoteJid);
        $etapa = $conversa->etapa ?? 'consentimento';
        $dados = is_array($conversa->dados) ? $conversa->dados : [];

        $comando = mb_strtolower($texto);
        $nome = $dados['nome'] ?? $this->paciente($telefone)?->nome;
        $odonto = $this->configuracao->modoOdontologico();

        if ($odonto) {
            $interceptado = $this->interceptarMensagemOdontologica($etapa, $dados, $texto, $telefone, $nomeClinica);
            if ($interceptado !== null) {
                [$novaEtapa, $novosDados, $resposta] = $interceptado;
                $this->salvar($conversa, $novaEtapa, $novosDados);

                return $this->resultado($etapa, $novaEtapa, $resposta);
            }
        }

        // O comando global é checado antes do switch: \"menu\" precisa funcionar
        // mesmo com um estado corrompido, senão o paciente fica preso.
        if (in_array($comando, self::COMANDOS_GLOBAIS, true)) {
            if ($comando === 'voltar' && $etapa !== 'menu') {
                $etapaAnterior = $this->etapaAnterior($conversa);
                $this->salvar($conversa, $etapaAnterior, $dados);

                return $this->resultado($etapa, $etapaAnterior, $this->textoDaEtapa($etapaAnterior, $dados, $nome));
            }

            if ($comando === 'confirmar') {
                [$novaEtapa, $novosDados, $resposta] = $this->confirmarPresenca($telefone, $dados, $nome);
                $this->salvar($conversa, $novaEtapa, $novosDados);

                return $this->resultado($etapa, $novaEtapa, $resposta);
            }

            if ($odonto && in_array($comando, ['cancelar', 'reagendar', 'sair'], true)) {
                [$novaEtapa, $novosDados, $resposta] = $this->comandoGerenciamentoOdontologico($comando, $telefone, $dados, $nome);
                $this->salvar($conversa, $novaEtapa, $novosDados);

                return $this->resultado($etapa, $novaEtapa, $resposta);
            }

            $dadosMenu = $this->base($dados + ['nome' => $nome]);
            $this->salvar($conversa, 'menu', $dadosMenu);

            return $this->resultado($etapa, 'menu', $this->menu(is_string($nome) ? $nome : null));
        }

        [$numero, $opcao] = $this->opcao($texto);

        [$novaEtapa, $novosDados, $resposta] = match ($etapa) {
            'consentimento' => $this->consentimento($telefone, $opcao, $nomeClinica, $texto),
            'cadastro' => $this->cadastro($telefone, $texto, $dados),
            'menu' => $this->menuInicial($dados, $opcao, $texto),
            'sintoma' => $this->sintoma($texto, $dados),
            'especialidade' => $this->especialidade($dados, $numero, $opcao),
            'profissional' => $this->profissional($dados, $numero, $opcao),
            'procedimento' => $this->procedimento($dados, $numero, $opcao),
            'data' => $this->data($dados, $numero, $opcao),
            'horario' => $this->horario($remoteJid, $dados, $numero, $opcao),
            'minhas_consultas' => $this->minhasConsultas($dados, $numero, $opcao, $telefone),
            'dor_coleta' => $this->dorColeta($dados, $texto, $telefone),
            'duracao_implante' => $this->duracaoImplante($dados, $numero, $opcao),
            'pre_anamnese' => $this->preAnamnese($dados, $texto, $telefone),
            'cancelar_consulta' => $this->cancelarConsultaEtapa($dados, $numero, $opcao, $telefone),
            'reagendar_consulta' => $this->reagendarConsultaEtapa($dados, $numero, $opcao),
            'recepcao' => ['recepcao', $dados, "Um atendente da clínica responderá em breve. 😊\n\nResponda \"menu\" se quiser voltar às opções."],
            'encerrado' => ['encerrado', $dados, 'Atendimento encerrado. Responda \"menu\" se quiser voltar às opções.'],
            default => ['menu', $this->base($dados), $this->menu($dados['nome'] ?? null)],
        };

        // Handoff odontológico suspende o bot para o atendimento humano, mantendo
        // o motivo e o histórico da conversa.
        if ($odonto && $novaEtapa === 'recepcao' && isset($novosDados['motivo_handoff']) && is_string($novosDados['motivo_handoff']) && $novosDados['motivo_handoff'] !== '') {
            $conversa->bot_status = 'aguardando_atendente';
            $conversa->humano_iniciado_em = now();
        }

        $this->salvar($conversa, $novaEtapa, $novosDados);

        return $this->resultado($etapa, $novaEtapa, $resposta);
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function consentimento(string $telefone, ?int $opcao, string $nomeClinica, string $texto = ''): array
    {
        if ($this->configuracao->modoOdontologico()) {
            return $this->consentimentoOdontologico($telefone, $opcao, $nomeClinica, $texto);
        }

        if ($opcao === 0) {
            $paciente = $this->paciente($telefone);

            if ($paciente !== null) {
                return ['menu', ['paciente_id' => $paciente->id, 'nome' => $paciente->nome], $this->menu($paciente->nome)];
            }

            return ['cadastro', [], 'Vamos fazer seu cadastro rápido. Qual é o seu nome completo?'];
        }

        if ($opcao === 1) {
            return ['recepcao', [], 'Tudo bem! Vou avisar a recepção para que um atendente te responda em breve.'];
        }

        return ['consentimento', [], $this->textoConsentimento($nomeClinica)];
    }

    /**
     * @param  array<string, mixed>  $estado
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function cadastro(string $telefone, string $texto, array $estado = []): array
    {
        if (mb_strlen(trim($texto)) < 3) {
            $dados = $this->base($estado);
            if (isset($estado['acao_pendente'])) {
                $dados['acao_pendente'] = $estado['acao_pendente'];
            }

            return ['cadastro', $dados, 'Por favor, informe seu nome completo.'];
        }

        $paciente = $this->paciente($telefone);

        if ($paciente === null) {
            $paciente = Paciente::create([
                'clinica_id' => $this->tenants->id(),
                'nome' => trim($texto),
                'celular' => $telefone,
                'status' => 'ativo',
            ]);

            AuditLogger::log('criar', 'pacientes', $paciente->id, depois: $paciente->only(['nome', 'celular']));
        }

        $base = $this->base($estado + ['paciente_id' => $paciente->id, 'nome' => $paciente->nome]);
        $acao = $estado['acao_pendente'] ?? null;
        $textoAcao = $texto;

        if ($this->configuracao->modoOdontologico() && is_string($acao) && $acao !== '') {
            unset($base['acao_pendente']);
            if ($acao === 'faq' && isset($estado['faq_pendente']) && is_string($estado['faq_pendente'])) {
                $textoAcao = $estado['faq_pendente'];
            }
            unset($base['faq_pendente']);

            return $this->executarAcaoOdontologica($base, $acao, $textoAcao, $telefone, "Cadastro realizado, {$paciente->nome}!\n\n");
        }

        return [
            'menu',
            $base,
            "Cadastro realizado, {$paciente->nome}! ".$this->menu($paciente->nome),
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function menuInicial(array $dados, ?int $opcao, string $texto = ''): array
    {
        if ($this->configuracao->modoOdontologico()) {
            return $this->menuInicialOdontologico($dados, $opcao, $texto);
        }

        if ($opcao === 2) {
            return ['recepcao', $dados, 'Tudo bem! A recepção vai te atender em breve.'];
        }

        if ($opcao === 1) {
            return ['minhas_consultas', $dados, $this->minhasConsultasTexto($dados)];
        }

        if ($opcao !== 0) {
            return ['menu', $dados, $this->menu($dados['nome'] ?? null)];
        }

        $temEspecialidadesVinculadas = Specialty::query()
            ->whereHas('professionals', fn ($query) => $query->where('is_active', true))
            ->exists();

        if (! $temEspecialidadesVinculadas) {
            return $this->listaDeProfissionais($dados);
        }

        return ['especialidade', $dados, $this->textoEspecialidade()];
    }

    /**
     * Lista todos os profissionais ativos, sem passar por especialidade.
     *
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function listaDeProfissionais(array $dados): array
    {
        $profissionais = Professional::with('specialties')
            ->where('is_active', true)
            ->orderBy('nome')
            ->get();

        if ($profissionais->isEmpty()) {
            return ['recepcao', $dados, 'Nenhum profissional disponível para agendamento agora. Vou avisar a recepção.'];
        }

        $lista = $profissionais
            ->map(function (Professional $profissional, int $i): string {
                $especialidades = $profissional->specialties->pluck('name')->join(', ');
                $sufixo = $especialidades !== '' ? " ({$especialidades})" : '';

                return ($i + 1).". {$profissional->nome}{$sufixo}";
            })
            ->implode("\n");

        return [
            'profissional',
            array_merge($dados, ['profissionais' => $profissionais->map(fn (Professional $p) => [
                'id' => $p->id,
                'nome' => $p->nome,
            ])->all()]),
            "Escolha o profissional:\n\n{$lista}\n\nResponda com o número.",
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function profissional(array $dados, ?string $numero, ?int $opcao): array
    {
        $escolhido = $this->itemEscolhido($dados['profissionais'] ?? [], $numero, $opcao);

        if (! is_array($escolhido)) {
            return ['profissional', $dados, 'Opção inválida. Responda com o número do profissional.'];
        }

        // Cortado ANTES de montar a lista e antes de guardar: guardar os 30
        // procedimentos ativos e mostrar 12 faria o "13" ser uma escolha válida
        // que o paciente nunca viu, e a numeração da tela deixaria de bater com
        // a lista.
        $procedimentos = Procedure::where('is_active', true)
            ->orderBy('nome')
            ->take(self::MAXIMO_PROCEDIMENTOS)
            ->get();

        if ($procedimentos->isEmpty()) {
            return ['recepcao', $dados, 'Não tenho a lista de procedimentos carregada. Vou avisar a recepção.'];
        }

        $lista = $procedimentos
            ->map(fn (Procedure $p, int $i) => ($i + 1).". {$p->nome} ({$p->duracao_minutos} min)")
            ->implode("\n");

        return [
            'procedimento',
            $dados + [
                'profissional_id' => $escolhido['id'],
                'profissional' => $escolhido['nome'],
                'procedimentos' => $procedimentos->map(fn (Procedure $p) => [
                    'id' => $p->id,
                    'nome' => $p->nome,
                    'duracao' => (int) $p->duracao_minutos,
                ])->all(),
            ],
            "Certo, {$escolhido['nome']}. Qual procedimento?\n\n{$lista}\n\nResponda com o número.",
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function procedimento(array $dados, ?string $numero, ?int $opcao): array
    {
        $escolhido = $this->itemEscolhido($dados['procedimentos'] ?? [], $numero, $opcao);

        if (! is_array($escolhido)) {
            return ['procedimento', $dados, 'Opção inválida. Responda com o número do procedimento.'];
        }

        // A data é oferecida já com a agenda real, e não num turno seguinte:
        // exigir uma mensagem só para "vou verificar" deixava o paciente
        // esperando um nada, e era o ponto onde a conversa morria.
        $agenda = $this->disponibilidade->horariosLivres(
            (int) $dados['profissional_id'],
            (int) $escolhido['duracao'],
            self::DIAS_DE_AGENDA,
        );

        $dias = $this->diasComHorarios($agenda);

        if ($dias === []) {
            return [
                'menu',
                $this->base($dados),
                "Sem horários livres para {$escolhido['nome']} com {$dados['profissional']} nos próximos dias.\n\n".$this->menu($dados['nome'] ?? null),
            ];
        }

        $lista = collect($dias)
            ->map(fn (array $dia, int $i) => ($i + 1).". {$dia['data']} ({$dia['dia_semana']}) — {$dia['quantidade']} horário(s)")
            ->implode("\n");

        $novosDados = $dados + [
            'procedimento_id' => $escolhido['id'],
            'procedimento' => $escolhido['nome'],
            'duracao' => (int) $escolhido['duracao'],
            'dias' => $dias,
        ];

        if ($this->configuracao->modoOdontologico()) {
            $duracaoEtapa = $this->solicitarDuracaoImplanteSeNecessario($novosDados, $escolhido);
            if ($duracaoEtapa !== null) {
                return $duracaoEtapa;
            }
        }

        return [
            'data',
            $novosDados,
            "Escolha o dia para {$escolhido['nome']} ({$escolhido['duracao']} min):\n\n{$lista}\n\nResponda com o número.",
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function data(array $dados, ?string $numero, ?int $opcao): array
    {
        $escolhido = $this->itemEscolhido($dados['dias'] ?? [], $numero, $opcao);

        if (! is_array($escolhido)) {
            return ['data', $dados, 'Opção inválida. Responda com o número do dia.'];
        }

        // Reconsultado, e não a lista guardada em `dados`: entre a tela do dia e
        // a escolha do horário pode passar uma hora, e nesse intervalo outra
        // pessoa agenda o mesmo slot.
        $horarios = $this->disponibilidade->horariosDoDia(
            (int) $dados['profissional_id'],
            (int) $dados['duracao'],
            $escolhido['data'],
            self::DIAS_DE_AGENDA,
        );

        if ($horarios === []) {
            return ['data', $dados, 'Esse dia não tem mais horários livres. Responda \"menu\" para ver outras opções.'];
        }

        // O mesmo corte do procedimento: a tela mostra 10, e é só o que o paciente
        // pode escolher que vai para o estado. Guardar os 16 do turno permitiria
        // agendar o "14" para alguém que nunca o viu.
        $visiveis = collect($horarios)
            ->take(self::MAXIMO_HORARIOS)
            ->values();

        $lista = $visiveis
            ->map(fn (string $hora, int $i) => ($i + 1).". {$hora}")
            ->implode("\n");

        return [
            'horario',
            $dados + [
                'data_escolhida' => $escolhido['data'],
                'horarios' => $visiveis->all(),
            ],
            "Horários livres em {$escolhido['data']} ({$escolhido['dia_semana']}):\n\n{$lista}\n\nResponda com o número.",
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function horario(string $remoteJid, array $dados, ?string $numero, ?int $opcao): array
    {
        $hora = $this->itemEscolhido($dados['horarios'] ?? [], $numero, $opcao);

        if (! is_string($hora)) {
            return ['horario', $dados, 'Opção inválida. Responda com o número do horário.'];
        }

        /**
         * Chave por conversa+procedimento+slot: a Evolution reentrega o mesmo
         * webhook, e sem esta chave a reentrega criaria uma segunda consulta para
         * a mesma pessoa no mesmo horário.
         */
        $chave = mb_substr(
            "{$remoteJid}|{$dados['procedimento_id']}|{$dados['data_escolhida']}|{$hora}",
            0,
            64,
        );

        try {
            $this->agendamentos->criar([
                'paciente_id' => (int) $dados['paciente_id'],
                'profissional_id' => (int) $dados['profissional_id'],
                'dia' => $dados['data_escolhida'],
                'hora_inicio' => $hora,
                // A duração vem do procedimento escolhido, nunca um número solto.
                'duracao' => (int) $dados['duracao'],
                'tipo' => AppointmentStatus::AGENDADO,
                'observacao' => "WhatsApp: {$dados['procedimento']}",
                'idempotency_key' => $chave,
            ]);
        } catch (HorarioOcupadoException) {
            return ['horario', $dados, 'Esse horário acabou de ser ocupado. Responda \"menu\" para ver outros.'];
        } catch (LockTimeoutException) {
            return ['horario', $dados, 'A agenda está ocupada no momento. Tente de novo em instantes.'];
        } catch (UniqueConstraintViolationException) {
            /**
             * Perdeu a corrida do índice único da chave de idempotência: outra
             * requisição com a mesma chave gravou primeiro. O cliente quer o
             * agendamento, e ele existe — então isto é sucesso.
             */
        } catch (\Throwable $exception) {
            Log::error('A máquina de estados do WhatsApp não conseguiu agendar.', [
                'remote_jid' => $remoteJid,
                'erro' => $exception->getMessage(),
            ]);

            return ['horario', $dados, 'Não foi possível confirmar o agendamento agora. Tente de novo.'];
        }

        $nome = $dados['nome'] ?? null;

        $endereco = $this->configuracao->modoOdontologico() ? $this->enderecoConfirmacao() : null;
        $linhaEndereco = $endereco ? "\n📍 {$endereco}" : '';

        return [
            'menu',
            $this->base($dados),
            "✅ Agendamento confirmado!\n\n"
            ."📅 {$dados['data_escolhida']} às {$hora}\n"
            ."🦷 {$dados['procedimento']}\n"
            ."👤 {$dados['profissional']}{$linhaEndereco}\n\n"
            .$this->menu($nome),
        ];
    }

    /**
     * O mínimo do estado que precisa sobreviver a um retorno ao menu.
     *
     * `paciente_id` é o campo que se perde com facilidade: as telas que devolvem a
     * conversa ao menu guardavam só o nome, e o paciente que usava \"menu\" no meio
     * do agendamento chegava à tela de horário com `paciente_id` vazio — o que vira
     * violação de chave estrangeira e uma consulta órfã, com a resposta "não foi
     * possível confirmar" para quem fez tudo certinho.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    protected function base(array $dados): array
    {
        $base = [];

        if (($dados['paciente_id'] ?? null) !== null) {
            $base['paciente_id'] = (int) $dados['paciente_id'];
        }

        if (($dados['nome'] ?? null) !== null) {
            $base['nome'] = $dados['nome'];
        }

        return $base;
    }

    /**
     * Escolhe um item de lista pela posição enviada pelo paciente.
     *
     * Texto não numérico e número fora da faixa devolvem a mesma coisa, porque
     * para o paciente é a mesma coisa: ele não escolheu nenhum item da lista.
     */
    protected function itemEscolhido(mixed $lista, ?string $numero, ?int $opcao): mixed
    {
        if ($numero === null || $opcao === null || ! is_array($lista)) {
            return null;
        }

        return $lista[$opcao] ?? null;
    }

    /**
     * Texto digitado pelo paciente convertido em número de opção.
     *
     * Só dígitos contam: o paciente copia o menu inteiro, emoji incluso, e
     * "1️⃣ Agendar consulta" tem que valer como \"1\".
     *
     * @return array{0: string|null, 1: int|null}
     */
    protected function opcao(string $texto): array
    {
        $numero = preg_replace('/\D/', '', $texto);

        if ($numero === null || $numero === '') {
            return [null, null];
        }

        return [$numero, (int) $numero - 1];
    }

    /**
     * Só os dias que têm horário livre, no formato que a tela do paciente
     * precisa.
     *
     * @param  list<array<string, mixed>>  $agenda
     * @return list<array{data: string, dia_semana: string, quantidade: int}>
     */
    protected function diasComHorarios(array $agenda): array
    {
        return collect($agenda)
            ->filter(fn (array $dia) => $dia['horarios'] !== [])
            ->map(fn (array $dia) => [
                'data' => $dia['data'],
                'dia_semana' => $dia['dia_semana'],
                'quantidade' => count($dia['horarios']),
            ])
            ->values()
            ->all();
    }

    /**
     * Paciente pelo celular, casando pelos últimos 9 dígitos.
     *
     * Os últimos 9 e não o número inteiro: o mesmo paciente aparece gravado com
     * DDD, com zero à esquerda e com o nono dígito acrescentado, e casar pelo
     * inteiro exato faz o bot pedir cadastro de quem já existe.
     */
    protected function paciente(string $telefone): ?Paciente
    {
        if (strlen($telefone) < 9) {
            return null;
        }

        return Paciente::where('celular', 'like', '%'.substr($telefone, -9))->first();
    }

    /**
     * Lê a conversa, resetando se estiver expirada.
     *
     * A expiração é verificada aqui, e não só guardada: sem esta checagem, uma
     * conversa abandonada na etapa `horario` voltaria do dia seguinte ainda
     * esperando a escolha de um horário de um turno que já passou, e o bot
     * marcaria a consulta para um dia que nem existe mais.
     */
    protected function abrirConversa(string $remoteJid): WhatsAppConversation
    {
        return WhatsAppConversation::abrirPara($remoteJid, self::VALIDADE_HORAS);
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    protected function salvar(WhatsAppConversation $conversa, string $etapa, array $dados): void
    {
        $conversa->etapa = $etapa;
        $conversa->dados = $dados;
        $conversa->expires_at = now()->addHours(self::VALIDADE_HORAS);
        $conversa->save();
    }

    protected function menu(?string $nome): string
    {
        if ($this->configuracao->modoOdontologico()) {
            $texto = $this->configuracao->texto(ConfiguracaoWhatsApp::MENSAGEM_MENU);
            if (is_string($texto) && trim($texto) !== '') {
                return $this->personalizarTextoOdontologico($texto, $nome);
            }

            return 'Como podemos ajudá-lo(a) hoje?

'
                .'1. Gostaria de agendar uma avaliação
'
                .'2. Estou sentindo dor ou desconforto
'
                .'3. Gostaria de saber sobre algum procedimento
'
                .'4. Já sou paciente e preciso de atendimento
'
                .'5. Gostaria de falar com a equipe

'
                .'Se preferir, pode escrever sua dúvida diretamente por aqui.';
        }

        $saudacao = is_string($nome) && $nome !== '' ? ", {$nome}" : '';

        return "Olá{$saudacao}! 👋 Como posso ajudar?\n\n1️⃣ Agendar consulta\n2️⃣ Minhas consultas\n3️⃣ Falar com a recepção";
    }

    /**
     * Determina a etapa anterior com base na etapa atual, para o comando "voltar".
     */
    protected function etapaAnterior(WhatsAppConversation $conversa): string
    {
        $etapa = $conversa->etapa ?? 'menu';
        $dados = is_array($conversa->dados) ? $conversa->dados : [];

        return match ($etapa) {
            'sintoma' => 'especialidade',
            'especialidade' => 'menu',
            'profissional' => isset($dados['especialidade_id']) ? 'especialidade' : 'menu',
            'procedimento' => 'profissional',
            'data' => isset($dados['duracao_implante_opcoes']) ? 'duracao_implante' : 'procedimento',
            'duracao_implante' => 'procedimento',
            'horario' => 'data',
            'minhas_consultas' => 'menu',
            'dor_coleta', 'pre_anamnese', 'cancelar_consulta', 'reagendar_consulta' => 'menu',
            default => 'menu',
        };
    }

    /**
     * Texto adequado para cada etapa ao voltar, para não deixar o paciente sem contexto.
     */
    protected function textoDaEtapa(string $etapa, array $dados, ?string $nome): string
    {
        return match ($etapa) {
            'especialidade' => $this->textoEspecialidade(),
            'sintoma' => $this->textoSintoma(),
            'profissional' => $this->textoProfissional($dados),
            'procedimento' => $this->textoProcedimento(),
            'data' => $this->textoData($dados),
            'horario' => $this->textoHorario($dados),
            'minhas_consultas' => $this->minhasConsultasTexto($dados),
            'dor_coleta' => $this->textoDorColeta($dados),
            'duracao_implante' => $this->textoDuracaoImplante($dados),
            'pre_anamnese' => $this->textoPreAnamnese($dados),
            'cancelar_consulta' => $this->textoCancelarConsulta($dados),
            'reagendar_consulta' => $this->textoReagendarConsulta($dados),
            default => $this->menu($nome),
        };
    }

    protected function textoEspecialidade(): string
    {
        $especialidades = Specialty::orderBy('name')
            ->take(self::MAXIMO_ESPECIALIDADES)
            ->get();

        if ($especialidades->isEmpty()) {
            return 'Não há especialidades cadastradas.';
        }

        $lista = $especialidades
            ->map(fn (Specialty $e, int $i) => ($i + 1).". {$e->name}")
            ->implode("\n");

        return "Escolha a especialidade:\n\n{$lista}\n\n".(count($especialidades) + 1).". Descrever o que sinto (ex: dor de dente)\n\nResponda com o número.";
    }

    protected function textoProfissional(array $dados): string
    {
        $especialidadeId = $dados['especialidade_id'] ?? null;

        $query = Professional::with('specialties')->where('is_active', true);

        if ($especialidadeId) {
            $query->whereHas('specialties', fn ($q) => $q->where('specialties.id', $especialidadeId));
        }

        $profissionais = $query->orderBy('nome')->get();

        // Mesmo recuo de `profissionaisDaEspecialidade`: sem vínculo cadastrado,
        // mostrar todos é melhor do que deixar o paciente sem opção ao voltar.
        if ($profissionais->isEmpty() && $especialidadeId) {
            $profissionais = Professional::with('specialties')
                ->where('is_active', true)
                ->orderBy('nome')
                ->get();
        }

        if ($profissionais->isEmpty()) {
            return 'Não há profissionais para esta especialidade.';
        }

        $lista = $profissionais
            ->map(function (Professional $profissional, int $i): string {
                $especialidades = $profissional->specialties->pluck('name')->join(', ');
                $sufixo = $especialidades !== '' ? " ({$especialidades})" : '';

                return ($i + 1).". {$profissional->nome}{$sufixo}";
            })
            ->implode("\n");

        return "Escolha o profissional:\n\n{$lista}\n\nResponda com o número.";
    }

    protected function textoProcedimento(): string
    {
        $procedimentos = Procedure::where('is_active', true)
            ->orderBy('nome')
            ->take(self::MAXIMO_PROCEDIMENTOS)
            ->get();

        if ($procedimentos->isEmpty()) {
            return 'Não há procedimentos cadastrados.';
        }

        $lista = $procedimentos
            ->map(fn (Procedure $p, int $i) => ($i + 1).". {$p->nome} ({$p->duracao_minutos} min)")
            ->implode("\n");

        return "Qual procedimento?\n\n{$lista}\n\nResponda com o número.";
    }

    protected function textoData(array $dados): string
    {
        $profissionalId = $dados['profissional_id'] ?? null;
        $duracao = $dados['duracao'] ?? null;

        if (! $profissionalId || ! $duracao) {
            return 'Escolha primeiro o profissional e o procedimento.';
        }

        $agenda = $this->disponibilidade->horariosLivres(
            (int) $profissionalId,
            (int) $duracao,
            self::DIAS_DE_AGENDA,
        );

        $dias = $this->diasComHorarios($agenda);

        if ($dias === []) {
            return 'Sem horários livres nos próximos dias.';
        }

        $lista = collect($dias)
            ->map(fn (array $dia, int $i) => ($i + 1).". {$dia['data']} ({$dia['dia_semana']}) — {$dia['quantidade']} horário(s)")
            ->implode("\n");

        return "Escolha o dia:\n\n{$lista}\n\nResponda com o número.";
    }

    protected function textoHorario(array $dados): string
    {
        $profissionalId = $dados['profissional_id'] ?? null;
        $duracao = $dados['duracao'] ?? null;
        $data = $dados['data_escolhida'] ?? null;

        if (! $profissionalId || ! $duracao || ! $data) {
            return 'Escolha primeiro o profissional, procedimento e data.';
        }

        $horarios = $this->disponibilidade->horariosDoDia(
            (int) $profissionalId,
            (int) $duracao,
            $data,
            self::DIAS_DE_AGENDA,
        );

        if ($horarios === []) {
            return 'Esse dia não tem mais horários livres.';
        }

        $visiveis = collect($horarios)
            ->take(self::MAXIMO_HORARIOS)
            ->values();

        $lista = $visiveis
            ->map(fn (string $hora, int $i) => ($i + 1).". {$hora}")
            ->implode("\n");

        return "Horários livres em {$data}:\n\n{$lista}\n\nResponda com o número.";
    }

    /**
     * Lista especialidades e guarda a escolhida para filtrar profissionais.
     */
    protected function especialidade(array $dados, ?string $numero, ?int $opcao): array
    {
        if ($opcao === null) {
            return ['especialidade', $dados, $this->textoEspecialidade()];
        }

        $especialidades = $this->especialidadesVisiveis();

        if ($especialidades->isEmpty()) {
            return $this->listaDeProfissionais($dados);
        }

        // Última opção é "Descrever o que sinto".
        if ($opcao === $especialidades->count()) {
            return ['sintoma', $dados, $this->textoSintoma()];
        }

        $escolhida = $especialidades[$opcao] ?? null;

        if (! $escolhida) {
            return ['especialidade', $dados, 'Opção inválida. Responda com o número da especialidade.'];
        }

        return $this->profissionaisDaEspecialidade($escolhida, $dados);
    }

    /**
     * @return Collection<int, Specialty>
     */
    protected function especialidadesVisiveis(): Collection
    {
        return Specialty::orderBy('name')
            ->take(self::MAXIMO_ESPECIALIDADES)
            ->get();
    }

    /**
     * Profissionais de uma especialidade, com queda para todos os ativos quando
     * o vínculo profissional-especialidade ainda não existe na clínica.
     *
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function profissionaisDaEspecialidade(Specialty $especialidade, array $dados): array
    {
        $profissionais = Professional::with('specialties')
            ->where('is_active', true)
            ->whereHas('specialties', fn ($query) => $query->where('specialties.id', $especialidade->id))
            ->orderBy('nome')
            ->get();

        $nota = "Certo, {$especialidade->name}. ";

        if ($profissionais->isEmpty()) {
            // Especialidade sem profissional vinculado não pode ser um beco sem
            // saída: mostra todos os ativos, como o fluxo fazia antes da etapa
            // de especialidade.
            $profissionais = Professional::with('specialties')
                ->where('is_active', true)
                ->orderBy('nome')
                ->get();

            $nota = "Ainda não há profissional específico para {$especialidade->name}. ";
        }

        if ($profissionais->isEmpty()) {
            return ['recepcao', $dados, 'Nenhum profissional disponível para agendamento agora. Vou avisar a recepção.'];
        }

        $lista = $profissionais
            ->map(function (Professional $profissional, int $i): string {
                $especialidadesNomes = $profissional->specialties->pluck('name')->join(', ');
                $sufixo = $especialidadesNomes !== '' ? " ({$especialidadesNomes})" : '';

                return ($i + 1).". {$profissional->nome}{$sufixo}";
            })
            ->implode("\n");

        return [
            'profissional',
            array_merge($dados, [
                'especialidade_id' => $especialidade->id,
                'especialidade' => $especialidade->name,
                'profissionais' => $profissionais->map(fn (Professional $p) => [
                    'id' => $p->id,
                    'nome' => $p->nome,
                ])->all(),
            ]),
            $nota."Escolha o profissional:\n\n{$lista}\n\nResponda com o número.",
        ];
    }

    /**
     * Texto da etapa de sintoma.
     */
    protected function textoSintoma(): string
    {
        return "Descreva o que você está sentindo.\n\nExemplos: dor de dente, limpeza, aparelho, implante, gengiva sangrando.\n\nResponda com a sua queixa ou digite 'menu' para voltar.";
    }

    /**
     * Busca a especialidade pelo sintoma descrito pelo paciente e avança para
     * os profissionais dela.
     *
     * @param  string  $texto  O que o paciente digitou (texto livre)
     * @param  array<string, mixed>  $dados  Estado da conversa
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function sintoma(string $texto, array $dados): array
    {
        $textoNormalizado = mb_strtolower(trim($texto));

        foreach (self::SINTOMAS_POR_ESPECIALIDADE as $sintomas => $fragmentos) {
            foreach (explode('|', $sintomas) as $termo) {
                if (mb_strpos($textoNormalizado, mb_strtolower(trim($termo))) === false) {
                    continue;
                }

                $especialidade = $this->especialidadePorFragmentos($fragmentos);

                if ($especialidade !== null) {
                    return $this->profissionaisDaEspecialidade($especialidade, $dados);
                }

                break;
            }
        }

        // Sem casamento: volta à lista de especialidades em vez de insistir na
        // mesma pergunta. O paciente que escreveu algo fora do mapa ainda
        // consegue escolher pelo número.
        return [
            'especialidade',
            $dados,
            "Não consegui identificar a especialidade pelo que você escreveu.\n\n".$this->textoEspecialidade(),
        ];
    }

    /**
     * Procura a especialidade da clínica por fragmento de nome, na ordem em que
     * os fragmentos foram declarados.
     *
     * @param  list<string>  $fragmentos
     */
    protected function especialidadePorFragmentos(array $fragmentos): ?Specialty
    {
        $especialidades = $this->especialidadesVisiveis();

        foreach ($fragmentos as $fragmento) {
            $encontrada = $especialidades->first(
                fn (Specialty $especialidade) => mb_stripos($especialidade->name, $fragmento) !== false,
            );

            if ($encontrada !== null) {
                return $encontrada;
            }
        }

        return null;
    }

    /**
     * Confirma a consulta do paciente para amanhã (resposta ao lembrete de 24h).
     *
     * @param  string  $telefone  Telefone do paciente (apenas dígitos)
     * @param  array<string, mixed>  $dados  Estado da conversa
     * @param  string|null  $nome  Nome do paciente
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function confirmarPresenca(string $telefone, array $dados, ?string $nome): array
    {
        $paciente = $this->paciente($telefone);

        if (! $paciente) {
            return ['menu', $this->base($dados), 'Não consegui identificar seu cadastro. Responda \"menu\" para começar novamente.'];
        }

        $amanha = Carbon::tomorrow()->startOfDay();
        $statusIds = AppointmentStatus::query()
            ->whereIn('name', [AppointmentStatus::AGENDADO, AppointmentStatus::CONFIRMADO])
            ->pluck('id');

        $consulta = Appointment::query()
            ->where('paciente_id', $paciente->id)
            ->whereIn('appointment_status_id', $statusIds)
            ->whereDate('inicio', $amanha)
            ->orderBy('inicio')
            ->first();

        if (! $consulta) {
            return ['menu', $this->base($dados), "Não há consulta agendada para amanhã em seu nome.\n\n".$this->menu($nome ?? null)];
        }

        $statusConfirmado = AppointmentStatus::idFor(AppointmentStatus::CONFIRMADO);
        $consulta->update(['appointment_status_id' => $statusConfirmado]);

        AuditLogger::log('confirmar', 'appointments', $consulta->id, depois: ['appointment_status_id' => $statusConfirmado]);

        $hora = $consulta->inicio->format('H:i');
        $profissional = $consulta->profissional?->nome ?? 'profissional';

        $endereco = $this->configuracao->modoOdontologico() ? $this->enderecoConfirmacao() : null;
        $linhaEndereco = $endereco ? "\n📍 {$endereco}" : '';

        return [
            'menu',
            $this->base($dados),
            "✅ Consulta confirmada!\n\n📅 Amanhã às {$hora}\n👤 {$profissional}{$linhaEndereco}\n\nObrigado por confirmar. Até lá!\n\n".$this->menu($nome ?? null),
        ];
    }

    /**
     * Mostra as consultas futuras do paciente.
     */
    protected function minhasConsultas(array $dados, ?string $numero, ?int $opcao, string $telefone = ''): array
    {
        if ($this->configuracao->modoOdontologico()) {
            return $this->minhasConsultasOdontologico($dados, $numero, $opcao, $telefone);
        }

        if ($opcao === null) {
            return ['minhas_consultas', $dados, $this->minhasConsultasTexto($dados)];
        }

        // Qualquer número volta ao menu.
        return ['menu', $this->base($dados), $this->menu($dados['nome'] ?? null)];
    }

    protected function minhasConsultasTexto(array $dados): string
    {
        $pacienteId = $dados['paciente_id'] ?? null;

        if (! $pacienteId) {
            return 'Não consegui identificar seu cadastro. Responda \"menu\" para começar novamente.';
        }

        $consultas = Appointment::with(['profissional', 'status'])
            ->where('paciente_id', $pacienteId)
            ->where('inicio', '>=', now())
            ->orderBy('inicio')
            ->get()
            // Cancelada não é consulta a mostrar: o paciente não pode chegar no
            // consultório por causa de uma linha que já foi desmarcada.
            ->reject(fn (Appointment $c) => $c->status?->name === AppointmentStatus::CANCELADO);

        $nomePaciente = is_string($dados['nome'] ?? null) && trim((string) $dados['nome']) !== ''
            ? trim((string) $dados['nome'])
            : null;
        $cabecalho = $nomePaciente ? "Suas consultas, {$nomePaciente}:" : 'Suas consultas:';

        if ($consultas->isEmpty()) {
            $msg = $nomePaciente
                ? "Você não tem consultas agendadas, {$nomePaciente}."
                : 'Você não tem consultas agendadas.';

            return $msg."\n\n".$this->menu($dados['nome'] ?? null);
        }

        $linhas = $consultas->map(function (Appointment $c): string {
            $statusNome = $c->status?->label ?? $c->status?->name ?? 'Agendado';

            return "📅 {$c->inicio->format('d/m H:i')} — {$c->profissional?->nome} ({$statusNome})";
        })->implode("\n");

        return "{$cabecalho}\n\n{$linhas}\n\nResponda \"menu\" para voltar.";
    }

    protected function textoConsentimento(string $nomeClinica): string
    {
        if ($this->configuracao->modoOdontologico()) {
            $texto = $this->configuracao->texto(ConfiguracaoWhatsApp::MENSAGEM_BOAS_VINDAS);
            if (is_string($texto) && trim($texto) !== '') {
                return $texto;
            }

            return 'Olá! Seja bem-vindo(a)! 😊

'
                .'Como podemos ajudá-lo(a) hoje?

'
                .'1. Gostaria de agendar uma avaliação
'
                .'2. Estou sentindo dor ou desconforto
'
                .'3. Gostaria de saber sobre algum procedimento
'
                .'4. Já sou paciente e preciso de atendimento
'
                .'5. Gostaria de falar com a equipe

'
                .'Se preferir, pode escrever sua dúvida diretamente por aqui.';
        }

        $clinica = $nomeClinica !== '' ? $nomeClinica : 'clínica';

        return "Olá! Sou a assistente virtual da {$clinica}.\n\n"
            .'Usamos o WhatsApp para atendimento e agendamento. Seus dados são tratados conforme a LGPD e usados apenas para esse fim.'."\n\n"
            ."Deseja continuar?\n\n1️⃣ Sim, continuar\n2️⃣ Falar com a recepção";
    }

    protected function telefone(string $remoteJid): string
    {
        return preg_replace('/\D/', '', $remoteJid);
    }

    /**
     * @return array{ok: bool, etapa_de: string, etapa_para: string, resposta: string}
     */
    protected function resultado(string $de, string $para, string $resposta): array
    {
        return [
            'ok' => true,
            'etapa_de' => $de,
            'etapa_para' => $para,
            'resposta' => $resposta,
        ];
    }

    /**
     * Intercepta mensagens em modo odontológico para FAQ, urgência/dor e
     * encaminhamentos que devem acontecer antes do roteamento padrão.
     *
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}|null
     */
    protected function interceptarMensagemOdontologica(string $etapa, array $dados, string $texto, string $telefone, string $nomeClinica): ?array
    {
        $textoLimpo = trim($texto);
        if ($textoLimpo === '') {
            return null;
        }

        $etapasTextoLivre = ['cadastro', 'dor_coleta', 'pre_anamnese'];
        $numero = $this->opcao($textoLimpo)[0];
        $ehNumero = $numero !== null;

        if (! in_array($etapa, $etapasTextoLivre, true)) {
            $urgencia = $this->detectarUrgencia($textoLimpo);
            if ($urgencia !== null) {
                return $this->iniciarColetaDor($dados, $textoLimpo, $telefone, $urgencia);
            }

            if (! $ehNumero) {
                $faq = $this->respostaFaq($textoLimpo, $dados);
                if ($faq !== null) {
                    $prox = $etapa === 'consentimento' ? 'consentimento' : ($etapa ?: 'menu');

                    return [$prox, $dados, $faq];
                }
            }
        }

        if (! $ehNumero && $this->pediuHumanoTextoLivre($textoLimpo)) {
            $dadosH = $this->base($dados);
            $dadosH['motivo_handoff'] = 'Paciente solicitou atendimento humano pelo WhatsApp.';

            return ['recepcao', $dadosH, $this->textoTransferenciaHumana()];
        }

        return null;
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function consentimentoOdontologico(string $telefone, ?int $opcao, string $nomeClinica, string $texto): array
    {
        $paciente = $this->paciente($telefone);
        $dadosBase = $paciente ? $this->base(['paciente_id' => $paciente->id, 'nome' => $paciente->nome]) : [];

        if ($opcao === null) {
            if ($paciente !== null) {
                $pos = $this->posAtendimentoSeAplicavel($dadosBase, $telefone);
                if ($pos !== null) {
                    return $pos;
                }

                return ['menu', $dadosBase, $this->menu($paciente->nome)];
            }

            return ['consentimento', [], $this->textoConsentimento($nomeClinica)];
        }

        $acao = match ($opcao) {
            0 => 'agendar',
            1 => 'dor',
            2 => 'faq',
            3 => 'ja_sou_paciente',
            4 => 'humano',
            default => null,
        };

        if ($acao === null) {
            if ($paciente !== null) {
                return ['menu', $dadosBase, $this->menu($paciente->nome)];
            }

            return ['consentimento', [], $this->textoConsentimento($nomeClinica)];
        }

        if ($paciente === null && in_array($acao, ['agendar', 'dor', 'ja_sou_paciente'], true)) {
            $dadosPendentes = ['acao_pendente' => $acao];

            return ['cadastro', $dadosPendentes, 'Para seguirmos com seu atendimento, qual é o seu nome completo?'];
        }

        if ($acao === 'faq') {
            return ['menu', $dadosBase, $this->textoFaqIntro()];
        }

        return $this->executarAcaoOdontologica($dadosBase, $acao, $texto, $telefone);
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function menuInicialOdontologico(array $dados, ?int $opcao, string $texto): array
    {
        if ($opcao === null) {
            return ['menu', $dados, $this->menu($dados['nome'] ?? null)];
        }

        $acao = match ($opcao) {
            0 => 'agendar',
            1 => 'dor',
            2 => 'faq',
            3 => 'ja_sou_paciente',
            4 => 'humano',
            default => null,
        };

        if ($acao === null) {
            return ['menu', $dados, $this->menu($dados['nome'] ?? null)];
        }

        $telefone = $this->telefoneDeDados($dados);

        return $this->executarAcaoOdontologica($this->base($dados), $acao, $texto, $telefone);
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function executarAcaoOdontologica(array $dados, string $acao, string $texto = '', string $telefone = '', string $prefixo = ''): array
    {
        return match ($acao) {
            'agendar' => $this->iniciarAgendamentoOdontologico($dados, $prefixo),
            'dor' => $this->iniciarColetaDor($dados, $texto, $telefone),
            'faq' => $this->iniciarFaq($dados, $texto, $prefixo),
            'ja_sou_paciente' => $this->menuJaSouPaciente($dados, $telefone, $prefixo),
            'humano' => (function () use ($dados) {
                $novos = $this->base($dados);
                $novos['motivo_handoff'] = 'Paciente solicitou atendimento humano pela equipe.';

                return ['recepcao', $novos, $this->textoTransferenciaHumana()];
            })(),
            default => ['menu', $this->base($dados), $this->menu($dados['nome'] ?? null)],
        };
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function iniciarAgendamentoOdontologico(array $dados, string $prefixo = ''): array
    {
        $pacienteId = $dados['paciente_id'] ?? null;
        if (! $pacienteId) {
            $dados['acao_pendente'] = 'agendar';

            return ['cadastro', $dados, 'Para agendar, qual é o seu nome completo?'];
        }

        $resultado = $this->menuInicialPadraoAgendamento($dados);
        if ($prefixo !== '') {
            $resultado[2] = $prefixo.$resultado[2];
        }

        return $resultado;
    }

    /**
     * Reaproveita o fluxo padrão de agendamento (especialidade/profissional).
     *
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function menuInicialPadraoAgendamento(array $dados): array
    {
        $temEspecialidadesVinculadas = Specialty::query()
            ->whereHas('professionals', fn ($query) => $query->where('is_active', true))
            ->exists();

        if (! $temEspecialidadesVinculadas) {
            return $this->listaDeProfissionais($dados);
        }

        return ['especialidade', $dados, $this->textoEspecialidade()];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function iniciarColetaDor(array $dados, string $texto, string $telefone = '', ?array $urgencia = null): array
    {
        $novos = $this->base($dados);
        $novos['dor'] = [
            'mensagem_inicial' => mb_substr(trim($texto), 0, 500),
            'grave' => (bool) ($urgencia['grave'] ?? false),
        ];

        $fluxo = $this->configuracao->fluxoOdontologico() ?? [];
        $urgenciaCfg = is_array($fluxo['urgencia'] ?? null) ? $fluxo['urgencia'] : [];
        $mensagem = is_string($urgenciaCfg['mensagem'] ?? null) && trim((string) $urgenciaCfg['mensagem']) !== ''
            ? (string) $urgenciaCfg['mensagem']
            : 'Sinto muito que você esteja com desconforto. Para direcionar seu atendimento, poderia me informar onde sente a dor/desconforto e há quanto tempo isso começou?';

        if (($urgencia['grave'] ?? false) === true && is_string($urgenciaCfg['mensagem_grave'] ?? null) && trim((string) $urgenciaCfg['mensagem_grave']) !== '') {
            $mensagem .= '

'.(string) $urgenciaCfg['mensagem_grave'];
        }

        $mensagem .= '

Se preferir falar com a equipe agora, responda "humano".';

        return ['dor_coleta', $novos, $mensagem];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function dorColeta(array $dados, string $texto, string $telefone = ''): array
    {
        $limpo = trim($texto);
        $normalizado = mb_strtolower($limpo);

        if (in_array($normalizado, ['pular', 'nao', 'não'], true)) {
            $novos = $this->base($dados);
            $novos['motivo_handoff'] = 'Paciente relatou dor/desconforto e preferiu não detalhar pelo WhatsApp.';

            return ['recepcao', $novos, $this->textoTransferenciaHumana()];
        }

        if (in_array($normalizado, ['humano', 'atendente', 'equipe'], true)) {
            $novos = $this->base($dados);
            $novos['motivo_handoff'] = 'Paciente com dor/desconforto solicitou atendimento humano.';

            return ['recepcao', $novos, $this->textoTransferenciaHumana()];
        }

        $dor = is_array($dados['dor'] ?? null) ? $dados['dor'] : [];

        if (! isset($dor['relato'])) {
            $dor['relato'] = mb_substr($limpo, 0, 500);
            $novos = $dados;
            $novos['dor'] = $dor;

            return ['dor_coleta', $novos, 'Obrigada por informar. A dor/desconforto é leve, moderada ou intensa? Se souber, me diga também se houve inchaço, sangramento ou febre. (Não é diagnóstico, é só para encaminhar melhor à equipe.)'];
        }

        $dor['intensidade_e_sinais'] = mb_substr($limpo, 0, 500);
        $resumo = 'Dor/desconforto relatada no WhatsApp';
        $partes = array_filter([
            $dor['mensagem_inicial'] ?? null,
            $dor['relato'] ?? null,
            $dor['intensidade_e_sinais'] ?? null,
        ], fn ($v) => is_string($v) && trim($v) !== '');
        if ($partes !== []) {
            $resumo .= ': '.mb_substr(implode(' | ', $partes), 0, 240);
        }

        $novos = $this->base($dados);
        $novos['motivo_handoff'] = $resumo;

        $fluxo = $this->configuracao->fluxoOdontologico() ?? [];
        $urgenciaCfg = is_array($fluxo['urgencia'] ?? null) ? $fluxo['urgencia'] : [];
        $aviso = (! empty($dor['grave']) && is_string($urgenciaCfg['mensagem_grave'] ?? null))
            ? '

'.(string) $urgenciaCfg['mensagem_grave']
            : '';

        return [
            'recepcao',
            $novos,
            'Recebi suas informações. Vou encaminhar para a equipe da clínica dar continuidade com segurança.'.$aviso.'

'.$this->textoTransferenciaHumana(),
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function iniciarFaq(array $dados, string $texto, string $prefixo = ''): array
    {
        $resposta = $this->respostaFaq($texto, $dados);
        if ($resposta !== null) {
            return ['menu', $this->base($dados), $prefixo.$resposta.'

'.$this->menu($dados['nome'] ?? null)];
        }

        $fluxo = $this->configuracao->fluxoOdontologico() ?? [];
        $obs = is_string($fluxo['observacao_padrao'] ?? null) ? (string) $fluxo['observacao_padrao'] : '';
        $msg = 'Posso informar sobre os procedimentos cadastrados e orientações gerais da clínica.

'
            .'Serviços disponíveis: consulta clínica/avaliação (30 min), restaurações (60 min, pode variar), profilaxia com ultrassom (60 min), instalação de implantes (30–90 min conforme planejamento) e clareamento (60 min).
';
        if ($obs !== '') {
            $msg .= "
{$obs}
";
        }
        $msg .= '
Se quiser saber sobre um procedimento específico, valores, horários ou outra dúvida, escreva sua pergunta. Para valores não informados, posso encaminhar à equipe.';

        return ['menu', $this->base($dados), $prefixo.$msg.'

'.$this->menu($dados['nome'] ?? null)];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function menuJaSouPaciente(array $dados, string $telefone = '', string $prefixo = ''): array
    {
        $pacienteId = $dados['paciente_id'] ?? null;
        if (! $pacienteId && $telefone !== '') {
            $paciente = $this->paciente($telefone);
            if ($paciente) {
                $dados = $this->base($dados + ['paciente_id' => $paciente->id, 'nome' => $paciente->nome]);
                $pacienteId = $paciente->id;
            }
        }

        if (! $pacienteId) {
            $dados['acao_pendente'] = 'ja_sou_paciente';

            return ['cadastro', $dados, 'Para localizar seu cadastro, qual é o seu nome completo?'];
        }

        $texto = $this->minhasConsultasTexto($dados);
        $texto .= '

1️⃣ Voltar ao menu
2️⃣ Agendar nova consulta
3️⃣ Cancelar uma consulta
4️⃣ Reagendar uma consulta
5️⃣ Falar com a equipe

Responda com o número da opção.';

        $novos = $this->base($dados);
        $novos['menu_ja_sou_paciente'] = true;

        return ['minhas_consultas', $novos, $prefixo.$texto];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function minhasConsultasOdontologico(array $dados, ?string $numero, ?int $opcao, string $telefone): array
    {
        $base = $this->base($dados);

        if (isset($dados['cancelamento'])) {
            return $this->cancelarConsultaEtapa($dados, $numero, $opcao, $telefone);
        }
        if (isset($dados['reagendamento'])) {
            return $this->reagendarConsultaEtapa($dados, $numero, $opcao);
        }

        if ($opcao === null) {
            if (! empty($dados['menu_ja_sou_paciente'])) {
                return $this->menuJaSouPaciente($base, $telefone);
            }

            return ['minhas_consultas', $base, $this->minhasConsultasTexto($base).'

Responda "menu" para voltar.'];
        }

        if (! empty($dados['menu_ja_sou_paciente'])) {
            return match ($opcao) {
                0 => ['menu', $base, $this->menu($dados['nome'] ?? null)],
                1 => $this->iniciarAgendamentoOdontologico($base),
                2 => $this->iniciarCancelamento($base, $telefone),
                3 => $this->iniciarReagendamento($base, $telefone),
                4 => (function () use ($base) {
                    $base['motivo_handoff'] = 'Paciente solicitou atendimento humano na área Já sou paciente.';

                    return ['recepcao', $base, $this->textoTransferenciaHumana()];
                })(),
                default => $this->menuJaSouPaciente($base, $telefone),
            };
        }

        if ($opcao === 0) {
            return ['menu', $base, $this->menu($dados['nome'] ?? null)];
        }
        if ($opcao === 1) {
            $base['motivo_handoff'] = 'Paciente pediu ajuda da equipe a partir de Minhas consultas.';

            return ['recepcao', $base, $this->textoTransferenciaHumana()];
        }

        return ['menu', $base, $this->menu($dados['nome'] ?? null)];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function comandoGerenciamentoOdontologico(string $comando, string $telefone, array $dados, ?string $nome): array
    {
        $base = $this->base($dados + ['nome' => $nome]);

        if ($comando === 'sair') {
            return ['encerrado', $base, 'Atendimento encerrado. Se precisar, é só me chamar novamente.'];
        }

        if ($comando === 'cancelar') {
            return $this->iniciarCancelamento($base, $telefone);
        }

        if ($comando === 'reagendar') {
            return $this->iniciarReagendamento($base, $telefone);
        }

        return ['menu', $base, $this->menu($nome)];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function iniciarCancelamento(array $dados, string $telefone): array
    {
        $consultas = $this->consultasFuturasDoPaciente($dados, $telefone);
        if ($consultas === []) {
            return ['menu', $this->base($dados), 'Você não tem consultas futuras que possam ser canceladas por aqui.

'.$this->menu($dados['nome'] ?? null)];
        }

        $lista = $this->formatarListaConsultas($consultas);
        $novos = $this->base($dados);
        $novos['cancelamento'] = [
            'consultas' => array_map(fn (Appointment $c) => $c->id, $consultas),
        ];

        return ['cancelar_consulta', $novos, "Qual consulta você deseja cancelar?

{$lista}

Responda com o número da consulta ou \"menu\" para voltar."];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function cancelarConsultaEtapa(array $dados, ?string $numero, ?int $opcao, string $telefone): array
    {
        $estado = is_array($dados['cancelamento'] ?? null) ? $dados['cancelamento'] : [];
        $ids = array_values(array_filter(array_map('intval', $estado['consultas'] ?? [])));
        if ($ids === []) {
            return $this->iniciarCancelamento($this->base($dados), $telefone);
        }

        $consultas = $this->consultasPorIdsDoPaciente($ids, $dados, $telefone);
        if ($consultas === []) {
            return ['menu', $this->base($dados), 'Não encontrei essa consulta para cancelamento.

'.$this->menu($dados['nome'] ?? null)];
        }

        if (! isset($estado['selecionada'])) {
            if ($opcao === null || ! isset($consultas[$opcao])) {
                $lista = $this->formatarListaConsultas($consultas);
                $novos = $this->base($dados);
                $novos['cancelamento'] = ['consultas' => array_map(fn (Appointment $c) => $c->id, $consultas)];

                return ['cancelar_consulta', $novos, "Opção inválida. Qual consulta deseja cancelar?

{$lista}

Responda com o número."];
            }

            $selecionada = $consultas[$opcao];
            $novos = $this->base($dados);
            $novos['cancelamento'] = [
                'consultas' => array_map(fn (Appointment $c) => $c->id, $consultas),
                'selecionada' => $selecionada->id,
            ];
            $desc = $this->descreverConsulta($selecionada);

            return ['cancelar_consulta', $novos, "Confirma o cancelamento desta consulta?

{$desc}

1️⃣ Sim, cancelar
2️⃣ Não, voltar ao menu"];
        }

        if ($opcao === 0) {
            $consulta = Appointment::query()->with('status')->find((int) $estado['selecionada']);
            $pacienteId = $dados['paciente_id'] ?? null;
            if (! $consulta || (int) $consulta->paciente_id !== (int) $pacienteId) {
                return ['menu', $this->base($dados), 'Não foi possível cancelar esta consulta com segurança.

'.$this->menu($dados['nome'] ?? null)];
            }

            if ($consulta->inicio?->isPast()) {
                return ['menu', $this->base($dados), 'Essa consulta não pode ser cancelada por aqui.

'.$this->menu($dados['nome'] ?? null)];
            }

            $statusCancelado = AppointmentStatus::idFor(AppointmentStatus::CANCELADO);
            if ((int) $consulta->appointment_status_id !== (int) $statusCancelado) {
                $antes = $consulta->only(['appointment_status_id']);
                $consulta->update(['appointment_status_id' => $statusCancelado]);
                AuditLogger::log('cancelar', 'appointments', $consulta->id, antes: $antes, depois: ['appointment_status_id' => $statusCancelado]);
            }

            $desc = $this->descreverConsulta($consulta->fresh());

            return ['menu', $this->base($dados), "Consulta cancelada com segurança.

{$desc}

Se quiser, posso ajudar a reagendar.

".$this->menu($dados['nome'] ?? null)];
        }

        return ['menu', $this->base($dados), $this->menu($dados['nome'] ?? null)];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function iniciarReagendamento(array $dados, string $telefone): array
    {
        $consultas = $this->consultasFuturasDoPaciente($dados, $telefone);
        if ($consultas === []) {
            return ['menu', $this->base($dados), 'Você não tem consultas futuras para reagendar por aqui.

'.$this->menu($dados['nome'] ?? null)];
        }

        $lista = $this->formatarListaConsultas($consultas);
        $novos = $this->base($dados);
        $novos['reagendamento'] = [
            'consultas' => array_map(fn (Appointment $c) => $c->id, $consultas),
        ];

        return ['reagendar_consulta', $novos, "Qual consulta você deseja reagendar?

{$lista}

Responda com o número da consulta ou \"menu\" para voltar."];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function reagendarConsultaEtapa(array $dados, ?string $numero, ?int $opcao): array
    {
        $estado = is_array($dados['reagendamento'] ?? null) ? $dados['reagendamento'] : [];
        $ids = array_values(array_filter(array_map('intval', $estado['consultas'] ?? [])));
        if ($ids === []) {
            return ['menu', $this->base($dados), $this->menu($dados['nome'] ?? null)];
        }

        $consultas = $this->consultasPorIdsDoPaciente($ids, $dados, $this->telefoneDeDados($dados));
        if ($consultas === []) {
            return ['menu', $this->base($dados), 'Não encontrei essa consulta para reagendamento.

'.$this->menu($dados['nome'] ?? null)];
        }

        if ($opcao === null || ! isset($consultas[$opcao])) {
            $lista = $this->formatarListaConsultas($consultas);
            $novos = $this->base($dados);
            $novos['reagendamento'] = ['consultas' => array_map(fn (Appointment $c) => $c->id, $consultas)];

            return ['reagendar_consulta', $novos, "Opção inválida. Qual consulta deseja reagendar?

{$lista}

Responda com o número."];
        }

        $antiga = $consultas[$opcao];
        $novos = $this->base($dados);
        $novos['reagendamento_origem_id'] = $antiga->id;

        $mensagem = 'Vamos reagendar esta consulta:

'.$this->descreverConsulta($antiga)
            .'

Ao final, a nova consulta será confirmada apenas após gravação bem-sucedida. A consulta anterior será cancelada com segurança após o novo agendamento.';

        $resultado = $this->iniciarAgendamentoOdontologico($novos);
        $resultado[2] = $mensagem.'

'.$resultado[2];

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $dados
     * @param  array<string, mixed>  $escolhido
     * @return array{0: string, 1: array<string, mixed>, 2: string}|null
     */
    protected function solicitarDuracaoImplanteSeNecessario(array &$dados, array $escolhido): ?array
    {
        $codigo = (string) ($escolhido['id'] ?? '');
        $nome = (string) ($escolhido['nome'] ?? '');
        $fluxo = $this->configuracao->fluxoOdontologico() ?? [];
        $procCfg = is_array($fluxo['procedimentos']['implante'] ?? null) ? $fluxo['procedimentos']['implante'] : [];
        $ehImplante = $codigo === 'IMPL_MALH'
            || str_contains(mb_strtolower($nome), 'implante')
            || (is_string($procCfg['nome'] ?? null) && mb_strtolower($procCfg['nome']) === mb_strtolower($nome));

        if (! $ehImplante) {
            return null;
        }

        $min = max(30, (int) ($procCfg['duracao_min_minutos'] ?? 30));
        $max = max($min, (int) ($procCfg['duracao_max_minutos'] ?? 90));
        $padrao = (int) ($procCfg['duracao_padrao_minutos'] ?? ($escolhido['duracao'] ?? 60));
        $opcoes = array_values(array_unique(array_filter([$min, $padrao, $max], fn ($v) => $v >= 30 && $v <= 90)));
        sort($opcoes);
        if ($opcoes === []) {
            $opcoes = [30, 60, 90];
        }

        $dados['duracao_implante_opcoes'] = $opcoes;
        $lista = collect($opcoes)->map(fn ($m, $i) => ($i + 1).". {$m} minutos")->implode('
');

        return [
            'duracao_implante',
            $dados,
            "Para instalação de implantes, a duração estimada pode variar conforme planejamento da equipe ({$min} a {$max} minutos).

Qual duração devemos considerar para este agendamento?

{$lista}

Responda com o número.",
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function duracaoImplante(array $dados, ?string $numero, ?int $opcao): array
    {
        $opcoes = $dados['duracao_implante_opcoes'] ?? [];
        $duracao = $this->itemEscolhido($opcoes, $numero, $opcao);

        if (! is_int($duracao) && ! ctype_digit((string) $duracao)) {
            return ['duracao_implante', $dados, $this->textoDuracaoImplante($dados)];
        }

        $duracao = (int) $duracao;
        if ($duracao < 30 || $duracao > 90) {
            return ['duracao_implante', $dados, $this->textoDuracaoImplante($dados)];
        }

        $agenda = $this->disponibilidade->horariosLivres(
            (int) ($dados['profissional_id'] ?? 0),
            $duracao,
            self::DIAS_DE_AGENDA,
        );

        $dias = $this->diasComHorarios($agenda);
        unset($dados['duracao_implante_opcoes']);

        if ($dias === []) {
            return [
                'menu',
                $this->base($dados),
                "Sem horários livres para {$dados['procedimento']} com {$dados['profissional']} nos próximos dias para {$duracao} minutos.

".$this->menu($dados['nome'] ?? null),
            ];
        }

        $lista = collect($dias)
            ->map(fn (array $dia, int $i) => ($i + 1).". {$dia['data']} ({$dia['dia_semana']}) — {$dia['quantidade']} horário(s)")
            ->implode('
');

        $dados['duracao'] = $duracao;
        $dados['dias'] = $dias;

        return [
            'data',
            $dados,
            "Escolha o dia para {$dados['procedimento']} ({$duracao} min):

{$lista}

Responda com o número.",
        ];
    }

    /**
     * Pré-anamnese gradual/opcional integrada a `anamneses`.
     *
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function preAnamnese(array $dados, string $texto, string $telefone = ''): array
    {
        $estado = is_array($dados['pre_anamnese'] ?? null) ? $dados['pre_anamnese'] : ['etapa' => 'intro', 'respostas' => []];
        $respostas = is_array($estado['respostas'] ?? null) ? $estado['respostas'] : [];
        $etapa = (string) ($estado['etapa'] ?? 'intro');
        $limpo = trim($texto);
        $norm = mb_strtolower($limpo);

        if (in_array($norm, ['pular', 'nao', 'não', 'depois', 'agora nao', 'agora não'], true) && $etapa === 'intro') {
            $novos = $this->base($dados);
            unset($novos['pre_anamnese']);

            return ['menu', $novos, 'Sem problemas, a pré-anamnese é opcional e pode ser respondida ao dentista na consulta.

'.$this->menu($dados['nome'] ?? null)];
        }

        if ($etapa === 'intro') {
            $estado['etapa'] = 'doencas';
            $novos = $dados;
            $novos['pre_anamnese'] = $estado;
            $opcoes = collect(AnamnesePreAtendimento::DOENCAS)->map(fn ($label, $key) => "- {$label}")->implode('
');

            return ['pre_anamnese', $novos, "Pré-anamnese (opcional, sem diagnóstico). Você pode responder \"pular\" a qualquer momento.

Você possui alguma doença sistêmica? Se sim, cite quais (ex.: hipertensão, diabetes, cardiopatia, renal, alterações de coagulação, outras). Se não, responda \"não\".

{$opcoes}"];
        }

        if ($etapa === 'doencas') {
            if ($norm === 'pular') {
                return $this->concluirPreAnamnese($dados, $respostas, true);
            }
            if ($norm === 'nao' || $norm === 'não' || $norm === 'nenhuma') {
                $respostas['doencas'] = [];
            } else {
                $respostas['doencas'] = $this->mapearDoencasTexto($limpo);
                if (str_contains($norm, 'outr')) {
                    $respostas['outras_doencas'] = $limpo;
                    $respostas['doencas'][] = 'outras';
                    $respostas['doencas'] = array_values(array_unique($respostas['doencas']));
                }
            }
            $estado['etapa'] = 'usa_medicamentos';
            $estado['respostas'] = $respostas;
            $novos = $dados;
            $novos['pre_anamnese'] = $estado;

            return ['pre_anamnese', $novos, 'Faz uso de algum medicamento atualmente? Responda \"sim\" e qual(is), ou \"não\".'];
        }

        if ($etapa === 'usa_medicamentos') {
            if ($norm === 'pular') {
                return $this->concluirPreAnamnese($dados, $respostas, true);
            }
            if ($norm === 'nao' || $norm === 'não') {
                $respostas['usa_medicamentos'] = AnamnesePreAtendimento::NAO;
                $respostas['medicamentos'] = '';
            } else {
                $respostas['usa_medicamentos'] = AnamnesePreAtendimento::SIM;
                $respostas['medicamentos'] = preg_replace('/^sim[:,\s-]*/iu', '', $limpo) ?: $limpo;
            }
            $estado['etapa'] = 'dores_tonturas';
            $estado['respostas'] = $respostas;
            $novos = $dados;
            $novos['pre_anamnese'] = $estado;

            return ['pre_anamnese', $novos, 'Costuma sentir dores de cabeça ou tonturas? Responda \"sim\" e descreva, ou \"não\".'];
        }

        if ($etapa === 'dores_tonturas') {
            if ($norm === 'pular') {
                return $this->concluirPreAnamnese($dados, $respostas, true);
            }
            if ($norm === 'nao' || $norm === 'não') {
                $respostas['dores_tonturas'] = AnamnesePreAtendimento::NAO;
                $respostas['dores_tonturas_detalhes'] = '';
            } else {
                $respostas['dores_tonturas'] = AnamnesePreAtendimento::SIM;
                $respostas['dores_tonturas_detalhes'] = preg_replace('/^sim[:,\s-]*/iu', '', $limpo) ?: $limpo;
            }
            $estado['etapa'] = 'alergias';
            $estado['respostas'] = $respostas;
            $novos = $dados;
            $novos['pre_anamnese'] = $estado;

            return ['pre_anamnese', $novos, 'Possui alguma alergia a medicamentos ou materiais odontológicos? Responda \"sim\" e qual(is), ou \"não\".'];
        }

        if ($etapa === 'alergias') {
            if ($norm === 'pular') {
                return $this->concluirPreAnamnese($dados, $respostas, true);
            }
            if ($norm === 'nao' || $norm === 'não') {
                $respostas['alergias'] = AnamnesePreAtendimento::NAO;
                $respostas['alergias_detalhes'] = '';
            } else {
                $respostas['alergias'] = AnamnesePreAtendimento::SIM;
                $respostas['alergias_detalhes'] = preg_replace('/^sim[:,\s-]*/iu', '', $limpo) ?: $limpo;
            }
            $estado['etapa'] = 'tratamento_medico';
            $estado['respostas'] = $respostas;
            $novos = $dados;
            $novos['pre_anamnese'] = $estado;

            return ['pre_anamnese', $novos, 'Está realizando algum tratamento médico atualmente? Responda \"sim\" e qual(is), ou \"não\".'];
        }

        if ($etapa === 'tratamento_medico') {
            if ($norm === 'pular') {
                return $this->concluirPreAnamnese($dados, $respostas, true);
            }
            if ($norm === 'nao' || $norm === 'não') {
                $respostas['tratamento_medico'] = AnamnesePreAtendimento::NAO;
                $respostas['tratamento_medico_detalhes'] = '';
            } else {
                $respostas['tratamento_medico'] = AnamnesePreAtendimento::SIM;
                $respostas['tratamento_medico_detalhes'] = preg_replace('/^sim[:,\s-]*/iu', '', $limpo) ?: $limpo;
            }

            return $this->concluirPreAnamnese($dados, $respostas, false);
        }

        $estado['etapa'] = 'doencas';
        $novos = $dados;
        $novos['pre_anamnese'] = $estado;

        return ['pre_anamnese', $novos, $this->textoPreAnamnese($novos)];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @param  array<string, mixed>  $respostas
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    protected function concluirPreAnamnese(array $dados, array $respostas, bool $parcial): array
    {
        $novos = $this->base($dados);
        unset($novos['pre_anamnese']);

        $respostas = array_merge([
            'doencas' => [],
            'outras_doencas' => '',
            'usa_medicamentos' => AnamnesePreAtendimento::NAO,
            'medicamentos' => '',
            'dores_tonturas' => AnamnesePreAtendimento::NAO,
            'dores_tonturas_detalhes' => '',
            'alergias' => AnamnesePreAtendimento::NAO,
            'alergias_detalhes' => '',
            'tratamento_medico' => AnamnesePreAtendimento::NAO,
            'tratamento_medico_detalhes' => '',
        ], $respostas);

        foreach (['usa_medicamentos', 'dores_tonturas', 'alergias', 'tratamento_medico'] as $campo) {
            if (! in_array($respostas[$campo] ?? null, [AnamnesePreAtendimento::SIM, AnamnesePreAtendimento::NAO], true)) {
                $respostas[$campo] = AnamnesePreAtendimento::NAO;
            }
        }

        $pacienteId = $dados['paciente_id'] ?? null;
        if ($pacienteId) {
            try {
                $anamnese = Anamnese::create([
                    'paciente_id' => (int) $pacienteId,
                    'titulo' => 'Pré-anamnese (WhatsApp)',
                    'responsavel' => $dados['nome'] ?? null,
                    'respostas' => AnamnesePreAtendimento::normalizar($respostas),
                ]);
                AuditLogger::log('registrar_anamnese', 'anamneses', $anamnese->id, depois: [
                    'anamnese_id' => $anamnese->id,
                    'titulo' => $anamnese->titulo,
                    'origem' => 'whatsapp',
                ]);
            } catch (\Throwable) {
            }
        }

        $msg = $parcial
            ? 'Pré-anamnese encerrada. As informações fornecidas serão avaliadas pelo profissional responsável durante a consulta.'
            : 'Obrigada pelas informações. Elas serão avaliadas pelo profissional responsável durante a consulta.';

        return ['menu', $novos, $msg.'

'.$this->menu($dados['nome'] ?? null)];
    }

    protected function mapearDoencasTexto(string $texto): array
    {
        $norm = mb_strtolower($texto);
        $mapa = [
            'hipertens' => 'hipertensao',
            'press' => 'hipertensao',
            'cardio' => 'cardiopatia',
            'cora' => 'cardiopatia',
            'diabet' => 'diabetes',
            'renal' => 'renal',
            'rim' => 'renal',
            'coagula' => 'coagulacao',
            'sangue' => 'coagulacao',
        ];
        $achadas = [];
        foreach ($mapa as $termo => $chave) {
            if (str_contains($norm, $termo)) {
                $achadas[] = $chave;
            }
        }
        if ($achadas === [] && ! in_array($norm, ['nao', 'não', 'nenhuma'], true)) {
            $achadas[] = 'outras';
        }

        return array_values(array_unique($achadas));
    }

    /**
     * @return array{grave: bool}|null
     */
    protected function detectarUrgencia(string $texto): ?array
    {
        $fluxo = $this->configuracao->fluxoOdontologico() ?? [];
        $urgencia = is_array($fluxo['urgencia'] ?? null) ? $fluxo['urgencia'] : [];
        $sinais = is_array($urgencia['sinais'] ?? null) ? $urgencia['sinais'] : ['dor intensa', 'inchaço', 'sangramento', 'trauma', 'febre'];
        $norm = mb_strtolower($texto);

        foreach ($sinais as $sinal) {
            if (! is_string($sinal) || $sinal === '') {
                continue;
            }
            if (str_contains($norm, mb_strtolower($sinal))) {
                $grave = preg_match('/sangramento|febre|trauma|respirar|engolir|abrir a boca|incha[cç]o facial|dor intensa/u', $norm) === 1;

                return ['grave' => $grave];
            }
        }

        if (preg_match('/(dor|doendo|desconforto|urg[eê]ncia|urgente|inchado|incha[cç]o)/u', $norm) === 1) {
            $grave = preg_match('/intensa|muita dor|sangr|febre|trauma|respirar|engolir/u', $norm) === 1;

            return ['grave' => $grave];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    protected function respostaFaq(string $texto, array $dados): ?string
    {
        $fluxo = $this->configuracao->fluxoOdontologico() ?? [];
        $faq = is_array($fluxo['faq'] ?? null) ? $fluxo['faq'] : [];
        $norm = mb_strtolower($texto);

        if (preg_match('/(pre[cç]o|valor|quanto custa|custa|conv[eê]nio)/u', $norm) === 1) {
            $preco = $this->configuracao->texto(ConfiguracaoWhatsApp::PRECO_NAO_INFORMADO);
            if (is_string($faq['preco'] ?? null) && trim((string) $faq['preco']) !== '') {
                return (string) $faq['preco'];
            }
            if (is_string($preco) && trim($preco) !== '') {
                return $preco;
            }

            return 'Para valores, nossa equipe pode informar conforme o procedimento e a avaliação necessária. Se quiser, posso encaminhar sua mensagem para a equipe.';
        }

        $mapa = [
            'consulta_tempo' => '/consulta.*(tempo|demora|dura|dura[cç][aã]o|minut)/u',
            'clareamento' => '/clareamento/u',
            'implante_tempo' => '/implante.*(tempo|demora|dura|dura[cç][aã]o|minut)/u',
            'profilaxia_ultrassom' => '/profilaxia|limpeza|ultrassom/u',
            'radiografia' => '/raio|radiograf/u',
            'foto' => '/foto|imagem/u',
        ];

        foreach ($mapa as $chave => $regex) {
            if (preg_match($regex, $norm) === 1 && is_string($faq[$chave] ?? null) && trim((string) $faq[$chave]) !== '') {
                $resp = (string) $faq[$chave];
                $obs = is_string($fluxo['observacao_padrao'] ?? null) ? (string) $fluxo['observacao_padrao'] : '';
                if ($obs !== '' && ! str_contains($resp, 'avaliação clínica')) {
                    $resp .= "

{$obs}";
                }

                return $resp;
            }
        }

        if (preg_match('/(endere[cç]o|onde fica|local|localiza[cç][aã]o)/u', $norm) === 1) {
            $end = $this->enderecoConfirmacao();
            if ($end) {
                return "Nosso endereço é: {$end}.";
            }

            return 'Posso encaminhar sua solicitação para a equipe informar o endereço e detalhes de atendimento.';
        }

        if (preg_match('/(hor[aá]rio|funcionamento|abre|fecha)/u', $norm) === 1) {
            return 'Para horários de funcionamento e disponibilidade, posso encaminhar sua mensagem para a equipe confirmar com segurança.';
        }

        return null;
    }

    protected function pediuHumanoTextoLivre(string $texto): bool
    {
        $norm = mb_strtolower($texto);

        return preg_match('/(humano|atendente|pessoa|recep[cç][aã]o|falar com algu[eé]m|equipe)/u', $norm) === 1;
    }

    protected function textoTransferenciaHumana(): string
    {
        $fluxo = $this->configuracao->fluxoOdontologico() ?? [];
        $msg = is_string($fluxo['transferencia'] ?? null) ? trim((string) $fluxo['transferencia']) : '';

        return $msg !== '' ? $msg : 'Claro! Vou encaminhar sua conversa para a equipe da clínica. Assim que possível, alguém dará continuidade ao atendimento por aqui.';
    }

    protected function enderecoConfirmacao(): ?string
    {
        $config = $this->configuracao->texto(ConfiguracaoWhatsApp::ENDERECO_CONFIRMACAO);
        if (is_string($config) && trim($config) !== '') {
            return trim($config);
        }

        $perfil = ClinicProfile::current();
        if (! $perfil) {
            return null;
        }

        $partes = array_filter([
            trim(implode(', ', array_filter([$perfil->address ?? null, $perfil->number ?? null]))),
            $perfil->complement ?? null,
            $perfil->district ?? null,
            trim(implode(' - ', array_filter([$perfil->city ?? null, $perfil->state ?? null]))),
            isset($perfil->postal_code) && $perfil->postal_code ? 'CEP '.$perfil->postal_code : null,
        ], fn ($v) => is_string($v) && trim($v) !== '');

        $endereco = implode(' - ', $partes);

        return $endereco !== '' ? $endereco : null;
    }

    protected function personalizarTextoOdontologico(string $texto, ?string $nome): string
    {
        return $texto;
    }

    protected function telefoneDeDados(array $dados): string
    {
        return '';
    }

    protected function textoDorColeta(array $dados): string
    {
        $fluxo = $this->configuracao->fluxoOdontologico() ?? [];
        $urgencia = is_array($fluxo['urgencia'] ?? null) ? $fluxo['urgencia'] : [];

        return is_string($urgencia['mensagem'] ?? null) && trim((string) $urgencia['mensagem']) !== ''
            ? (string) $urgencia['mensagem']
            : 'Para direcionar seu atendimento, me informe onde sente dor/desconforto e há quanto tempo começou.';
    }

    protected function textoDuracaoImplante(array $dados): string
    {
        $opcoes = $dados['duracao_implante_opcoes'] ?? [30, 60, 90];
        $lista = collect($opcoes)->map(fn ($m, $i) => ($i + 1).". {$m} minutos")->implode('
');

        return "Escolha a duração estimada para a instalação de implantes (30 a 90 minutos, conforme planejamento):

{$lista}

Responda com o número.";
    }

    protected function textoPreAnamnese(array $dados): string
    {
        $estado = is_array($dados['pre_anamnese'] ?? null) ? $dados['pre_anamnese'] : [];
        $etapa = $estado['etapa'] ?? 'intro';

        return match ($etapa) {
            'doencas' => 'Você possui alguma doença sistêmica? Cite quais ou responda \"não\". (Pode responder \"pular\")',
            'usa_medicamentos' => 'Faz uso de algum medicamento atualmente? Responda \"sim\" e qual(is), ou \"não\".',
            'dores_tonturas' => 'Costuma sentir dores de cabeça ou tonturas? Responda \"sim\" e descreva, ou \"não\".',
            'alergias' => 'Possui alergia a medicamentos ou materiais odontológicos? Responda \"sim\" e qual(is), ou \"não\".',
            'tratamento_medico' => 'Está realizando algum tratamento médico atualmente? Responda \"sim\" e qual(is), ou \"não\".',
            default => 'Gostaria de responder uma pré-anamnese rápida e opcional? Ela será avaliada pelo profissional na consulta. Responda \"sim\" para iniciar ou \"pular\".',
        };
    }

    protected function textoCancelarConsulta(array $dados): string
    {
        $ids = $dados['cancelamento']['consultas'] ?? [];
        $consultas = $this->consultasPorIdsDoPaciente(array_map('intval', is_array($ids) ? $ids : []), $dados, $this->telefoneDeDados($dados));
        $lista = $this->formatarListaConsultas($consultas);

        return "Qual consulta deseja cancelar?

{$lista}

Responda com o número.";
    }

    protected function textoReagendarConsulta(array $dados): string
    {
        $ids = $dados['reagendamento']['consultas'] ?? [];
        $consultas = $this->consultasPorIdsDoPaciente(array_map('intval', is_array($ids) ? $ids : []), $dados, $this->telefoneDeDados($dados));
        $lista = $this->formatarListaConsultas($consultas);

        return "Qual consulta deseja reagendar?

{$lista}

Responda com o número.";
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return list<Appointment>
     */
    protected function consultasFuturasDoPaciente(array $dados, string $telefone): array
    {
        $pacienteId = $dados['paciente_id'] ?? null;
        if (! $pacienteId && $telefone !== '') {
            $paciente = $this->paciente($telefone);
            $pacienteId = $paciente?->id;
        }
        if (! $pacienteId) {
            return [];
        }

        $statusCancelado = AppointmentStatus::idFor(AppointmentStatus::CANCELADO);

        return Appointment::with(['profissional', 'status'])
            ->where('paciente_id', (int) $pacienteId)
            ->where('inicio', '>=', now())
            ->when($statusCancelado, fn ($q) => $q->where('appointment_status_id', '!=', $statusCancelado))
            ->orderBy('inicio')
            ->limit(10)
            ->get()
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $dados
     * @return list<Appointment>
     */
    protected function consultasPorIdsDoPaciente(array $ids, array $dados, string $telefone): array
    {
        $futuras = $this->consultasFuturasDoPaciente($dados, $telefone);
        if ($ids === []) {
            return $futuras;
        }
        $permitidas = array_flip($ids);

        return array_values(array_filter($futuras, fn (Appointment $c) => isset($permitidas[$c->id])));
    }

    /**
     * @param  list<Appointment>  $consultas
     */
    protected function formatarListaConsultas(array $consultas): string
    {
        $linhas = [];
        foreach (array_values($consultas) as $i => $consulta) {
            $linhas[] = ($i + 1).'. '.$this->descreverConsulta($consulta);
        }

        return $linhas === [] ? 'Nenhuma consulta encontrada.' : implode('
', $linhas);
    }

    protected function descreverConsulta(Appointment $consulta): string
    {
        $inicio = $consulta->inicio?->format('d/m/Y H:i') ?? 'data a confirmar';
        $prof = $consulta->profissional?->nome ?? 'profissional';
        $servico = $consulta->servico ?: ($consulta->titulo ?: 'atendimento');
        $status = $consulta->status?->label ?? $consulta->status?->name ?? '';
        $sufixo = $status !== '' ? " ({$status})" : '';

        return "{$inicio} — {$servico} com {$prof}{$sufixo}";
    }

    /**
     * Conclusão pós-atendimento: oferece orientação/encaminhamento quando o paciente
     * retorna após ter sido atendido (sem diagnóstico ou prescrição).
     *
     * @param  array<string, mixed>  $dados
     * @return array{0: string, 1: array<string, mixed>, 2: string}|null
     */
    protected function posAtendimentoSeAplicavel(array $dados, string $telefone): ?array
    {
        $pacienteId = $dados['paciente_id'] ?? null;
        if (! $pacienteId && $telefone !== '') {
            $pacienteId = $this->paciente($telefone)?->id;
        }
        if (! $pacienteId) {
            return null;
        }

        $statusAtendido = AppointmentStatus::idFor('atendido');
        if (! $statusAtendido) {
            return null;
        }

        $recente = Appointment::query()
            ->where('paciente_id', (int) $pacienteId)
            ->where('appointment_status_id', $statusAtendido)
            ->where('inicio', '>=', now()->subDays(7))
            ->where('inicio', '<=', now())
            ->orderByDesc('inicio')
            ->first();

        if (! $recente) {
            return null;
        }

        $fluxo = $this->configuracao->fluxoOdontologico() ?? [];
        $template = is_string($fluxo['pos_atendimento'] ?? null) ? (string) $fluxo['pos_atendimento'] : null;
        $nome = $dados['nome'] ?? 'Paciente';
        $texto = $template
            ? strtr($template, ['{nome}' => (string) $nome])
            : "Olá, {$nome}! Se tiver alguma dúvida sobre orientações recebidas no seu atendimento recente, posso encaminhar sua mensagem para a equipe. Não faço diagnóstico nem prescrição por aqui.";

        if (mb_stripos($texto, 'orienta') === false) {
            $texto .= ' Se precisar de orientações ou tiver dúvidas sobre o atendimento, posso encaminhar sua mensagem para a equipe.';
        }

        $base = $this->base($dados);
        $base['pos_atendimento_oferecido'] = $recente->id;

        return ['menu', $base, $texto.'

'.$this->menu($dados['nome'] ?? null)];
    }

    protected function textoFaqIntro(): string
    {
        $fluxo = $this->configuracao->fluxoOdontologico() ?? [];
        $obs = is_string($fluxo['observacao_padrao'] ?? null) ? (string) $fluxo['observacao_padrao'] : '';

        $msg = 'Posso informar sobre os procedimentos e orientações gerais da clínica.

'
            .'Serviços: consulta clínica/avaliação (30 min), restaurações (60 min, pode variar), profilaxia com ultrassom (60 min), instalação de implantes (30–90 min conforme planejamento) e clareamento (60 min).
';
        if ($obs !== '') {
            $msg .= "
{$obs}
";
        }
        $msg .= '
Escreva sua dúvida (ex.: quanto custa, onde fica, horários) ou responda "menu" para voltar.';

        return $msg;
    }
}
