<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Central\Clinic;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Services\Platform\PlatformLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Assinaturas: quem está pagando o quê, e por quanto tempo.
 *
 * A troca de plano NÃO edita a assinatura antiga. Ela marca a vigente como
 * encerrada e abre uma nova, porque o histórico de quanto a clínica pagou mês a
 * mês é o que responde "por que a fatura de março era 149 e a de abril 450?".
 */
class SubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $subscriptions = Subscription::query()
            ->with(['clinic', 'plan'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('busca'), function ($query) use ($request): void {
                $termo = '%'.$request->string('busca').'%';

                $query->whereHas('clinic', fn ($query) => $query->where('name', 'like', $termo));
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('platform.assinaturas.index', [
            'assinaturas' => $subscriptions,
            'estados' => config('tenancy.subscription_states'),
            'status' => $request->string('status')->toString(),
            'busca' => $request->string('busca')->toString(),
            'assinaturasAtivas' => Subscription::query()->current()->count(),
            'receitaMensal' => (float) Subscription::query()
                ->current()
                ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
                ->sum('plans.price_monthly'),
        ]);
    }

    /**
     * Abre uma assinatura nova para a clínica e encerra a que estiver em vigor.
     */
    public function store(Request $request, Clinic $clinic): RedirectResponse
    {
        abort_unless(auth()->user()?->isSuperAdmin() === true, 403, 'Apenas o super admin pode alterar assinaturas.');

        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'current_period_end' => ['nullable', 'date', 'after:today'],
        ]);

        $plan = Plan::query()->findOrFail($validated['plan_id']);

        if (! $plan->is_active) {
            return back()->with('error', 'Não é possível assinar um plano inativo.');
        }

        $subscription = DB::transaction(function () use ($clinic, $plan, $validated): Subscription {
            $clinic->subscriptions()
                ->current()
                ->get()
                ->each(fn (Subscription $atual) => $atual->forceFill([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'ended_at' => now(),
                ])->save());

            $fim = isset($validated['current_period_end'])
                ? Carbon::parse($validated['current_period_end'])
                : now()->addMonth();

            return $clinic->subscriptions()->create([
                'plan_id' => $plan->id,
                'status' => 'active',
                'started_at' => now(),
                'current_period_start' => now(),
                'current_period_end' => $fim,
            ]);
        });

        PlatformLogger::log('assinatura.criada', 'subscription', $subscription->id, [
            'plano' => $plan->name,
            'ate' => $subscription->current_period_end?->toDateString(),
        ], $clinic);

        return redirect()
            ->route('painel.clinicas.show', $clinic)
            ->with('success', "Assinatura do plano {$plan->name} criada.");
    }

    /**
     * Estende o período mais um mês, contando do vencimento atual. Renovar uma
     * assinatura vencida há três meses tem que voltar a valer por um mês a
     * partir de HOJE, senão o cliente paga e continua bloqueado.
     */
    public function renew(Subscription $subscription): RedirectResponse
    {
        abort_unless(auth()->user()?->isSuperAdmin() === true, 403, 'Apenas o super admin pode alterar assinaturas.');

        $subscription->loadMissing('clinic');

        $subscription->forceFill([
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => $subscription->isExpired()
                ? now()->addMonth()
                : $subscription->current_period_end->copy()->addMonth(),
            'grace_ends_at' => null,
        ])->save();

        PlatformLogger::log('assinatura.renovada', 'subscription', $subscription->id, [
            'ate' => $subscription->current_period_end?->toDateString(),
        ], $subscription->clinic);

        return back()->with('success', 'Assinatura renovada por mais um mês.');
    }

    /**
     * Muda o estado da assinatura e reflects na clínica, porque é a clínica que o
     * `ResolveTenant` lê para liberar o painel. Mudar só a assinatura deixaria a
     * clínica entrando com `status = active` e assinatura suspensa.
     */
    public function status(Request $request, Subscription $subscription): RedirectResponse
    {
        abort_unless(auth()->user()?->isSuperAdmin() === true, 403, 'Apenas o super admin pode alterar assinaturas.');

        $validated = $request->validate([
            'status' => ['required', 'in:trialing,active,past_due,suspended,cancelled,expired'],
            'grace_days' => ['nullable', 'integer', 'min:0', 'max:90'],
        ]);

        $subscription->loadMissing('clinic');

        $emAtraso = $validated['status'] === 'past_due';

        $subscription->forceFill([
            'status' => $validated['status'],
            'grace_ends_at' => $emAtraso
                ? now()->addDays((int) ($validated['grace_days'] ?? config('tenancy.grace_period_days')))
                : null,
            'cancelled_at' => $validated['status'] === 'cancelled' ? now() : null,
        ])->save();

        $this->refletirNaClinica($subscription);

        PlatformLogger::log('assinatura.status_alterado', 'subscription', $subscription->id, [
            'status' => $subscription->status,
        ], $subscription->clinic);

        return back()->with('success', 'Estado da assinatura atualizado.');
    }

    /**
     * @return array<string, string>
     */
    protected function clinicaStatePorAssinatura(string $status): array
    {
        return match ($status) {
            'active' => ['status' => 'active', 'is_active' => true, 'trial_ends_at' => null],
            'trialing' => ['status' => 'trial', 'is_active' => true],
            'past_due' => ['status' => 'past_due', 'is_active' => true],
            'suspended' => ['status' => 'suspended', 'is_active' => false, 'suspended_at' => now()],
            default => ['status' => 'cancelled', 'is_active' => false, 'suspended_at' => now()],
        };
    }

    protected function refletirNaClinica(Subscription $subscription): void
    {
        $clinic = $subscription->clinic;

        if ($clinic === null) {
            return;
        }

        $clinic->update($this->clinicaStatePorAssinatura($subscription->status));
    }
}
