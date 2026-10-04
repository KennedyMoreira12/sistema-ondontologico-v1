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
use App\Models\Professional;
use App\Models\Room;
use App\Models\WhatsAppMessage;
use App\Services\AuditLogger;
use App\Services\EvolutionService;
use App\Services\Tenant\TenantManager;
use App\Support\EvolutionPayload;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

    public function disponibilidade(Request $request): JsonResponse
    {
        $dias = (int) $request->integer('dias', 7);
        $profissionalId = (int) $request->integer('profissional_id');

        $profissionais = Professional::where('is_active', true)
            ->when($profissionalId > 0, fn ($q) => $q->where('id', $profissionalId))
            ->orderBy('nome')
            ->get();

        $inicio = Carbon::today();
        $fim = Carbon::today()->addDays($dias);

        $agendamentos = Appointment::with('status')
            ->where('fim', '>', $inicio)
            ->where('inicio', '<', $fim->endOfDay())
            ->get();

        $diasAbertos = [];
        for ($i = 0; $i <= $dias; $i++) {
            $dia = Carbon::today()->addDays($i);
            if ($dia->isSunday()) {
                continue;
            }
            $diasAbertos[] = $dia;
        }

        $resultado = [];

        foreach ($profissionais as $profissional) {
            foreach ($diasAbertos as $dia) {
                $ocupados = $agendamentos
                    ->filter(fn (Appointment $a) => $a->profissional_id === $profissional->id
                        && $a->status?->name !== AppointmentStatus::CANCELADO
                        && $a->inicio->toDateString() === $dia->toDateString())
                    ->map(fn (Appointment $a) => [
                        'inicio' => $a->inicio->timestamp,
                        'fim' => $a->fim->timestamp,
                    ])
                    ->values();

                $horarios = [];
                $slot = $dia->copy()->setTime(7, 0);
                $fimDia = $dia->copy()->setTime(21, 0);

                while ($slot->lt($fimDia)) {
                    $bloqueado = $ocupados->contains(fn ($a) => $slot->timestamp >= $a['inicio'] && $slot->timestamp < $a['fim']);

                    $horarios[] = [
                        'hora' => $slot->format('H:i'),
                        'disponivel' => ! $bloqueado,
                    ];

                    $slot->addMinutes(30);
                }

                $resultado[] = [
                    'profissional_id' => $profissional->id,
                    'profissional' => $profissional->nome,
                    'data' => $dia->toDateString(),
                    'dia_semana' => $dia->translatedFormat('l'),
                    'horarios' => $horarios,
                ];
            }
        }

        return response()->json(['dias' => $resultado]);
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
            $existente = Appointment::query()
                ->where('idempotency_key', $chave)
                ->first();

            if ($existente !== null) {
                return response()->json(['agendamento' => $existente->load(['paciente', 'profissional', 'status'])]);
            }
        }

        $duracao = $validated['duracao'] ?? 30;
        $tipo = $validated['tipo'] ?? AppointmentStatus::AGENDADO;

        $inicio = Carbon::parse($validated['dia'])->setTimeFromTimeString($validated['hora_inicio']);
        $fim = $inicio->copy()->addMinutes($duracao);

        $statusId = $this->statusId($tipo);

        try {
            /**
             * O mutex vai por PROFISSIONAL E DIA, não pelo horário pedido: dois
             * agendamentos que se sobrepõem sem coincidir de início (09:00 e
             * 09:15) precisam cair no mesmo lock, e travar pelo horário exato
             * deixaria os dois passarem.
             *
             * E é um lock, e não `lockForUpdate()` na consulta de conflito: um
             * `SELECT EXISTS` não trava a linha nem a faixa onde a linha ainda
             * não existe, então os dois "não há conflito" aconteciam mesmo assim.
             * O lock é o que segura; a transção mantém checagem e gravação no
             * mesmo passo, e o índice único da chave de idempotência é a rede
             * final se tudo acima falhar.
             */
            $consulta = Cache::lock(
                "agenda:{$validated['profissional_id']}:{$inicio->toDateString()}",
                10,
            )->block(5, fn (): Appointment => DB::transaction(function () use ($validated, $chave, $inicio, $fim, $statusId): Appointment {
                $conflito = Appointment::query()
                    ->where('profissional_id', $validated['profissional_id'])
                    ->whereHas('status', fn ($query) => $query->where('name', '!=', AppointmentStatus::CANCELADO))
                    ->where('fim', '>', $inicio)
                    ->where('inicio', '<', $fim)
                    ->exists();

                if ($conflito) {
                    throw new HorarioOcupadoException;
                }

                return Appointment::create([
                    'clinica_id' => $this->tenants->id(),
                    'paciente_id' => $validated['paciente_id'],
                    'profissional_id' => $validated['profissional_id'],
                    'room_id' => Room::where('is_active', true)->value('id'),
                    'appointment_status_id' => $statusId,
                    'inicio' => $inicio,
                    'fim' => $fim,
                    'titulo' => 'Agendamento via WhatsApp',
                    'observacao' => $validated['observacao'] ?? null,
                    'idempotency_key' => $chave,
                ]);
            }));
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

            $consulta = Appointment::query()
                ->where('idempotency_key', $chave)
                ->first();

            if ($consulta === null) {
                throw $exception;
            }

            return response()->json(['agendamento' => $consulta->load(['paciente', 'profissional', 'status'])]);
        }

        AuditLogger::log('criar', 'appointments', $consulta->id, depois: $consulta->only(['paciente_id', 'profissional_id', 'inicio', 'fim', 'appointment_status_id']));

        return response()->json(['agendamento' => $consulta->load(['paciente', 'profissional', 'status'])], 201);
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

    /**
     * Id do status pedido pelo bot. O nome vem da própria validação (`in:`), então
     * aqui nunca é valor fora da lista de canônicos.
     */
    private function statusId(string $name): int
    {
        return AppointmentStatus::idFor($name);
    }

    private function normalizarTelefone(?string $telefone): string
    {
        return preg_replace('/\D/', '', (string) $telefone);
    }
}
