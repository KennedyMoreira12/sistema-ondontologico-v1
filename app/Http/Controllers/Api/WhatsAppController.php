<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\EvolutionIndisponivel;
use App\Exceptions\HorarioOcupadoException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AssignCorrelationId;
use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Central\Clinic;
use App\Models\ClinicProfile;
use App\Models\Paciente;
use App\Models\Procedure;
use App\Models\Professional;
use App\Models\WhatsAppAlert;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppNote;
use App\Models\WhatsAppOutboxMessage;
use App\Models\WhatsAppQuickReply;
use App\Services\Agenda\AgendamentoService;
use App\Services\Agenda\DisponibilidadeService;
use App\Services\AuditLogger;
use App\Services\EvolutionService;
use App\Services\Tenant\TenantManager;
use App\Services\WhatsApp\AlertaRecepcao;
use App\Services\WhatsApp\AtendimentoIA;
use App\Services\WhatsApp\ClinicFeatures;
use App\Services\WhatsApp\FluxoAtendimento;
use App\Services\WhatsApp\MenuInterativo;
use App\Services\WhatsApp\NotasInternas;
use App\Services\WhatsApp\Outbox;
use App\Services\WhatsApp\RespostasRapidas;
use App\Services\WhatsApp\Triagem;
use App\Services\WhatsApp\WhatsAppHealth;
use App\Support\WhatsAppDeliveryStatus;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * API interna consumida pelo n8n de cada clínica.
 *
 * Não existe "a clínica" aqui: a clínica é a do token que o middleware
 * autentica, e o banco conectado é o dela. Nenhum método aceita id de clínica
 * por parâmetro, porque isso permitiria ao n8n de uma clínica escrever no banco
 * de outra.
 */
class WhatsAppController extends Controller
{
    public function __construct(
        protected TenantManager $tenants,
        protected EvolutionService $evolution,
        protected DisponibilidadeService $disponibilidadeService,
        protected AgendamentoService $agendamentoService,
        protected FluxoAtendimento $fluxo,
        protected AtendimentoIA $ia,
        protected ClinicFeatures $features,
        protected Outbox $outbox,
        protected WhatsAppHealth $health,
        protected Triagem $triagem,
        protected AlertaRecepcao $alertas,
        protected NotasInternas $notas,
        protected RespostasRapidas $respostasRapidas,
        protected MenuInterativo $menus,
    ) {}

    public function info(): JsonResponse
    {
        $clinica = $this->tenants->current();

        return response()->json([
            'clinica' => [
                'id' => $this->tenants->id(),
                'nome' => $clinica?->name,
                'perfil' => ClinicProfile::current(),

                /**
                 * O n8n descobre aqui se ele pode responder por esta clínica. Sem
                 * `evolution_instance` a Evolution não tem por onde entregar a
                 * mensagem e sem `evolution_token` o Laravel não consegue chamar
                 * de volta — e o bot falando "não consigo responder" para o
                 * paciente é pior do que o n8n saber que a integração falta.
                 */
                'evolution_instance' => $clinica?->evolution_instance,
                'evolution_pronta' => $clinica?->hasEvolutionIntegration() ?? false,
            ],
            'statuses' => AppointmentStatus::orderBy('id')->get(),
        ]);
    }

