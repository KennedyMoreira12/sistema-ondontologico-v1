<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission(
            $this->route('fornecedor') ? 'financeiro.update' : 'financeiro.create'
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:150'],
            'documento' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'contato' => ['nullable', 'string', 'max:120'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'tipo_documento' => ['required', Rule::in(['nf', 'boleto', 'duplicata', 'outros'])],
            'observacoes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
