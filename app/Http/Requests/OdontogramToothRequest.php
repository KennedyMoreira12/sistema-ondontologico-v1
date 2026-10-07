<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OdontogramToothRequest extends FormRequest
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
        return [
            'status' => ['sometimes', 'string', Rule::in(array_keys(config('odontograma.status_dente', [])))],
            'observacao' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
