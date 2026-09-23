<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClinicalNoteStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('prontuario.create');
    }

    public function rules(): array
    {
        return [
            'tipo' => ['nullable', 'string', 'max:30'],
            'conteudo' => ['required', 'string', 'max:10000'],
        ];
    }
}
