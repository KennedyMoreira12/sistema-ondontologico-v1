<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('admin.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role_ids' => ['required', 'array', 'min:1'],
            // roles vive no banco da clínica, então a validação precisa do
            // prefixo de conexão. Sem isso o Laravel procura a tabela no banco
            // central, onde ela não existe.
            'role_ids.*' => ['integer', Rule::exists('tenant.roles', 'id')],
            'cpf' => ['nullable', 'string', 'max:14', 'unique:users,cpf'],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role_ids.required' => 'Escolha ao menos um perfil de acesso.',
            'role_ids.*.exists' => 'Um dos perfis selecionados não existe nesta clínica.',
        ];
    }
}
