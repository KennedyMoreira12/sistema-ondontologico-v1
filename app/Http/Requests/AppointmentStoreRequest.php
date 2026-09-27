<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Models\AppointmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AppointmentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('agenda.create');
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

            $cancelado = AppointmentStatus::where('name', 'cancelado')->value('id');

            $conflito = Appointment::where('profissional_id', $this->integer('profissional_id'))
                ->where('appointment_status_id', '!=', $cancelado)
                ->where('fim', '>', $inicio)
                ->where('inicio', '<', $fim)
                ->exists();

            $pacienteOcupado = Appointment::where('paciente_id', $this->integer('paciente_id'))
                ->where('appointment_status_id', '!=', $cancelado)
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
