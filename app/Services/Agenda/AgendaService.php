<?php

namespace App\Services\Agenda;

use App\Exceptions\AgendaConflito;
use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\ProfessionalSchedule;
use App\Models\Room;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Regras de ocupação da agenda.
 *
 * A validação que existia nos FormRequests olhava apenas profissional e
 * paciente, e repetia a mesma consulta em dois arquivos. Aqui a regra é uma
 * só, usada pelo formulário, pelo reagendamento e pelo cálculo de horários
 * livres. Sala também entra: dois atendimentos na mesma sala ao mesmo tempo
 * são conflito, mesmo com profissionais diferentes.
 */
class AgendaService
{
    /**
     * Horário de abertura usado quando o profissional ainda não tem grade
     * cadastrada. É o intervalo que a planilha da clínica usa (07:00–21:00),
     * e não uma regra de negócio nova.
     */
    private const ABERTURA_PADRAO = '07:00';

    private const FECHAMENTO_PADRAO = '21:00';

    private const PASSO_MINUTOS = 30;

    /**
     * @return array<string, list<string>> Chaves: profissional, paciente, sala.
     */
    public function conflitos(
        Carbon $inicio,
        Carbon $fim,
        int $profissionalId,
        int $pacienteId,
        ?int $roomId = null,
        ?Appointment $ignorar = null,
    ): array {
        $cancelado = AppointmentStatus::query()->where('name', 'cancelado')->value('id');
        $conflitos = [];

        $base = Appointment::query()
            ->when($ignorar !== null, fn ($query) => $query->whereKeyNot($ignorar->getKey()))
            ->when($cancelado !== null, fn ($query) => $query->where('appointment_status_id', '!=', $cancelado))
            ->where('fim', '>', $inicio)
            ->where('inicio', '<', $fim);

        $doProfissional = (clone $base)
            ->where('profissional_id', $profissionalId)
            ->with('paciente:id,nome')
            ->first();

        if ($doProfissional !== null) {
            $conflitos['profissional'][] = 'O profissional já possui atendimento neste horário ('
                .$doProfissional->inicio->format('H:i').'–'.$doProfissional->fim->format('H:i')
                .' — '.($doProfissional->paciente?->nome ?? 'paciente').').';
        }

        $doPaciente = (clone $base)
            ->where('paciente_id', $pacienteId)
            ->with('profissional:id,nome')
            ->first();

        if ($doPaciente !== null) {
            $conflitos['paciente'][] = 'O paciente já possui atendimento neste horário ('
                .$doPaciente->inicio->format('H:i').'–'.$doPaciente->fim->format('H:i')
                .' — '.($doPaciente->profissional?->nome ?? 'profissional').').';
        }

        if ($roomId !== null) {
            $daSala = (clone $base)
                ->where('room_id', $roomId)
                ->with('profissional:id,nome')
                ->first();

            if ($daSala !== null) {
                $conflitos['sala'][] = 'A sala já está ocupada neste horário ('
                    .$daSala->inicio->format('H:i').'–'.$daSala->fim->format('H:i')
                    .' — '.($daSala->profissional?->nome ?? 'profissional').').';
            }
        }

        return $conflitos;
    }

    /**
     * Horários em que o profissional está livre para a duração pedida.
     *
     * Usa a grade cadastrada em `professional_schedules`; quando ela não
     * existe, usa o intervalo padrão da clínica. Nunca inventa dias: se o
     * profissional não atende no dia da semana, a lista volta vazia.
     *
     * @return list<string> Horários no formato H:i.
     */
    public function horariosDisponiveis(
        int $profissionalId,
        Carbon $dia,
        int $duracao = 30,
        ?Appointment $ignorar = null,
    ): array {
        $duracao = max(15, $duracao);
        $grades = ProfessionalSchedule::query()
            ->where('professional_id', $profissionalId)
            ->where('weekday', $dia->dayOfWeek)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();

        if ($grades->isEmpty()) {
            $grades = collect([(object) [
                'start_time' => self::ABERTURA_PADRAO,
                'end_time' => self::FECHAMENTO_PADRAO,
            ]]);
        }

        $cancelado = AppointmentStatus::query()->where('name', 'cancelado')->value('id');

        $ocupados = Appointment::query()
            ->where('profissional_id', $profissionalId)
            ->whereDate('inicio', $dia->toDateString())
            ->when($ignorar !== null, fn ($query) => $query->whereKeyNot($ignorar->getKey()))
            ->when($cancelado !== null, fn ($query) => $query->where('appointment_status_id', '!=', $cancelado))
            ->get(['inicio', 'fim']);

        $horarios = [];

        foreach ($grades as $grade) {
            $inicio = $dia->copy()->setTimeFromTimeString((string) $grade->start_time);
            $fim = $dia->copy()->setTimeFromTimeString((string) $grade->end_time);

            for ($slot = $inicio->copy(); $slot->copy()->addMinutes($duracao)->lte($fim); $slot->addMinutes(self::PASSO_MINUTOS)) {
                if ($slot->isPast() && $dia->isToday()) {
                    continue;
                }

                $slotFim = $slot->copy()->addMinutes($duracao);

                $colide = $ocupados->contains(
                    fn (Appointment $ocupado): bool => $ocupado->inicio->lt($slotFim) && $ocupado->fim->gt($slot)
                );

                if (! $colide) {
                    $horarios[] = $slot->format('H:i');
                }
            }
        }

        $horarios = array_values(array_unique($horarios));
        sort($horarios);

        return $horarios;
    }

    /**
     * Reagenda um atendimento existente, recusando conflito.
     *
     * @throws AgendaConflito
     */
    public function reagendar(
        Appointment $consulta,
        Carbon $inicio,
        int $duracao,
        ?int $profissionalId = null,
        ?int $roomId = null,
    ): Appointment {
        $fim = $inicio->copy()->addMinutes($duracao);
        $profissionalId ??= (int) $consulta->profissional_id;
        $roomId ??= $consulta->room_id !== null ? (int) $consulta->room_id : null;

        $conflitos = $this->conflitos(
            $inicio,
            $fim,
            $profissionalId,
            (int) $consulta->paciente_id,
            $roomId,
            $consulta,
        );

        if ($conflitos !== []) {
            throw new AgendaConflito($conflitos);
        }

        $consulta->update([
            'profissional_id' => $profissionalId,
            'room_id' => $roomId,
            'inicio' => $inicio,
            'fim' => $fim,
        ]);

        return $consulta->refresh();
    }

    public function statusId(string $nome): ?int
    {
        $id = AppointmentStatus::query()->where('name', $nome)->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Status padrão para um atendimento criado pela recepção.
     */
    public function statusAgendado(): ?int
    {
        return $this->statusId('agendado') ?? AppointmentStatus::query()->orderBy('id')->value('id');
    }

    /**
     * Próximos atendimentos de um profissional, para o painel do dia.
     *
     * @return Collection<int, Appointment>
     */
    public function agendaDoDia(Carbon $dia, ?int $profissionalId = null): Collection
    {
        return Appointment::query()
            ->with(['paciente:id,nome', 'profissional:id,nome', 'room:id,nome', 'status'])
            ->whereDate('inicio', $dia->toDateString())
            ->when($profissionalId !== null && $profissionalId > 0, fn ($query) => $query->where('profissional_id', $profissionalId))
            ->orderBy('inicio')
            ->get();
    }

    /**
     * Salas ativas, usadas no formulário de agendamento.
     *
     * @return Collection<int, Room>
     */
    public function salas(): Collection
    {
        return Room::query()->where('is_active', true)->orderBy('nome')->get();
    }
}
