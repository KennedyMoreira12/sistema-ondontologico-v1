<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('admin.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $usuario = $this->route('usuario');

        return [
            'name' => ['required', 'string', 'max:255'],
            // `ignore` precisa receber o id do usuário que está sendo EDITADO.
            // `$this->user()` devolve quem está logado, o que faria a validação
            // ignorar o e-mail do admin em vez do e-mail do alvo.
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($usuario)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', Rule::exists('tenant.roles', 'id')],
            'cpf' => ['nullable', 'string', 'max:14', Rule::unique('users', 'cpf')->ignore($usuario)],
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
