<?php

namespace App\Http\Requests;

use App\Services\Agenda\AgendaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AppointmentUpdateRequest extends FormRequest
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
            'paciente_id' => ['required', 'exists:tenant.pacientes,id'],
            'profissional_id' => ['required', 'exists:tenant.professionals,id'],
            'room_id' => ['nullable', 'exists:tenant.rooms,id'],
            'dia' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'duracao' => ['required', 'integer', 'in:15,30,45,60,90,120'],
            'appointment_status_id' => ['required', Rule::exists('tenant.appointment_statuses', 'id')],
            'titulo' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:80'],
            'servico' => ['nullable', 'string', 'max:120'],
            'valor' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'observacao' => ['nullable', 'string', 'max:1000'],
            'retorno' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $inicio = $this->date('dia')->setTimeFromTimeString($this->string('hora_inicio'));
            $fim = $inicio->copy()->addMinutes((int) $this->integer('duracao'));

            $conflitos = app(AgendaService::class)->conflitos(
                $inicio,
                $fim,
                $this->integer('profissional_id'),
                $this->integer('paciente_id'),
                $this->integer('room_id') ?: null,
                $this->route('consulta'),
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
