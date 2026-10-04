<?php

namespace App\Http\Requests\Platform;

use App\Models\Central\AdminUser;
use App\Support\EvolutionInstance;
use App\Support\EvolutionToken;
use Illuminate\Foundation\Http\FormRequest;

class ClinicStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->admin()?->isSuperAdmin() === true;
    }

    /**
     * A instância da Evolution é guardada já minusculizada: o `CLINICAS_MAP`
     * do n8n é sensível a caixa, e "Clinica-Marlene" lá e "clinica-marlene"
     * aqui é mensagem descartada sem erro.
     *
     * O token não é minusculizado — a Evolution gera string opaca e mexer nas
     * Maiúsculas dela troca o segredo por outro — mas o espaço em volta é
     * aparado, porque colar com espaço copia da tela dá 401 em toda chamada.
     *
     * @see EvolutionToken::normalize()
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('evolution_instance')) {
            $this->merge([
                'evolution_instance' => EvolutionInstance::normalize($this->input('evolution_instance')),
            ]);
        }

        if ($this->has('evolution_token')) {
            $this->merge([
                'evolution_token' => EvolutionToken::normalize($this->input('evolution_token')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'document' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:191', 'unique:clinics,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'evolution_instance' => EvolutionInstance::rules(),
            'evolution_token' => EvolutionToken::rules(),
            'plan_id' => ['required', 'integer', 'exists:plans,id'],

            /**
             * Cadastro do administrador da clínica. O e-mail pode repetir o da
             * clínica (é o contato principal), mas a unicidade é do `users`,
             * que é onde a sessão de login é montada.
             */
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan_id.exists' => 'Escolha um plano ativo para a clínica.',
            'admin_email.unique' => 'Já existe um usuário com este e-mail.',
            ...EvolutionInstance::messages(),
            ...EvolutionToken::messages(),
        ];
    }

    protected function admin(): ?AdminUser
    {
        $admin = $this->user();

        return $admin instanceof AdminUser ? $admin : null;
    }
}
