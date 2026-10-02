<?php

namespace App\Http\Requests\Platform;

use App\Models\Central\AdminUser;
use App\Models\Central\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PlanUpdateRequest extends FormRequest
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
        /** @var Plan $plan */
        $plan = $this->route('plan');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:191', 'alpha_dash',
                Rule::unique('plans', 'slug')->ignore($plan->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'price_monthly' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'features' => ['nullable', 'array'],
            'features.*.habilitado' => ['nullable', 'boolean'],
            'features.*.limite_*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * O nome do recurso é uma CHAVE de `features`, e não um valor: `Rule::in` em
     * `features.*` compararia o sub-array inteiro (`habilitado`, `limite_*`) com
     * a lista de recursos e reprovaria até o recurso válido. Aqui cada chave é
     * conferida contra `tenancy.features`, que é a lista que o CheckPlanFeature
     * consulta ao bloquear uma rota.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ($this->recursosInformados() as $recurso) {
                    if (! array_key_exists($recurso, config('tenancy.features'))) {
                        $validator->errors()->add(
                            "features.{$recurso}",
                            'Recurso desconhecido: o nome precisa existir na lista de recursos da plataforma.',
                        );
                    }
                }
            },
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function recursosInformados(): array
    {
        return array_keys((array) $this->input('features', []));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.alpha_dash' => 'O slug aceita apenas letras, números, hífen e underscore.',
        ];
    }

    protected function admin(): ?AdminUser
    {
        $admin = $this->user();

        return $admin instanceof AdminUser ? $admin : null;
    }
}
