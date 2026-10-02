<?php

namespace App\Http\Requests\Platform;

use App\Models\Central\AdminUser;
use App\Models\Central\Clinic;
use App\Support\EvolutionInstance;
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
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('evolution_instance')) {
            $this->merge([
                'evolution_instance' => EvolutionInstance::normalize($this->input('evolution_instance')),
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
            'timezone' => ['required', 'timezone'],
            'status' => ['required', Rule::in(array_keys(config('tenancy.clinic_states')))],
            'is_active' => ['nullable', 'boolean'],
            'suspension_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Estado de clínica inválido.',
            'timezone.timezone' => 'Fuso horário inválido.',
        ];
    }

    protected function admin(): ?AdminUser
    {
        $admin = $this->user();

        return $admin instanceof AdminUser ? $admin : null;
    }
}
