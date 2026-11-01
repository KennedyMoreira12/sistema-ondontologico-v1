<?php

namespace App\Http\Requests\Platform;

use App\Models\Central\AdminUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Atualização de uma oportunidade pelo painel.
 *
 * A etapa do funil é validada contra `prospeccao.statuses` (a mesma lista que o
 * importador usa), então um valor escrito à mão não entra nem por request
 * forjado. Só super admin passa — a rota também tem o middleware `superadmin`,
 * e o `authorize()` cobre o caso do Form Request ser reaproveitado.
 */
class ProspectUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->admin()?->isSuperAdmin() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_keys((array) config('prospeccao.statuses')))],
            'priority' => ['nullable', 'string', Rule::in(array_keys((array) config('prospeccao.priorities')))],
            'channel' => ['nullable', 'string', Rule::in(array_keys((array) config('prospeccao.channels')))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'result' => ['nullable', 'string', 'max:255'],
            'next_follow_up_at' => ['nullable', 'date'],
            'disinterest_reason' => ['nullable', 'required_if:status,sem_interesse', 'string', 'max:1000'],
            'validated' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Etapa do funil inválida.',
            'priority.in' => 'Prioridade inválida.',
            'channel.in' => 'Canal de contato inválido.',
            'disinterest_reason.required_if' => 'Informe o motivo ao marcar como sem interesse.',
        ];
    }

    protected function admin(): ?AdminUser
    {
        $admin = $this->user();

        return $admin instanceof AdminUser ? $admin : null;
    }
}
