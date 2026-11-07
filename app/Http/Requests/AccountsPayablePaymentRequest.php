<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountsPayablePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('financeiro.receive');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'valor' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('tenant.payment_methods', 'id')],
            'data' => ['required', 'date'],
            'observacoes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }
}
