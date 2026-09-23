<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnamneseStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('anamnese.create');
    }

    public function rules(): array
    {
        return [
            'titulo' => ['nullable', 'string', 'max:255'],
            'responsavel' => ['nullable', 'string', 'max:255'],
            'respostas' => ['nullable'],
        ];
    }
}
