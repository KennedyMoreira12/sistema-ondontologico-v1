<?php

namespace App\Http\Requests;

use App\Models\AccountsPayable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountsPayableStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission(
            $this->route('conta') ? 'financeiro.update' : 'financeiro.create'
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['nullable', 'integer', Rule::exists('tenant.suppliers', 'id')],
            'financeiro_categoria_id' => ['nullable', 'integer', Rule::exists('tenant.financeiro_categorias', 'id')],
            'nome_conta' => ['required', 'string', 'max:150'],
            'descricao' => ['nullable', 'string', 'max:1000'],
            'valor' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'data_vencimento' => ['required', 'date'],
            'tipo' => ['required', Rule::in(AccountsPayable::TIPOS)],
            'tipo_documento' => ['required', Rule::in(AccountsPayable::TIPOS_DOCUMENTO)],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
