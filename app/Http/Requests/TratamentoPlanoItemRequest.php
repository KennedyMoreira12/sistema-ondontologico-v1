<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TratamentoPlanoItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('tratamentos.create')
            || $this->user()->hasPermission('tratamentos.update');
    }

    /**
     * @return array<int, int>
     */
    public function dentesFdi(): array
    {
        return array_merge(
            range(11, 18),
            range(21, 28),
            range(31, 38),
            range(41, 48)
        );
    }

    public function rules(): array
    {
        return [
            'procedure_id' => ['required', 'integer', 'exists:tenant.procedures,id'],
            'dente' => ['nullable', 'integer', Rule::in($this->dentesFdi())],
            'quantidade' => ['required', 'integer', 'min:1', 'max:999'],
            'valor_unitario' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'desconto' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'observacao' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'string', Rule::in(['planejado', 'executado'])],
        ];
    }
}
