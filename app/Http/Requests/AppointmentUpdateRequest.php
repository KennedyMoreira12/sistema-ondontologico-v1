<?php

namespace App\Http\Requests;

use App\Models\Appointment;
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
            'paciente_id' => ['required', 'exists:pacientes,id'],
            'profissional_id' => ['required', 'exists:professionals,id'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'dia' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'duracao' => ['required', 'integer', 'in:15,30,45,60,90,120'],
            'appointment_status_id' => ['required', Rule::exists('appointment_statuses', 'id')],
            'titulo' => ['nullable', 'string', 'max:255'],
            'observacao' => ['nullable', 'string', 'max:1000'],
            'retorno' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $inicio = $this->date('dia')->setTimeFromTimeString($this->string('hora_inicio'));
            $fim = $inicio->copy()->addMinutes((int) $this->integer('duracao'));

            $conflito = Appointment::where('profissional_id', $this->integer('profissional_id'))
                ->where('id', '!=', $this->consulta->id)
                ->where('appointment_status_id', '!=', 5)
                ->where('fim', '>', $inicio)
                ->where('inicio', '<', $fim)
                ->exists();

            $pacienteOcupado = Appointment::where('paciente_id', $this->integer('paciente_id'))
                ->where('id', '!=', $this->consulta->id)
                ->where('appointment_status_id', '!=', 5)
                ->where('fim', '>', $inicio)
                ->where('inicio', '<', $fim)
                ->exists();

            if ($conflito) {
                $validator->errors()->add('hora_inicio', 'O profissional já possui uma consulta neste horário.');
            }

            if ($pacienteOcupado) {
                $validator->errors()->add('dia', 'O paciente já possui uma consulta neste horário.');
            }
        });
    }
}
