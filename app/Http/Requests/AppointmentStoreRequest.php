<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Services\Agenda\AgendaService;
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
            'area' => ['nullable', 'string', 'max:80'],
            'servico' => ['nullable', 'string', 'max:120'],
            'valor' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'observacao' => ['nullable', 'string', 'max:1000'],
            'retorno' => ['nullable', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $inicio = $this->date('dia')->setTimeFromTimeString($this->string('hora_inicio'));
            $fim = $inicio->copy()->addMinutes((int) $this->integer('duracao'));

            /*
             * Um reenvio do mesmo formulário chega com a mesma chave e o
             * horário já está ocupado pela consulta que o primeiro envio criou.
             * Sem ignorar essa consulta, o retry morreria como "horário
             * ocupado" em vez de devolver o agendamento já existente.
             */
            $chave = $this->input('idempotency_key');
            $jaCriada = is_string($chave) && $chave !== ''
                ? Appointment::query()->where('idempotency_key', $chave)->first()
                : null;

            $conflitos = app(AgendaService::class)->conflitos(
                $inicio,
                $fim,
                $this->integer('profissional_id'),
                $this->integer('paciente_id'),
                $this->integer('room_id') ?: null,
                $jaCriada,
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
