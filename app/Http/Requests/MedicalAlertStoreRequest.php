<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MedicalAlertStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('alertas.create');
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', 'string', 'max:50'],
            'descricao' => ['required', 'string', 'max:500'],
        ];
    }
}
