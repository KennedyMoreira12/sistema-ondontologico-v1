<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\HorarioOcupadoException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\ClinicProfile;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Room;
use App\Services\AuditLogger;
use App\Services\Tenant\TenantManager;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * API interna consumida pelo n8n de cada clínica.
 *
 * Não existe "a clínica" aqui: a clínica é a do token que o middleware
 * autentica, e o banco conectado é o dela. Nenhum método aceita id de clínica
 * por parâmetro, porque isso permitiria o n8n de uma clínica escrever no banco
 * de outra.
 */
class WhatsAppController extends Controller
{
    public function __construct(protected TenantManager $tenants) {}

    public function info(): JsonResponse
    {
        return response()->json([
            'clinica' => [
                'id' => $this->tenants->id(),
                'nome' => $this->tenants->current()?->name,
                'perfil' => ClinicProfile::current(),
            ],
            'statuses' => AppointmentStatus::orderBy('id')->get(),
        ]);
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
