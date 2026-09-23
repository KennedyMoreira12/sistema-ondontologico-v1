<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WaitingListStoreRequest extends FormRequest
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
            'paciente_id' => ['required', 'exists:pacientes,id'],
            'prioridade' => ['required', 'integer', 'between:1,5'],
            'periodo_inicio' => ['required', 'date'],
            'periodo_fim' => ['nullable', 'date', 'after_or_equal:periodo_inicio'],
            'motivo' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', 'max:20'],
        ];
    }
}
