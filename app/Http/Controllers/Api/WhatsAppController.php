<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Clinica;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Room;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class WhatsAppController extends Controller
{
    public function info(): JsonResponse
    {
        $clinica = Clinica::first();

        return response()->json([
            'clinica' => $clinica,
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
            $clinica = Clinica::first();

            $paciente = Paciente::create([
                'clinica_id' => $clinica?->id,
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
                        && $a->status?->name !== 'cancelado'
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
            'paciente_id' => ['required', 'exists:pacientes,id'],
            'profissional_id' => ['required', 'exists:professionals,id'],
            'dia' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'duracao' => ['sometimes', 'integer', 'in:15,30,45,60,90,120'],
            'tipo' => ['sometimes', 'string', 'in:confirmado,agendado'],
            'observacao' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        $duracao = $validated['duracao'] ?? 30;
        $tipo = $validated['tipo'] ?? 'agendado';

        $inicio = Carbon::parse($validated['dia'])->setTimeFromTimeString($validated['hora_inicio']);
        $fim = $inicio->copy()->addMinutes($duracao);

        $conflito = Appointment::where('profissional_id', $validated['profissional_id'])
            ->whereHas('status', fn ($q) => $q->where('name', '!=', 'cancelado'))
            ->where('fim', '>', $inicio)
            ->where('inicio', '<', $fim)
            ->exists();

        if ($conflito) {
            return response()->json(['message' => 'Horário já não está disponível para este profissional.'], 409);
        }

        $statusId = AppointmentStatus::where('name', $tipo)->value('id') ?? 1;

        $consulta = Appointment::create([
            'clinica_id' => Professional::find($validated['profissional_id'])->clinica_id ?? Clinica::first()?->id,
            'paciente_id' => $validated['paciente_id'],
            'profissional_id' => $validated['profissional_id'],
            'room_id' => Room::where('is_active', true)->value('id'),
            'appointment_status_id' => $statusId,
            'inicio' => $inicio,
            'fim' => $fim,
            'titulo' => 'Agendamento via WhatsApp',
            'observacao' => $validated['observacao'] ?? null,
        ]);

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
        $confirmado = AppointmentStatus::where('name', 'confirmado')->value('id');

        AuditLogger::log('confirmar', 'appointments', $consulta->id, antes: ['appointment_status_id' => $consulta->appointment_status_id], depois: ['appointment_status_id' => $confirmado]);

        $consulta->update(['appointment_status_id' => $confirmado]);

        return response()->json(['agendamento' => $consulta->load(['paciente', 'profissional', 'status'])]);
    }

    public function cancelarAgendamento(Request $request, Appointment $consulta): JsonResponse
    {
        $cancelado = AppointmentStatus::where('name', 'cancelado')->value('id');

        AuditLogger::log('cancelar', 'appointments', $consulta->id, antes: ['appointment_status_id' => $consulta->appointment_status_id], depois: ['appointment_status_id' => $cancelado]);

        $consulta->update(['appointment_status_id' => $cancelado]);

        return response()->json(['message' => 'Agendamento cancelado.']);
    }

    private function normalizarTelefone(?string $telefone): string
    {
        return preg_replace('/\D/', '', (string) $telefone);
    }
}
