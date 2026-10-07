<?php

namespace App\Http\Requests;

use App\Support\Odontograma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OdontogramConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('odontograma.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $dente = (int) $this->route('dente');
        $superficies = Odontograma::superficiesDoDente($dente);

        return [
            'tooth_condition_id' => ['required', 'integer', 'exists:tenant.tooth_conditions,id'],
            'superficie' => ['nullable', 'string', Rule::in($superficies)],
            'camada' => ['required', 'string', Rule::in(array_keys(config('odontograma.camadas', [])))],
            'descricao' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
