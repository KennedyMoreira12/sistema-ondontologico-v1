<?php

namespace App\Http\Controllers;

use App\Http\Requests\AppointmentStoreRequest;
use App\Http\Requests\AppointmentUpdateRequest;
use App\Http\Requests\WaitingListStoreRequest;
use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Room;
use App\Models\WaitingList;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AgendaController extends Controller
{
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

    public function store(AppointmentStoreRequest $request): RedirectResponse
    {
        $inicio = $request->date('dia')->setTimeFromTimeString($request->string('hora_inicio'));
        $fim = $inicio->copy()->addMinutes($request->integer('duracao'));

        $consulta = Appointment::create([
            'clinica_id' => auth()->user()->clinica_id,
            'paciente_id' => $request->integer('paciente_id'),
            'profissional_id' => $request->integer('profissional_id'),
            'room_id' => $request->integer('room_id') ?: null,
            'appointment_status_id' => $request->input('appointment_status_id') ?: 1,
            'inicio' => $inicio,
            'fim' => $fim,
            'titulo' => $request->input('titulo'),
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
            'observacao' => $request->input('observacao'),
            'retorno' => $request->boolean('retorno'),
        ]);

        AuditLogger::log('atualizar', 'appointments', $consulta->id, antes: $before, depois: $consulta->only(['paciente_id', 'profissional_id', 'inicio', 'fim', 'appointment_status_id']));

        return redirect()->route('agenda.index', ['data' => $inicio->toDateString()])
            ->with('success', 'Consulta atualizada.');
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
