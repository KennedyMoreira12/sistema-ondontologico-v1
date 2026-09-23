<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrescricaoStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('prontuario.create');
    }

    public function rules(): array
    {
        return [
            'data' => ['nullable', 'date'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'items' => ['nullable', 'array', 'max:20'],
            'items.*.medicamento' => ['required', 'string', 'max:255'],
            'items.*.dose' => ['nullable', 'string', 'max:100'],
            'items.*.frequencia' => ['nullable', 'string', 'max:100'],
            'items.*.duracao' => ['nullable', 'string', 'max:100'],
            'items.*.observacao' => ['nullable', 'string', 'max:500'],
        ];
    }
}
