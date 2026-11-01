<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class ProspectCommercialRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->guard('admin')->check() && auth()->guard('admin')->user()->isSuperAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'prospect_id' => ['required', 'integer', 'exists:prospects,id'],
            'template_id' => ['required', 'integer', 'exists:prospect_message_templates,id'],
            'mensagem' => ['required', 'string', 'max:4096'],
            'acao' => ['required', 'string', 'in:preparar,aprovar_enviar,pausar_bot,retomar_bot'],
        ];
    }
}