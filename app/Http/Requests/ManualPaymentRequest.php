<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pagamento avulso lançado direto no financeiro.
 *
 * O lançamento cria uma conta a receber já quitada e o pagamento
 * correspondente na mesma transação, para que o caixa e o contas a receber
 * contem a mesma história.
 */
class ManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('financeiro.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'paciente_id' => ['required', 'integer', Rule::exists('tenant.pacientes', 'id')],
            'descricao' => ['required', 'string', 'max:150'],
            'servico' => ['nullable', 'string', 'max:120'],
            'valor' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'payment_method_id' => ['required', 'integer', Rule::exists('tenant.payment_methods', 'id')],
            'data' => ['required', 'date'],
            'observacoes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