    /**
     * Envia texto pelo WhatsApp da clínica.
     *
     * A clínica é a do token, nunca uma do corpo: o middleware já conectou no
     * banco dela e a Evolution instance/token vêm do registro central. Um
     * parâmetro `clinica_id` aqui deixaria o n8n de uma clínica escrever na
     * instância de outra.
     *
     * A Evolution responde 200 mesmo quando não conseguiu enviar, com `error` no
     * corpo. Devolver esse array como sucesso faria o n8n marcar a mensagem como
     * entregue e o paciente nunca receberia nada — por isso o `error` vira 502
     * aqui, que é o status que faz o n8n tentar de novo.
     */
    public function enviarMensagem(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'telefone' => ['required', 'string', 'max:20'],
            'texto' => ['required', 'string', 'max:4096'],
            // Opcional: um reenvio do n8n com a mesma chave não gera segunda
            // mensagem. Sem ela, cada chamada é uma mensagem nova, como antes.
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ])->validate();

        $clinica = $this->tenants->require();

        $correlationId = $request->attributes->get(AssignCorrelationId::ATTRIBUTE);

        /**
         * Com a flag desligada, o caminho é o de sempre: chamada direta e
         * resposta síncrona. Ligar a outbox é uma decisão por clínica, e o
         * comportamento antigo precisa continuar intacto até essa decisão.
         */
        if ($this->features->enabled($clinica, 'whatsapp_outbox')) {
            return $this->enviarPelaOutbox(
                $clinica,
                $validated,
                is_string($correlationId) ? $correlationId : null,
            );
        }

        try {
            $resposta = $this->evolution->sendText(
                $clinica,
                $validated['telefone'],
                $validated['texto'],
            );
        } catch (EvolutionIndisponivel $exception) {
            // 503 e não 500: a clínica não tem integração configurada, e isso se
            // resolve no painel, não é defeito do sistema.
            return response()->json(['message' => $exception->getMessage()], 503);
        } catch (RequestException $exception) {
            Log::error('A Evolution API recusou o envio de mensagem.', [
                'clinica' => $clinica->id,
                'status' => $exception->response?->status(),
            ]);

            return response()->json(['message' => 'A Evolution API recusou o envio.'], 502);
        }

        if (isset($resposta['error'])) {
            return response()->json([
                'message' => 'A Evolution API não conseguiu enviar a mensagem.',
                'erro' => $resposta['error'],
            ], 502);
        }

        $this->registrarMensagemEnviada(
            $clinica,
            $validated['telefone'],
            $validated['texto'],
            $resposta,
            is_string($correlationId) ? $correlationId : null,
        );

        return response()->json([
            'status' => 'sent',
            'message' => $resposta,
        ], 200);
    }

    /**
     * Envia pela outbox, que é a única via quando a flag está ligada.
     *
     * Com `QUEUE_CONNECTION=sync` o job roda aqui mesmo e a resposta já sai
     * `sent` ou com a falha. Com worker, a linha fica `pending` e a resposta é
     * 202: o n8n sabe que a mensagem foi aceita para envio, não que já saiu.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function enviarPelaOutbox(Clinic $clinica, array $validated, ?string $correlationId): JsonResponse
    {
        $registro = $this->outbox->enqueue(
            $clinica,
            $validated['telefone'],
            $validated['texto'],
            $validated['idempotency_key'] ?? null,
            $correlationId,
        );

        if ($registro->wasRecentlyCreated) {
            $this->outbox->dispatch($clinica, $registro);
            $registro->refresh();
        }

        return $this->respostaDaOutbox($registro);
    }

    protected function respostaDaOutbox(WhatsAppOutboxMessage $registro): JsonResponse
    {
        $comum = [
            'outbox_id' => $registro->id,
            'message_key' => $registro->message_key,
            'tentativas' => $registro->attempts,
        ];

        if ($registro->status === WhatsAppOutboxMessage::SENT) {
            return response()->json([
                'status' => 'sent',
                'provider_message_id' => $registro->provider_message_id,
            ] + $comum, 200);
        }

        if (in_array($registro->status, [
            WhatsAppOutboxMessage::PENDING,
            WhatsAppOutboxMessage::PROCESSING,
        ], true)) {
            return response()->json(['status' => 'queued'] + $comum, 202);
        }

        // DLQ. A integração ausente é configuração pendente no painel (503);
        // recusa da Evolution e entrega incerta são falha de envio (502).
        $status = $registro->last_error_code === 'sem_integracao' ? 503 : 502;

        return response()->json([
            'status' => 'failed',
            'message' => $status === 503
                ? (string) $registro->last_error
                : 'A Evolution API não conseguiu enviar a mensagem.',
            'erro' => $registro->last_error,
        ] + $comum, $status);
    }

    /**
     * Diagnóstico da conexão e da outbox da clínica.
     *
     * Não envia mensagem e não depende da Evolution: `check_evolution=1` é o
     * único caminho que faz uma chamada externa, sob pedido explícito.
     */
    public function saude(Request $request): JsonResponse
    {
        $clinica = $this->tenants->require();

        return response()->json([
            'whatsapp' => $this->health->snapshot($clinica, $request->boolean('check_evolution')),
            'flags' => $this->features->all($clinica),
        ]);
    }

    /**
     * Guarda no histórico da clínica a mensagem que saiu.
     *
     * Registrar aqui e não esperar o `SEND_MESSAGE` do webhook é deliberado: o
     * webhook chega depois, e um n8n que caiu no meio do caminho deixaria a
     * resposta da clínica fora do histórico. A linha é gravada com o `key.id` que
     * a Evolution devolveu, que é o mesmo que vem no webhook — então, quando ele
     * chegar, o índice único absorve em vez de duplicar.
     *
     * Sem `key.id` na resposta cai numa chave local, e aí a linha duplicada é o
     * preço aceito: melhor dois registros do mesmo texto do que a resposta da
     * clínica sumir do histórico.
     *
     * @param  array<string, mixed>  $resposta
     */
    protected function registrarMensagemEnviada(
        Clinic $clinica,
        string $telefone,
        string $texto,
        array $resposta,
        ?string $correlationId = null,
    ): void {
        $this->outbox->registrarHistorico(
            $clinica,
            $telefone,
            $this->jidDoWhatsapp($telefone),
            $texto,
            $resposta,
            $this->chaveDaEvolucao($resposta) ?? $this->chaveLocal($clinica, $telefone, $texto),
            $correlationId,
            WhatsAppDeliveryStatus::SENT,
        );
    }

    /**
     * O `remote_jid` no MESMO formato que o webhook grava.
     *
     * O webhook grava o JID que o WhatsApp manda (`5511…@s.whatsapp.net`), e
     * guardar aqui só os dígitos deixaria a mesma conversa com dois formatos de
     * `remote_jid` — o `telefone()` continuaria funcionando, mas qualquer
     * consulta por `remote_jid` perderia metade do histórico sem erro nenhum.
     */
    protected function jidDoWhatsapp(string $telefone): string
    {
        return $this->normalizarTelefone($telefone).'@s.whatsapp.net';
    }

    /**
     * @param  array<string, mixed>  $resposta
     */
    protected function chaveDaEvolucao(array $resposta): ?string
    {
        $chave = $resposta['key']['id'] ?? null;

        return is_string($chave) && $chave !== '' ? $chave : null;
    }

    /**
     * Chave provisória para quando a Evolution não devolveu `key.id`.
     *
     * O prefixo `local` não existe no formato de id do WhatsApp, então a chave
     * jamais colide com a que o webhook traz.
     */
    protected function chaveLocal(Clinic $clinica, string $telefone, string $texto): string
    {
        return 'local-'.substr(
            hash('sha256', $clinica->id.'|'.$this->normalizarTelefone($telefone).'|'.$texto.'|'.microtime(true)),
            0,
            64,
        );
    }

    public function profissionais(): JsonResponse
    {
        return response()->json(
            Professional::with('specialties')->where('is_active', true)->orderBy('nome')->get()
        );
    }

    /**
     * Procedimentos que o bot pode oferecer, com a duração que vai virar o tamanho
     * da consulta.
     *
     * A duração mora aqui, em `procedures.duracao_minutos`, e não no bot: é o
     * mesmo número que a recepção vê na tela e que o agenda cobra. Se o n8n
     * decidisse o tamanho da consulta, trocar a duração do procedimento deixaria
     * o WhatsApp marcando encaixes que a clínica não atende mais.
     *
     * Só os ativos saem: oferecer um procedimento inativo é oferecer um
     * horário que ninguém vai atender.
     */
    public function procedimentos(): JsonResponse
    {
        $procedimentos = Procedure::where('is_active', true)
            ->orderBy('nome')
            ->get(['id', 'codigo', 'nome', 'categoria', 'duracao_minutos']);

        return response()->json(['procedimentos' => $procedimentos]);
    }

    public function paciente(Request $request): JsonResponse
    {
        $telefone = $this->normalizarTelefone($request->input('telefone') ?? $request->input('phone'));

        $paciente = $telefone ? Paciente::where('celular', 'like', '%'.substr($telefone, -9))->first() : null;

        return response()->json(['paciente' => $paciente]);
    }

    public function criarPaciente(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'nome' => ['required', 'string', 'max:255'],
            'telefone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:191'],
        ])->validate();

        $telefone = $this->normalizarTelefone($validated['telefone']);

        $paciente = $telefone
            ? Paciente::where('celular', 'like', '%'.substr($telefone, -9))->first()
            : null;

        if ($paciente === null) {
            $paciente = Paciente::create([
                'clinica_id' => $this->tenants->id(),
                'nome' => $validated['nome'],
                'celular' => $telefone,
                'email' => $validated['email'] ?? null,
                'status' => 'ativo',
            ]);

            AuditLogger::log('criar', 'pacientes', $paciente->id, depois: $paciente->only(['nome', 'celular']));

            return response()->json(['paciente' => $paciente], 201);
        }

        return response()->json(['paciente' => $paciente]);
    }

    /**
     * Horários livres, com o tamanho da consulta vindo do procedimento escolhido.
     *
     * A duração é o que diferencia este método do anterior: um procedimento de 90
     * minutos não cabe em qualquer buraco de 30, e oferecer os mesmos horários
     * para um clareamento e para uma radiografia é oferecer consulta que não cabe
     * no turno. Por isso o passo entre horários é a própria duração — oferecer
     * 08:00 e 08:15 para algo de 90 min seria oferecer duas coisas que não podem
     * acontecer ao mesmo tempo.
     *
     * São dois filtros de horário, ambos necessários:
     *
     * 1. O slot precisa CABER no turno (`slot + duracao <= fim`). Sem isso, um
     *    procedimento de 2 h oferecido às 17 h de um turno que fecha às 18 h
     *    passa, e o agendamento terminaria depois do fim do atendimento.
     * 2. O slot precisa CABER livre no meio das consultas: basta o início não
     *    bater com o começo de nada, senão 09:45-10:15 aparece como livre numa
     *    consulta que ocupa 09:30-10:30.
     */
    public function disponibilidade(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'dias' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'profissional_id' => ['sometimes', 'integer', 'min:1'],
            'procedimento_id' => ['sometimes', 'integer', 'exists:tenant.procedures,id'],
            // Só quando não há `procedimento_id`: o bot manda o id e a API
            // resolve a duração, sem o n8n poder inventar a dela.
            'duracao' => ['required_without:procedimento_id', 'integer', 'in:15,30,45,60,90,120'],
        ])->validate();

        $dias = (int) ($validated['dias'] ?? 7);
        $profissionalId = (int) ($validated['profissional_id'] ?? 0);

        $duracao = isset($validated['procedimento_id'])
            ? $this->disponibilidadeService->duracaoDoProcedimento((int) $validated['procedimento_id'])
            : (int) $validated['duracao'];

        return response()->json([
            'dias' => $this->disponibilidadeService->horariosLivres($profissionalId, $duracao, $dias),
            'duracao' => $duracao,
        ]);
    }

    /**
     * * Etapas por onde a conversa do bot pode passar.
     *
     * O n8n escreve este campo, e ele volta a ser lido no próximo turno da
     * conversa. Sem a lista, qualquer string gravada vira uma etapa que o bot não
     * conhece — e o `switch` do n8n cairia no `default`, que é o pior lugar
     * para um estado corrompido: o paciente receberia o menu como se tivesse
     * recomeçado, sem explicação de por quê.
     *
     * @var list<string>
     */
    public const ETAPAS = [
        'consentimento',
        'menu',
        'cadastro',
        'profissional',
        'procedimento',
        'data',
        'horario',
        'recepcao',
        'encerrado',
    ];

    /**
     * Lê o estado da conversa de um contato; cria se não existir.
     * O n8n consulta isto para saber em que etapa o paciente está.
     *
     * A expiração é verificada aqui, e não só guardada: sem esta checagem, uma
     * conversa abandonada na etapa `horario` voltaria do dia seguinte ainda
     * esperando a escolha de um horário que nem existe mais, e o bot marcaria
     * uma consulta para um turno que já passou.
     */
    public function conversa(Request $request): JsonResponse
    {
        $jid = $request->string('remote_jid')->toString();

        if ($jid === '') {
            return response()->json(['message' => 'remote_jid obrigatório.'], 422);
        }

        $conversa = WhatsAppConversation::firstOrNew(['remote_jid' => $jid]);

        if (! $conversa->exists || $conversa->expires_at?->isPast()) {
            $conversa->etapa = 'consentimento';
            $conversa->dados = null;
            $conversa->expires_at = now()->addHours(4);
            $conversa->save();
        }

        return response()->json(['conversa' => $conversa]);
    }

    /**
     * Atualiza a etapa/dados da conversa.
     *
     * `dados` é validado como objeto — e não só como array — porque é o que
     * carrega nome, profissional e procedimento escolhidos. A regra `array` do
     * Laravel aceita lista, e `[1,2]` passaria por ela: o n8n gravaria lista onde
     * o próximo turno lê `dados.profissional_id`, e o defeito só apareceria
     * depois, como paciente em etapa errada. `array_is_list()` é o que separa as
     * duas coisas.
     */
    public function conversaAtualizar(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'remote_jid' => ['required', 'string', 'max:191'],
            'etapa' => ['sometimes', 'string', 'in:'.implode(',', self::ETAPAS)],
            'dados' => [
                'sometimes',
                'nullable',
                'array',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_array($value) && array_is_list($value)) {
                        $fail('O campo dados deve ser um objeto com chaves, não uma lista.');
                    }
                },
            ],
        ])->validate();

        $conversa = WhatsAppConversation::firstOrNew(['remote_jid' => $validated['remote_jid']]);
        $conversa->etapa = $validated['etapa'] ?? $conversa->etapa ?? 'consentimento';

        if (array_key_exists('dados', $validated)) {
            $conversa->dados = $validated['dados'];
        }

        $conversa->expires_at = now()->addHours(4);
        $conversa->save();

        return response()->json(['conversa' => $conversa]);
    }

    public function agendamento(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            // Precisa do prefixo de conexão: pacientes e profissionais vivem no
            // banco da clínica, e sem o prefixo a regra procuraria as tabelas
            // no banco central, onde elas não existem.
            'paciente_id' => ['required', 'exists:tenant.pacientes,id'],
            'profissional_id' => ['required', 'exists:tenant.professionals,id'],
            'dia' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'duracao' => ['sometimes', 'integer', 'in:15,30,45,60,90,120'],
            'tipo' => ['sometimes', 'string', 'in:confirmado,agendado'],
            'observacao' => ['nullable', 'string', 'max:1000'],
            // Reenvio da mesma requisição devolve a consulta já criada, em vez de
            // duplicar. Sem a chave, o bot fica sem idempotência.
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ])->validate();

        $chave = $validated['idempotency_key'] ?? null;

        if ($chave !== null) {
            $existente = $this->agendamentoService->existentePorChave($chave);

            if ($existente !== null) {
                return response()->json(['agendamento' => $existente->load(['paciente', 'profissional', 'status'])]);
            }
        }

        try {
            $consulta = $this->agendamentoService->criar([
                'paciente_id' => (int) $validated['paciente_id'],
                'profissional_id' => (int) $validated['profissional_id'],
                'dia' => $validated['dia'],
                'hora_inicio' => $validated['hora_inicio'],
                'duracao' => $validated['duracao'] ?? 30,
                'tipo' => $validated['tipo'] ?? AppointmentStatus::AGENDADO,
                'observacao' => $validated['observacao'] ?? null,
                'idempotency_key' => $chave,
            ]);
        } catch (HorarioOcupadoException) {
            return response()->json(['message' => 'Horário já não está disponível para este profissional.'], 409);
        } catch (LockTimeoutException) {
            // Lock com TTL de 10s e espera de 5s: chegar aqui já é anomalia, e a
            // resposta 503 manda o n8n reenviar do que tratar como erro definitivo.
            return response()->json(['message' => 'Agenda ocupada no momento, tente novamente em instantes.'], 503);
        } catch (UniqueConstraintViolationException $exception) {
            /**
             * Perdeu a corrida do índice único da chave de idempotência: outra
             * requisição com a mesma chave gravou primeiro. A resposta certa é a
             * consulta que ela criou — o cliente quer o agendamento, e ele existe.
             */
            if ($chave === null) {
                throw $exception;
            }

            $recuperada = $this->agendamentoService->existentePorChave($chave);

            if ($recuperada === null) {
                throw $exception;
            }

            return response()->json(['agendamento' => $recuperada->load(['paciente', 'profissional', 'status'])]);
        }

        return response()->json(['agendamento' => $consulta->load(['paciente', 'profissional', 'status'])], 201);
    }

    /**
     * Um turno da conversa: recebe o texto do paciente e devolve a resposta.
     *
     * Este é o único endpoint que o n8n chama por mensagem. A máquina de estados
     * mora no Laravel porque o Code node do n8n não tem HTTP nesta instalação,
     * e porque estado de conversa testável é estado em PHP — o mesmo `switch` aqui
     * é um teste de feature comum, e lá dentro só se verifica lendo registro de
     * execução do n8n.
     *
     * O n8n continua sendo o orquestrador: os guards (token, allowlist,
     * `from_me`) são dele, e o envio da resposta também. Aqui não há regra de
     * negócio, só o turno da conversa.
     */
    public function fluxo(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'remote_jid' => ['required', 'string', 'max:191'],
            'texto' => ['nullable', 'string', 'max:4096'],
        ])->validate();

        $clinica = $this->tenants->require();
        $texto = $validated['texto'] ?? '';

        $conversa = WhatsAppConversation::abrirPara($validated['remote_jid'], FluxoAtendimento::VALIDADE_HORAS);

        /**
         * A mesma precedência da rota com IA: opt-out, pausa/humano, expediente
         * e regra vêm antes do fluxo. Quando `Triagem` decide, a máquina de
         * estados nem roda — o que impede uma regra de sequestrar a escolha de
         * horário no meio do agendamento.
         */
        $triagem = $this->triagem->decidir($conversa, $texto, $clinica?->name ?? '');

        if ($triagem !== null) {
            return response()->json([
                'ok' => $triagem['ok'],
                'etapa_de' => $triagem['etapa_de'],
                'etapa_para' => $triagem['etapa_para'],
                'resposta' => $triagem['resposta'],
                'handled_by' => $triagem['handled_by'],
            ]);
        }

        $resultado = $this->fluxo->processar(
            $validated['remote_jid'],
            $texto,
            $clinica?->name ?? '',
        );

        /**
         * A máquina determinística mantém o comportamento antigo na etapa
         * `recepcao` (repete o aviso de espera), mas agora a recepção fica
         * sabendo que existe alguém esperando.
         */
        if ($resultado['etapa_para'] === 'recepcao') {
            $this->alertas->abrir($conversa->refresh(), WhatsAppAlert::HANDOFF, 'Fluxo encaminhou para a recepção.');
        }

        /**
         * O contrato interativo é ADITIVO: `resposta` continua sendo o texto
         * completo, e o n8n atual segue funcionando sem mudar nada. Com a flag
         * desligada a chave nem aparece, para o payload ser exatamente o de
         * antes. Quem for enviar o menu usa o objeto `interativo` na rota
         * `mensagem-interativa`, que sabe cair para o texto numerado.
         */
        if ($this->features->enabled($clinica, 'interactive_menus')) {
            $conversa->refresh();

            $menu = $this->menus->paraEtapa(
                $resultado['etapa_para'],
                is_array($conversa->dados) ? $conversa->dados : [],
                $clinica?->name ?? '',
            );

            if ($menu !== null) {
                $resultado['interativo'] = $menu;
            }
        }

        return response()->json($resultado);
    }

    /**
     * Envia um menu de botões ou lista, com fallback textual obrigatório.
     *
     * Existe como rota própria, e não como campo opcional em `mensagem`, para
     * deixar explícito o que muda: o corpo não é texto, é uma estrutura de
     * opções. A validação de limite é do WhatsApp (três botões, dez linhas), e
     * um menu fora disso seria recusado inteiro pela Evolution.
     *
     * Fluxo de decisão:
     *
     * - flag `interactive_menus` desligada: 409 com o texto pronto, sem enviar
     *   nada. Quem chamou decide mandar o fallback pela rota de texto.
     * - flag ligada e Evolution recusando o formato (4xx ou `error` no corpo):
     *   o texto numerado é enviado na hora, e a resposta diz `sent_fallback`.
     * - timeout/5xx: 502 sem fallback, porque o menu pode ter saído e o texto
     *   duplicaria a mensagem no celular do paciente.
     */
    public function enviarMensagemInterativa(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'telefone' => ['required', 'string', 'max:20'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'interativo' => ['required', 'array'],
            'interativo.tipo' => ['required', 'string', 'in:'.MenuInterativo::TIPO_BOTOES.','.MenuInterativo::TIPO_LISTA],
            'interativo.titulo' => ['required', 'string', 'max:60'],
            'interativo.descricao' => ['nullable', 'string', 'max:1024'],
            'interativo.rodape' => ['nullable', 'string', 'max:60'],
            'interativo.botao_lista' => ['nullable', 'string', 'max:20'],
            'interativo.opcoes' => ['required', 'array', 'min:1', 'max:10'],
            'interativo.opcoes.*.id' => ['required', 'string', 'max:64', 'distinct'],
            'interativo.opcoes.*.titulo' => ['required', 'string', 'max:24'],
            'interativo.opcoes.*.descricao' => ['nullable', 'string', 'max:72'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $opcoes = $request->input('interativo.opcoes');

            if (
                $request->input('interativo.tipo') === MenuInterativo::TIPO_BOTOES
                && is_array($opcoes)
                && count($opcoes) > max(1, (int) config('whatsapp.interactive.max_buttons', 3))
            ) {
                $validator->errors()->add(
                    'interativo.opcoes',
                    'Um menu de botões aceita no máximo '.config('whatsapp.interactive.max_buttons', 3).' opções; use o tipo list para listas maiores.',
                );
            }
        });

        $validated = $validator->validate();
        $clinica = $this->tenants->require();

        try {
            $menu = $this->menus->normalizar($validated['interativo']);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $textoFallback = $this->menus->texto($menu);

        if (! $this->features->enabled($clinica, 'interactive_menus')) {
            return response()->json([
                'status' => 'disabled',
                'message' => 'Menus interativos não estão habilitados para esta clínica.',
                'fallback_texto' => $textoFallback,
            ], 409);
        }

        $correlationId = $request->attributes->get(AssignCorrelationId::ATTRIBUTE);
        $correlationId = is_string($correlationId) ? $correlationId : null;

        if ($this->features->enabled($clinica, 'whatsapp_outbox')) {
            $registro = $this->outbox->enqueueInterativo(
                $clinica,
                $validated['telefone'],
                $menu,
                $textoFallback,
                $validated['idempotency_key'] ?? null,
                $correlationId,
            );

            if ($registro->wasRecentlyCreated) {
                $this->outbox->dispatch($clinica, $registro);
                $registro->refresh();
            }

            $resposta = $this->respostaDaOutbox($registro);
            $corpo = $resposta->getData(true);
            $corpo['fallback_texto'] = $textoFallback;

            return response()->json($corpo, $resposta->getStatusCode());
        }

        try {
            $resposta = $this->menus->enviar($this->evolution, $clinica, $validated['telefone'], $menu);
        } catch (EvolutionIndisponivel $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        } catch (ConnectionException $exception) {
            Log::error('A conexão com a Evolution caiu ao enviar um menu do WhatsApp.', [
                'clinica' => $clinica->id,
                'erro' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'A conexão com a Evolution API falhou; o menu não foi reenviado para não duplicar a mensagem.',
            ], 502);
        } catch (RequestException $exception) {
            $status = (int) ($exception->response?->status() ?? 0);

            if ($status === 429 || $status >= 500) {
                Log::error('A Evolution API ficou instável ao enviar um menu do WhatsApp.', [
                    'clinica' => $clinica->id,
                    'status' => $status,
                ]);

                return response()->json(['message' => 'A Evolution API não conseguiu enviar o menu.'], 502);
            }

            $resposta = ['error' => 'HTTP '.$status];
        }

        if (isset($resposta['error'])) {
            try {
                $textoResposta = $this->evolution->sendText($clinica, $validated['telefone'], $textoFallback);
            } catch (EvolutionIndisponivel $exception) {
                return response()->json(['message' => $exception->getMessage()], 503);
            } catch (RequestException|ConnectionException $exception) {
                Log::error('O fallback textual do menu do WhatsApp também falhou.', [
                    'clinica' => $clinica->id,
                    'erro' => $exception->getMessage(),
                ]);

                return response()->json(['message' => 'A Evolution API não conseguiu enviar o menu nem o texto de fallback.'], 502);
            }

            if (isset($textoResposta['error'])) {
                return response()->json([
                    'message' => 'A Evolution API não conseguiu enviar o menu nem o texto de fallback.',
                    'erro' => $textoResposta['error'],
                ], 502);
            }

            $this->registrarMensagemEnviada(
                $clinica,
                $validated['telefone'],
                $textoFallback,
                $textoResposta,
                $correlationId,
            );

            return response()->json([
                'status' => 'sent_fallback',
                'message' => 'A Evolution recusou o menu; a versão em texto foi enviada.',
                'fallback_texto' => $textoFallback,
                'resposta' => $textoResposta,
            ], 200);
        }

        $this->registrarMensagemEnviada(
            $clinica,
            $validated['telefone'],
            $textoFallback,
            $resposta,
            $correlationId,
        );

        return response()->json([
            'status' => 'sent',
            'tipo' => $menu['tipo'],
            'fallback_texto' => $textoFallback,
            'message' => $resposta,
        ], 200);
    }

    /**
     * Um turno da conversa COM IA.
     *
     * Mesma responsabilidade de `fluxo()`, mas com a camada que interpreta texto
     * livre. A IA PROPÕE (intenção e texto); quem decide e executa continua sendo
     * o Laravel — este método não agenda, não cancela e não confirma nada por
     * conta própria, apenas delega ao fluxo determinístico quando a resposta não
     * é uma mera informação.
     *
     * A rota exige `plan:ai_agent` além de `plan:whatsapp`: ter WhatsApp não é ter
     * IA, e o plano que vende uma não vende necessariamente a outra.
     */
    public function fluxoIA(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'remote_jid' => ['required', 'string', 'max:191'],
            'texto' => ['nullable', 'string', 'max:4096'],
        ])->validate();

        $clinica = $this->tenants->require();

        return response()->json($this->ia->processar(
            $validated['remote_jid'],
            $validated['texto'] ?? '',
            $clinica?->name ?? '',
        ));
    }

    /**
     * Pausa o bot e passa a conversa para atendimento humano.
     *
     * Existe como endpoint separado porque a pausa precisa ser uma decisão
     * explícita e auditável, não um efeito colateral do texto. Enquanto o
     * `bot_status` for `humano`, nenhuma resposta automática sai.
     */
    public function handoff(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'remote_jid' => ['required', 'string', 'max:191'],
        ])->validate();

        $conversa = WhatsAppConversation::firstOrNew(['remote_jid' => $validated['remote_jid']]);
        $conversa->etapa ??= 'menu';

        $conversa->assumirPorHumano();

        // Quem assume a conversa está atendendo o que estava na fila: manter o
        // alerta aberto faria a recepção olhar de novo o que já está na mão.
        $this->alertas->resolver($conversa);

        AuditLogger::log('handoff', 'whatsapp_conversations', $conversa->id, depois: [
            'bot_status' => $conversa->bot_status,
        ]);

        return response()->json(['conversa' => $conversa]);
    }

    /**
     * Devolve a conversa ao bot.
     *
     * Retomada controlada: limpa a pausa e o vínculo com o atendente de uma vez,
     * para não sobrar um `humano_iniciado_em` que faria o painel contar um
     * atendimento que já terminou.
     */
    public function retomar(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'remote_jid' => ['required', 'string', 'max:191'],
        ])->validate();

        $conversa = WhatsAppConversation::firstOrNew(['remote_jid' => $validated['remote_jid']]);
        $conversa->etapa ??= 'menu';

        $conversa->retomarBot();

        AuditLogger::log('retomar', 'whatsapp_conversations', $conversa->id, depois: [
            'bot_status' => $conversa->bot_status,
        ]);

        return response()->json(['conversa' => $conversa]);
    }

    /**
     * Fila de conversas esperando gente.
     *
     * É o que faltava para "avisar a recepção" significar alguma coisa: o alerta
     * era gravado, mas ninguém conseguia listá-lo. Devolve apenas alertas
     * abertos, do mais antigo para o mais novo, com quanto tempo o paciente já
     * espera — ordenar por criação é o que faz o mais antigo aparecer primeiro.
     */
    public function alertas(): JsonResponse
    {
        $abertos = WhatsAppAlert::query()
            ->abertos()
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(100)
            ->get();

        return response()->json([
            'total' => WhatsAppAlert::query()->abertos()->count(),
            'alertas' => $abertos->map(fn (WhatsAppAlert $alerta): array => [
                'id' => $alerta->id,
                'remote_jid' => $alerta->remote_jid,
                'tipo' => $alerta->tipo,
                'motivo' => $alerta->motivo,
                'criado_em' => $alerta->created_at?->toIso8601String(),
                'esperando_minutos' => $alerta->created_at === null
                    ? 0
                    : max(0, (int) $alerta->created_at->diffInMinutes(now())),
            ])->all(),
        ]);
    }

    public function respostasRapidas(): JsonResponse
    {
        return response()->json([
            'respostas' => $this->respostasRapidas->listar()
                ->map(fn (WhatsAppQuickReply $resposta): array => [
                    'id' => $resposta->id,
                    'atalho' => $resposta->atalho,
                    'titulo' => $resposta->titulo,
                    'conteudo' => $resposta->conteudo,
                ])
                ->all(),
        ]);
    }

    public function notas(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'remote_jid' => ['required', 'string', 'max:191'],
            'limite' => ['nullable', 'integer', 'min:1', 'max:200'],
        ])->validate();

        $notas = $this->notas->listar($validated['remote_jid'], (int) ($validated['limite'] ?? 50));

        return response()->json([
            'notas' => $notas->map(fn (WhatsAppNote $nota): array => $this->notaParaJson($nota))->all(),
        ]);
    }

    /**
     * Cria uma nota interna. Nada é enviado ao paciente: a nota só existe no
     * banco da clínica e na auditoria (sem o texto).
     */
    public function criarNota(Request $request): JsonResponse
    {
        // Trim antes de validar: uma nota só com espaços não é conteúdo, e sem
        // isso passaria no `required` e estouraria no serviço.
        $request->merge(['conteudo' => trim((string) $request->input('conteudo', ''))]);

        $validated = Validator::make($request->all(), [
            'remote_jid' => ['required', 'string', 'max:191'],
            'conteudo' => ['required', 'string', 'max:'.NotasInternas::LIMITE_CARACTERES],
        ])->validate();

        $nota = $this->notas->criar(
            $validated['remote_jid'],
            auth()->id(),
            $validated['conteudo'],
        );

        return response()->json(['nota' => $this->notaParaJson($nota)], 201);
    }

    /**
     * @return array{id: int, remote_jid: string, user_id: int|null, conteudo: string, criado_em: string|null}
     */
    private function notaParaJson(WhatsAppNote $nota): array
    {
        return [
            'id' => $nota->id,
            'remote_jid' => $nota->remote_jid,
            'user_id' => $nota->user_id,
            'conteudo' => $nota->conteudo,
            'criado_em' => $nota->created_at?->toIso8601String(),
        ];
    }

    public function agendamentosPaciente(Request $request): JsonResponse
    {
        $telefone = $this->normalizarTelefone($request->input('telefone'));

        $paciente = $telefone ? Paciente::where('celular', 'like', '%'.substr($telefone, -9))->first() : null;

        if ($paciente === null) {
            return response()->json(['agendamentos' => []]);
        }

        $agendamentos = Appointment::with(['profissional', 'status', 'room'])
            ->where('paciente_id', $paciente->id)
            ->where('inicio', '>=', Carbon::today()->startOfDay())
            ->orderBy('inicio')
            ->get();

        return response()->json(['agendamentos' => $agendamentos]);
    }

    public function confirmarAgendamento(Request $request, Appointment $consulta): JsonResponse
    {
        $confirmado = AppointmentStatus::idFor(AppointmentStatus::CONFIRMADO);

        AuditLogger::log('confirmar', 'appointments', $consulta->id, antes: ['appointment_status_id' => $consulta->appointment_status_id], depois: ['appointment_status_id' => $confirmado]);

        $consulta->update(['appointment_status_id' => $confirmado]);

        return response()->json(['agendamento' => $consulta->load(['paciente', 'profissional', 'status'])]);
    }

    public function cancelarAgendamento(Request $request, Appointment $consulta): JsonResponse
    {
        $cancelado = AppointmentStatus::idFor(AppointmentStatus::CANCELADO);

        AuditLogger::log('cancelar', 'appointments', $consulta->id, antes: ['appointment_status_id' => $consulta->appointment_status_id], depois: ['appointment_status_id' => $cancelado]);

        $consulta->update(['appointment_status_id' => $cancelado]);

        return response()->json(['message' => 'Agendamento cancelado.']);
    }

    private function normalizarTelefone(?string $telefone): string
    {
        return preg_replace('/\D/', '', (string) $telefone);
    }
}
