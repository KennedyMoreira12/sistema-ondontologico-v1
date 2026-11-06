<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Vínculo manual de um pagamento importado com um paciente.
 *
 * A decisão é sempre humana: o sistema sugere candidatos, mas não vincula
 * sozinho. Por isso o request exige um paciente existente e o controller
 * recusa sobrescrever um vínculo já feito.
 */
class PaymentLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('financeiro.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'paciente_id' => ['required', 'integer', Rule::exists('tenant.pacientes', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'paciente_id.required' => 'Escolha o paciente que receberá o vínculo.',
            'paciente_id.exists' => 'O paciente escolhido não existe nesta clínica.',
        ];
    }
}
