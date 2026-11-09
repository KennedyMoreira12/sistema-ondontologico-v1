<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinanceiroPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('financeiro.receive');
    }

    public function rules(): array
    {
        return [
            'valor' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'desconto' => ['nullable', 'numeric', 'gte:0', 'max:99999999.99'],
            'payment_method_id' => ['required', 'integer', Rule::exists('tenant.payment_methods', 'id')],
            'data' => ['nullable', 'date'],
            'observacoes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
