<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Services\Agenda\AgendaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Reagendamento rápido: muda dia, hora, duração, profissional e sala sem
 * tocar no restante do atendimento.
 */
class AgendaRescheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('agenda.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dia' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'duracao' => ['required', 'integer', 'in:15,30,45,60,90,120'],
            'profissional_id' => ['nullable', 'exists:tenant.professionals,id'],
            'room_id' => ['nullable', 'exists:tenant.rooms,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Appointment $consulta */
            $consulta = $this->route('consulta');
            $inicio = $this->date('dia')->setTimeFromTimeString($this->string('hora_inicio'));
            $fim = $inicio->copy()->addMinutes((int) $this->integer('duracao'));

            $conflitos = app(AgendaService::class)->conflitos(
                $inicio,
                $fim,
                $this->integer('profissional_id') ?: (int) $consulta->profissional_id,
                (int) $consulta->paciente_id,
                $this->integer('room_id') ?: null,
                $consulta,
            );

            foreach ($conflitos as $campo => $mensagens) {
                $chave = match ($campo) {
                    'profissional' => 'hora_inicio',
                    'sala' => 'room_id',
                    default => 'dia',
                };

                foreach ($mensagens as $mensagem) {
                    $validator->errors()->add($chave, $mensagem);
                }
            }
        });
    }
}
