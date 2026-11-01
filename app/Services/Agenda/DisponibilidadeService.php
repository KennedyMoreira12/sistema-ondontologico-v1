<?php

namespace App\Services\Agenda;

use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Procedure;
use App\Models\Professional;
use App\Models\ProfessionalSchedule;
use Illuminate\Support\Carbon as CarbonBase;

/**
 * Horários livres de um profissional, com o tamanho da consulta vindo do
 * procedimento escolhido.
 *
 * Existe como serviço, e não dentro do controller, porque dois lugares precisam
 * da MESMA conta: o endpoint que o painel/n8n consome e a máquina de estados do
 * WhatsApp. Duas implementações desse cálculo divergem na primeira regra nova —
 * uma delas passa a oferecer horário que não cabe no turno — e o defeito só
 * aparece como encaixe impossível na agenda.
 */
class DisponibilidadeService
{
    /**
     * @return list<array{profissional_id: int, profissional: string, data: string, dia_semana: string, duracao: int, horarios: list<array{hora: string}>}>
     */
    public function horariosLivres(int $profissionalId, int $duracao, int $dias = 7): array
    {
        $profissionais = Professional::where('is_active', true)
            ->when($profissionalId > 0, fn ($q) => $q->where('id', $profissionalId))
            ->orderBy('nome')
            ->get();

        $inicio = CarbonBase::today();
        $fim = CarbonBase::today()->addDays($dias);

        $agendamentos = Appointment::with('status')
            ->where('fim', '>', $inicio)
            ->where('inicio', '<', $fim->endOfDay())
            ->get();

        $resultado = [];

        foreach ($profissionais as $profissional) {
            foreach ($this->diasComAgenda($dias) as $dia) {
                $ocupados = $agendamentos
                    ->filter(fn (Appointment $a) => $a->profissional_id === $profissional->id
                        && $a->status?->name !== AppointmentStatus::CANCELADO
                        && $a->inicio->toDateString() === $dia->toDateString())
                    ->map(fn (Appointment $a) => [
                        'inicio' => $a->inicio->timestamp,
                        'fim' => $a->fim->timestamp,
                    ])
                    ->values();

                $intervalos = ProfessionalSchedule::where('professional_id', $profissional->id)
                    ->where('weekday', $dia->dayOfWeek)
                    ->where('is_active', true)
                    ->get(['start_time', 'end_time']);

                $horarios = [];
                foreach ($intervalos as $intervalo) {
                    [$hIni, $mIni] = explode(':', $intervalo->start_time);
                    [$hFim, $mFim] = explode(':', $intervalo->end_time);
                    $slot = $dia->copy()->setTime((int) $hIni, (int) $mIni);
                    $fimDia = $dia->copy()->setTime((int) $hFim, (int) $mFim);

                    while (true) {
                        $fimSlot = $slot->copy()->addMinutes($duracao);

                        if ($fimSlot->gt($fimDia)) {
                            break;
                        }

                        $conflita = $ocupados->contains(
                            fn ($a) => $slot->timestamp < $a['fim'] && $fimSlot->timestamp > $a['inicio']
                        );

                        if (! $conflita) {
                            $horarios[] = ['hora' => $slot->format('H:i')];
                        }

                        $slot->addMinutes($duracao);
                    }
                }

                if ($horarios === []) {
                    continue;
                }

                $resultado[] = [
                    'profissional_id' => $profissional->id,
                    'profissional' => $profissional->nome,
                    'data' => $dia->toDateString(),
                    'dia_semana' => $dia->translatedFormat('l'),
                    'duracao' => $duracao,
                    'horarios' => $horarios,
                ];
            }
        }

        return $resultado;
    }

    /**
     * Duração do procedimento, lida do catálogo.
     *
     * O bot nunca decide o tamanho da consulta: se decidisse, trocar a duração de
     * um procedimento deixaria o WhatsApp marcando encaixes que a clínica não
     * atende mais.
     */
    public function duracaoDoProcedimento(int $procedimentoId): int
    {
        return (int) Procedure::whereKey($procedimentoId)->value('duracao_minutos');
    }

    /**
     * Horários livres de UM dia, já como lista de "H:i".
     *
     * Existe para o bot reconsultar a agenda no momento de oferecer os horários,
     * em vez de confiar na lista guardada no estado da conversa: entre a tela do
     * dia e a escolha do horário pode passar uma hora com a conversa aberta, e
     * nesse intervalo outra pessoa agenda o mesmo slot.
     *
     * @return list<string>
     */
    public function horariosDoDia(int $profissionalId, int $duracao, string $data, int $dias = 7): array
    {
        $dia = collect($this->horariosLivres($profissionalId, $duracao, $dias))
            ->firstWhere('data', $data);

        return array_values(array_map(
            fn (array $horario) => $horario['hora'],
            $dia['horarios'] ?? [],
        ));
    }

    /**
     * Os dias que a clínica pode atender, de hoje para frente.
     *
     * O domingo é descartado porque nenhuma agenda cadastrada o usa; quando a
     * clínica passar a atender domingo, é este método que some com o dia — e não
     * o chamador, que não deveria saber que domingo existe.
     *
     * @return list<CarbonBase>
     */
    protected function diasComAgenda(int $dias): array
    {
        $disponiveis = [];

        for ($i = 0; $i <= $dias; $i++) {
            $dia = CarbonBase::today()->addDays($i);

            if (! $dia->isSunday()) {
                $disponiveis[] = $dia;
            }
        }

        return $disponiveis;
    }
}
