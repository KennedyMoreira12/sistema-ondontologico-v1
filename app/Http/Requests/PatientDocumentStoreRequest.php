<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PatientDocumentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('documentos.create');
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', 'string', 'max:50'],
            'titulo' => ['nullable', 'string', 'max:255'],
            'arquivo' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,xls,xlsx', 'max:10240'],
        ];
    }
}
