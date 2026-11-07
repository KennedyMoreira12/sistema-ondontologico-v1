<?php

namespace App\Http\Requests\Platform;

use App\Models\Central\AdminUser;
use App\Models\Central\Clinic;
use App\Services\Tenant\VerticalManager;
use App\Support\EvolutionInstance;
use App\Support\EvolutionToken;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClinicUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->admin()?->isSuperAdmin() === true;
    }

    /**
     * @see EvolutionInstance::normalize()
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

        /**
         * O formulário novo manda `verticals_present` e a lista de verticais
         * habilitadas. A vertical primária entra sempre na lista.
         */
        if ($this->boolean('verticals_present')) {
            $verticals = $this->input('verticals', []);
            $verticals = is_array($verticals)
                ? array_filter($verticals, fn (mixed $vertical): bool => is_string($vertical))
                : [];

            $primaria = $this->input('vertical');

            if (! is_string($primaria) || $primaria === '') {
                $primaria = $this->route('clinic')?->vertical ?: VerticalManager::ODONTOLOGIA;
            }

            $this->merge([
                'verticals' => array_values(array_unique([...$verticals, $primaria])),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Clinic $clinic */
        $clinic = $this->route('clinic');

        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'document' => ['nullable', 'string', 'max:30'],
            'email' => [
                'required', 'email', 'max:191',
                Rule::unique('clinics', 'email')->ignore($clinic->id),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'evolution_instance' => EvolutionInstance::rules($clinic->id),

            /**
             * Só entra no `validated()` quando veio algo. O campo aparece vazio
             * em toda edição — o token nunca é reidigitado, porque a Evolution
             * não o mostra de novo — e um `nullable` que devolvesse `null`
             * apagaria o token de uma clínica que só queria trocar o nome.
             *
             * @see evolutionTokenUpdate()
             */
            'evolution_token' => EvolutionToken::rules(),

            'timezone' => ['required', 'timezone'],
            'status' => ['required', Rule::in(array_keys(config('tenancy.clinic_states')))],

            /**
             * A vertical define quais módulos a clínica enxerga. Trocá-la não
             * apaga nada: as tabelas de odontologia continuam lá para a clínica
             * de fisioterapia, e as de fisioterapia para a odontológica.
             */
            'vertical' => ['nullable', Rule::in(VerticalManager::VERTICAIS)],
            'verticals' => ['nullable', 'array'],
            'verticals.*' => ['string', Rule::in(VerticalManager::VERTICAIS)],

            'is_active' => ['nullable', 'boolean'],
            'suspension_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * O token a gravar, ou nada.
     *
     * @return array{evolution_token?: string}
     */
    public function evolutionTokenUpdate(): array
    {
        $token = $this->validated()['evolution_token'] ?? null;

        return $token === null ? [] : ['evolution_token' => $token];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Estado de clínica inválido.',
            'timezone.timezone' => 'Fuso horário inválido.',
            'vertical.in' => 'Vertical inválida.',
            'verticals.*.in' => 'Vertical habilitada inválida.',
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
