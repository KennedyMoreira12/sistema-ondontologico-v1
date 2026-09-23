<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OdontogramToothRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('odontograma.update');
    }

    public function rules(): array
    {
        return [
            'dente' => [
                'required',
                'integer',
                Rule::in(array_merge(range(11, 18), range(21, 28), range(31, 38), range(41, 48))),
            ],
            'tooth_condition_id' => ['required', 'integer', 'exists:tooth_conditions,id'],
            'observacao' => ['nullable', 'string', 'max:500'],
        ];
    }
}
