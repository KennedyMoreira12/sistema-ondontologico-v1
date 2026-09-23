<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TratamentoPlanoStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('tratamentos.update');
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['rascunho', 'aprovado', 'em_andamento', 'concluido', 'cancelado'])],
        ];
    }
}
