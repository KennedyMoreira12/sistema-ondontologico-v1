<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TratamentoPlanoUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('tratamentos.update');
    }

    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:200'],
            'descricao' => ['nullable', 'string', 'max:2000'],
            'desconto' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'status' => ['sometimes', 'string', Rule::in(['rascunho', 'aprovado', 'em_andamento', 'concluido', 'cancelado'])],
        ];
    }
}
