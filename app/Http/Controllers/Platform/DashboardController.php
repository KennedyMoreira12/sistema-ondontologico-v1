<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Central\Clinic;
use App\Models\Central\Plan;
use App\Models\Central\PlatformAuditLog;
use App\Models\Central\Subscription;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('platform.dashboard', [
            'totalClinicas' => Clinic::query()->count(),
            'clinicasPorEstado' => $this->clinicasPorEstado(),
            'assinaturasAtivas' => Subscription::query()->current()->count(),
            'mrr' => $this->mrr(),
            'emAvaliacao' => Clinic::query()->where('status', 'trial')->count(),
            'planosAtivos' => Plan::query()->where('is_active', true)->count(),
            'ultimasClinicas' => Clinic::query()->latest()->limit(5)->get(),
            'ultimasAcoes' => PlatformAuditLog::query()
                ->with(['adminUser', 'clinic'])
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    /**
     * @return array<string, int>
     */
    protected function clinicasPorEstado(): array
    {
        $contagem = Clinic::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $porEstado = [];

        foreach (config('tenancy.clinic_states') as $estado => $rotulo) {
            $porEstado[$estado] = ['rotulo' => $rotulo, 'total' => (int) ($contagem[$estado] ?? 0)];
        }

        return $porEstado;
    }

    /**
     * Receita recorrente: só conta assinatura que está liberando acesso hoje.
     *
     * Somar o preço de toda assinatura da base inflaria o número com plano
     * cancelado e em atraso, que não paga nada.
     */
    protected function mrr(): float
    {
        return (float) Subscription::query()
            ->current()
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->sum('plans.price_monthly');
    }
}
