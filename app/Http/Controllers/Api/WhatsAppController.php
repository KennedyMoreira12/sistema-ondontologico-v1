<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\EvolutionIndisponivel;
use App\Exceptions\HorarioOcupadoException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Central\Clinic;
use App\Models\ClinicProfile;
use App\Models\Paciente;
use App\Models\Procedure;
use App\Models\Professional;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\Agenda\AgendamentoService;
use App\Services\Agenda\DisponibilidadeService;
use App\Services\AuditLogger;
use App\Services\EvolutionService;
use App\Services\Tenant\TenantManager;
use App\Services\WhatsApp\FluxoAtendimento;
use App\Support\EvolutionPayload;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

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
        ])->validate();

        $clinica = $this->tenants->require();

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

        $this->registrarMensagemEnviada($clinica, $validated['telefone'], $validated['texto'], $resposta);

        return response()->json([
            'status' => 'sent',
            'message' => $resposta,
        ], 200);
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
    protected function registrarMensagemEnviada(Clinic $clinica, string $telefone, string $texto, array $resposta): void
    {
        try {
            WhatsAppMessage::create([
                'message_key' => $this->chaveDaEvolucao($resposta)
                    ?? $this->chaveLocal($clinica, $telefone, $texto),
                'from_me' => true,
                'event' => EvolutionPayload::EVENTO_MENSAGEM,
                'message_type' => 'conversation',
                'remote_jid' => $this->jidDoWhatsapp($telefone),
                'body' => $texto,
            ]);
        } catch (UniqueConstraintViolationException) {
            // O webhook da Evolution chegou antes do retorno. Nada a fazer.
        }
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

        return response()->json($this->fluxo->processar(
            $validated['remote_jid'],
            $validated['texto'] ?? '',
            $clinica?->name ?? '',
        ));
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
