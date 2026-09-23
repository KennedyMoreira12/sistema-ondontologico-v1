<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TratamentoPlanoStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('tratamentos.create');
    }

    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:200'],
            'descricao' => ['nullable', 'string', 'max:2000'],
            'desconto' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }
}
