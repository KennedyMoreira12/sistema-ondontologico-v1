<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PlanStoreRequest;
use App\Http\Requests\Platform\PlanUpdateRequest;
use App\Models\Central\Plan;
use App\Services\Platform\PlatformLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Planos comerciais da plataforma.
 *
 * O que cada plano inclui vive em `plan_features`, não em código: mudar o que
 * está incluso é decisão comercial e vale entrar no ar sem deploy. A lista de
 * recursos EM SI (quais nomes existem) vem de `tenancy.features`, que é o que o
 * CheckPlanFeature consulta ao bloquear uma rota.
 */
class PlanController extends Controller
{
    public function index(): View
    {
        return view('platform.planos.index', [
            'planos' => Plan::query()
                ->with('features')
                ->withCount('subscriptions')
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('platform.planos.create', [
            'recursoLabels' => config('tenancy.features'),
            'limitesPorRecurso' => config('tenancy.feature_limits'),
        ]);
    }

    public function store(PlanStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $plan = Plan::query()->create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'price_monthly' => $validated['price_monthly'],
            'trial_days' => $validated['trial_days'],
            'sort_order' => $validated['sort_order'],
            'is_active' => $request->boolean('is_active'),
            'is_featured' => $request->boolean('is_featured'),
        ]);

        $this->syncFeatures($plan, $validated['features'] ?? []);

        PlatformLogger::log('plano.criado', 'plan', $plan->id, [
            'plano' => $plan->name,
            'recursos' => array_keys($plan->fresh()->featureMap()),
        ]);

        return redirect()
            ->route('painel.planos.index')
            ->with('success', "Plano {$plan->name} criado.");
    }

    public function edit(Plan $plan): View
    {
        return view('platform.planos.edit', [
            'plano' => $plan->load('features'),
            'recursoLabels' => config('tenancy.features'),
            'limitesPorRecurso' => config('tenancy.feature_limits'),
        ]);
    }

    public function update(PlanUpdateRequest $request, Plan $plan): RedirectResponse
    {
        $validated = $request->validated();

        $antes = $plan->only(array_keys(array_diff_key($validated, ['features' => true])));

        $plan->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'price_monthly' => $validated['price_monthly'],
            'trial_days' => $validated['trial_days'],
            'sort_order' => $validated['sort_order'],
            'is_active' => $request->boolean('is_active'),
            'is_featured' => $request->boolean('is_featured'),
        ]);

        $this->syncFeatures($plan, $validated['features'] ?? []);

        PlatformLogger::log('plano.atualizado', 'plan', $plan->id, [
            'antes' => $antes,
            'depois' => $plan->fresh()->only(array_keys($antes)),
            'recursos' => array_keys($plan->featureMap()),
        ]);

        return redirect()
            ->route('painel.planos.index')
            ->with('success', "Plano {$plan->name} atualizado.");
    }

    /**
     * Apagar um plano que tem assinatura derruba junto as clínicas
     * (`subscriptions.plan_id` é restrictOnDelete). Neste caso a resposta é
     * recusar com um número, que é o que o operador precisa ver antes de decidir
     * desativar o plano em vez de apagá-lo.
     */
    public function destroy(Plan $plan): RedirectResponse
    {
        abort_unless(auth()->user()?->isSuperAdmin() === true, 403, 'Apenas o super admin pode alterar planos.');

        $assinaturas = $plan->subscriptions()->count();

        if ($assinaturas > 0) {
            return back()->with(
                'error',
                "Este plano tem {$assinaturas} assinatura(s). Desative-o em vez de apagar."
            );
        }

        $nome = $plan->name;

        $plan->delete();

        PlatformLogger::log('plano.excluido', 'plan', $plan->id, ['plano' => $nome]);

        return redirect()
            ->route('painel.planos.index')
            ->with('success', "Plano {$nome} excluído.");
    }

    /**
     * `features[whatsapp][habilitado]` marcado grava a linha; sem `limite_*` o
     * recurso entra ligado sem contagem. Recurso desmarcado é removido, para que
     * desligar um recurso no formulário não deixe a linha velha valendo.
     *
     * @param  array<string, array<string, mixed>>  $features
     */
    protected function syncFeatures(Plan $plan, array $features): void
    {
        $plan->features()->delete();

        foreach ($features as $recurso => $opcoes) {
            if (! $opcoes['habilitado'] ?? false) {
                continue;
            }

            $limites = collect($opcoes)
                ->filter(fn (mixed $valor, string $chave): bool => str_starts_with($chave, 'limite_'))
                // O prefixo `limite_` é do formulário: o que vai para o banco é o
                // nome do limite puro (`posts_per_month`), que é como
                // `Plan::featureLimits()` o lê.
                ->mapWithKeys(fn (mixed $valor, string $chave): array => [
                    substr($chave, strlen('limite_')) => (float) $valor,
                ])
                ->all();

            $plan->features()->create([
                'feature' => $recurso,
                'limits' => $limites === [] ? null : $limites,
            ]);
        }
    }
}
