<?php

namespace App\Http\Controllers;

use App\Exceptions\AgendaConflito;
use App\Http\Requests\AgendaRescheduleRequest;
use App\Http\Requests\AppointmentStoreRequest;
use App\Http\Requests\AppointmentUpdateRequest;
use App\Http\Requests\WaitingListStoreRequest;
use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Room;
use App\Models\WaitingList;
use App\Services\Agenda\AgendaService;
use App\Services\Atendimento\AtendimentoService;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AgendaController extends Controller
{
    public function __construct(
        private AgendaService $agenda,
        private AtendimentoService $atendimentos,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('agenda.view');

        $inicioSemana = Carbon::parse($request->date('data') ?? today())
            ->startOfWeek(Carbon::MONDAY);

        $dias = [];
        for ($i = 0; $i < 6; $i++) {
            $dias[] = $inicioSemana->copy()->addDays($i);
        }

        $fimSemana = $inicioSemana->copy()->addDays(5)->endOfDay();

        $profissionalId = $request->integer('profissional_id');

        $consultas = Appointment::with(['paciente', 'profissional', 'room', 'status'])
            ->whereBetween('inicio', [$inicioSemana->startOfDay(), $fimSemana])
            ->when($profissionalId > 0, fn ($q) => $q->where('profissional_id', $profissionalId))
            ->orderBy('inicio')
            ->get()
            ->groupBy(fn (Appointment $c) => $c->inicio->toDateString().'|'.$c->inicio->copy()->floorMinutes(30)->format('H:i'));

        $horarios = [];
        $slot = Carbon::createFromTime(7, 0);
        while ($slot->lt(Carbon::createFromTime(21, 0))) {
            $horarios[] = $slot->copy()->format('H:i');
            $slot->addMinutes(30);
        }

        $listaEspera = WaitingList::with('paciente')
            ->where('status', 'aguardando')
            ->orderByDesc('prioridade')
            ->orderBy('periodo_inicio')
            ->get();

        $pacientes = Paciente::where('status', 'ativo')->orderBy('nome')->get();
        $profissionais = Professional::where('is_active', true)->orderBy('nome')->get();
        $salas = Room::where('is_active', true)->orderBy('nome')->get();
        $statuses = AppointmentStatus::orderBy('label')->get();

        return view('agenda.index', compact(
            'dias',
            'inicioSemana',
            'consultas',
            'horarios',
            'listaEspera',
            'pacientes',
            'profissionais',
            'salas',
            'statuses',
            'profissionalId'
        ));
    }

    /**
     * Agenda do dia: a visão que a recepção usa para receber o paciente.
     */
    public function dia(Request $request): View
    {
        $this->authorize('agenda.view');

        $dia = Carbon::parse($request->date('data') ?? today())->startOfDay();
        $profissionalId = $request->integer('profissional_id');

        $consultas = $this->agenda->agendaDoDia($dia, $profissionalId > 0 ? $profissionalId : null);

        $resumo = [
            'total' => $consultas->count(),
            'atendidos' => $consultas->filter(fn (Appointment $c): bool => $c->status?->name === 'atendido')->count(),
            'faltas' => $consultas->filter(fn (Appointment $c): bool => $c->status?->name === 'faltou')->count(),
            'aguardando' => $consultas->filter(fn (Appointment $c): bool => in_array($c->status?->name, ['agendado', 'confirmado'], true))->count(),
        ];

        return view('agenda.dia', [
            'dia' => $dia,
            'consultas' => $consultas,
            'resumo' => $resumo,
            'profissionais' => Professional::where('is_active', true)->orderBy('nome')->get(),
            'profissionalId' => $profissionalId,
            'pacientes' => Paciente::where('status', 'ativo')->orderBy('nome')->get(),
            'salas' => Room::where('is_active', true)->orderBy('nome')->get(),
            'statuses' => AppointmentStatus::orderBy('label')->get(),
        ]);
    }

    /**
     * Visão mensal: quantos atendimentos por dia, para enxergar a ocupação.
     */
    public function mes(Request $request): View
    {
        $this->authorize('agenda.view');

        $referencia = Carbon::parse($request->date('data') ?? today())->startOfMonth();
        $inicio = $referencia->copy()->startOfWeek(Carbon::MONDAY);
        $fim = $referencia->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $profissionalId = $request->integer('profissional_id');

        $consultas = Appointment::query()
            ->with(['paciente:id,nome', 'profissional:id,nome', 'status:id,name,label,cor'])
            ->whereBetween('inicio', [$inicio->copy()->startOfDay(), $fim->copy()->endOfDay()])
            ->when($profissionalId > 0, fn ($query) => $query->where('profissional_id', $profissionalId))
            ->orderBy('inicio')
            ->get()
            ->groupBy(fn (Appointment $consulta): string => $consulta->inicio->toDateString());

        return view('agenda.mes', [
            'referencia' => $referencia,
            'inicio' => $inicio,
            'fim' => $fim,
            'porDia' => $consultas,
            'profissionais' => Professional::where('is_active', true)->orderBy('nome')->get(),
            'profissionalId' => $profissionalId,
        ]);
    }

    /**
     * Horários livres para o formulário (JSON).
     */
    public function disponiveis(Request $request): JsonResponse
    {
        $this->authorize('agenda.view');

        $dados = $request->validate([
            'profissional_id' => ['required', 'integer', 'exists:tenant.professionals,id'],
            'dia' => ['required', 'date_format:Y-m-d'],
            'duracao' => ['nullable', 'integer', 'in:15,30,45,60,90,120'],
        ]);

        $horarios = $this->agenda->horariosDisponiveis(
            (int) $dados['profissional_id'],
            Carbon::parse($dados['dia']),
            (int) ($dados['duracao'] ?? 30),
        );

        return response()->json(['horarios' => $horarios]);
    }

    public function store(AppointmentStoreRequest $request): RedirectResponse
    {
        $inicio = $request->date('dia')->setTimeFromTimeString($request->string('hora_inicio'));
        $fim = $inicio->copy()->addMinutes($request->integer('duracao'));

        $consulta = Appointment::create([
            'clinica_id' => auth()->user()->clinica_id,
            'paciente_id' => $request->integer('paciente_id'),
            'profissional_id' => $request->integer('profissional_id'),
            'room_id' => $request->integer('room_id') ?: null,
            'appointment_status_id' => $this->agenda->statusAgendado(),
            'inicio' => $inicio,
            'fim' => $fim,
            'titulo' => $request->input('titulo'),
            'area' => $request->input('area'),
            'servico' => $request->input('servico'),
            'valor' => $request->input('valor') ?: null,
            'observacao' => $request->input('observacao'),
            'retorno' => $request->boolean('retorno'),
        ]);

        AuditLogger::log('criar', 'appointments', $consulta->id, depois: $consulta->only(['paciente_id', 'profissional_id', 'inicio', 'fim']));

        return redirect()->route('agenda.index', ['data' => $inicio->toDateString()])
            ->with('success', 'Consulta agendada com sucesso.');
    }

    public function update(AppointmentUpdateRequest $request, Appointment $consulta): RedirectResponse
    {
        $inicio = $request->date('dia')->setTimeFromTimeString($request->string('hora_inicio'));
        $fim = $inicio->copy()->addMinutes($request->integer('duracao'));

        $before = $consulta->only(['paciente_id', 'profissional_id', 'inicio', 'fim', 'appointment_status_id']);

        $consulta->update([
            'paciente_id' => $request->integer('paciente_id'),
            'profissional_id' => $request->integer('profissional_id'),
            'room_id' => $request->integer('room_id') ?: null,
            'appointment_status_id' => $request->integer('appointment_status_id'),
            'inicio' => $inicio,
            'fim' => $fim,
            'titulo' => $request->input('titulo'),
            'area' => $request->input('area'),
            'servico' => $request->input('servico'),
            'valor' => $request->input('valor') ?: null,
            'observacao' => $request->input('observacao'),
            'retorno' => $request->boolean('retorno'),
        ]);

        AuditLogger::log('atualizar', 'appointments', $consulta->id, antes: $before, depois: $consulta->only(['paciente_id', 'profissional_id', 'inicio', 'fim', 'appointment_status_id']));

        return redirect()->route('agenda.index', ['data' => $inicio->toDateString()])
            ->with('success', 'Consulta atualizada.');
    }

    /**
     * Reagendamento rápido, com a mesma checagem de conflito do formulário.
     */
    public function reagendar(AgendaRescheduleRequest $request, Appointment $consulta): RedirectResponse
    {
        $inicio = $request->date('dia')->setTimeFromTimeString($request->string('hora_inicio'));

        try {
            $this->agenda->reagendar(
                $consulta,
                $inicio,
                $request->integer('duracao'),
                $request->integer('profissional_id') ?: null,
                $request->integer('room_id') ?: null,
            );
        } catch (AgendaConflito $conflito) {
            return back()->withErrors($conflito->erros())->withInput();
        }

        AuditLogger::log('reagendar', 'appointments', $consulta->id, depois: $consulta->only(['inicio', 'fim', 'profissional_id', 'room_id']));

        return back()->with('success', 'Atendimento reagendado.');
    }

    /**
     * Presença ou falta do atendimento.
     */
    public function presenca(Request $request, Appointment $consulta): RedirectResponse
    {
        $this->authorize('agenda.update');

        $dados = $request->validate([
            'status' => ['required', 'in:atendido,faltou'],
        ]);

        $consulta = $dados['status'] === 'atendido'
            ? $this->atendimentos->marcarPresente($consulta)
            : $this->atendimentos->marcarFalta($consulta);

        AuditLogger::log('presenca', 'appointments', $consulta->id, depois: ['appointment_status_id' => $consulta->appointment_status_id]);

        return back()->with('success', $dados['status'] === 'atendido' ? 'Presença registrada.' : 'Falta registrada.');
    }

    public function destroy(Appointment $consulta): RedirectResponse
    {
        $this->authorize('agenda.delete');

        $cancelado = AppointmentStatus::where('name', 'cancelado')->value('id');

        AuditLogger::log('cancelar', 'appointments', $consulta->id, antes: $consulta->only(['inicio', 'appointment_status_id']));

        $consulta->update(['appointment_status_id' => $cancelado]);

        return redirect()->route('agenda.index', ['data' => $consulta->inicio->toDateString()])
            ->with('success', 'Consulta cancelada.');
    }

    public function storeWaiting(WaitingListStoreRequest $request): RedirectResponse
    {
        $entry = WaitingList::create($request->validated());

        AuditLogger::log('criar', 'waiting_lists', $entry->id, depois: $entry->only(['paciente_id', 'motivo']));

        return back()->with('success', 'Paciente adicionado à lista de espera.');
    }

    public function updateWaiting(Request $request, WaitingList $waiting): RedirectResponse
    {
        $this->authorize('agenda.update');

        $validated = $request->validate([
            'status' => ['required', 'string', 'max:20'],
        ]);

        $waiting->update($validated);

        AuditLogger::log('atualizar', 'waiting_lists', $waiting->id, depois: $validated);

        return back()->with('success', 'Lista de espera atualizada.');
    }

    public function destroyWaiting(WaitingList $waiting): RedirectResponse
    {
        $this->authorize('agenda.delete');

        AuditLogger::log('excluir', 'waiting_lists', $waiting->id, antes: $waiting->only(['paciente_id', 'motivo']));

        $waiting->delete();

        return back()->with('success', 'Registro removido da lista de espera.');
    }
}
