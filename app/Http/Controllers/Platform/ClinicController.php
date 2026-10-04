<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ClinicStoreRequest;
use App\Http\Requests\Platform\ClinicUpdateRequest;
use App\Models\Central\Clinic;
use App\Models\Central\IntegrationToken;
use App\Models\Central\Plan;
use App\Models\Central\PlatformAuditLog;
use App\Services\Platform\PlatformLogger;
use App\Services\Tenant\TenantDatabaseProvisioner;
use App\Services\Tenant\TenantProvisioner;
use App\Support\IntegrationAbilities;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Gestão de clínicas pelo painel da empresa.
 *
 * O provisionamento (criar banco, semear papéis, criar admin, abrir
 * assinatura) vive no TenantProvisioner e não aqui: o mesmo caminho serve o
 * `tenant:create` do artisan, e a tela e a linha de comando não podem divergir.
 */
class ClinicController extends Controller
{
    public function __construct(
        protected TenantProvisioner $provisioner,
        protected TenantDatabaseProvisioner $databases,
    ) {}

    public function index(Request $request): View
    {
        $clinics = Clinic::query()
            ->with(['tenantConnection', 'latestSubscription.plan'])
            ->when($request->filled('busca'), function ($query) use ($request): void {
                $termo = '%'.$request->string('busca').'%';

                $query->where(function ($query) use ($termo): void {
                    $query->where('name', 'like', $termo)
                        ->orWhere('email', 'like', $termo)
                        ->orWhere('document', 'like', $termo);
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('platform.clinicas.index', [
            'clinicas' => $clinics,
            'estados' => config('tenancy.clinic_states'),
            'busca' => $request->string('busca')->toString(),
            'status' => $request->string('status')->toString(),
        ]);
    }

    public function create(): View
    {
        return view('platform.clinicas.create', [
            'planos' => $this->planosAtivos(),
        ]);
    }

    public function store(ClinicStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $resultado = $this->provisioner->provision([
            ...$validated,
            'plan' => Plan::query()->findOrFail($validated['plan_id']),
        ]);

        PlatformLogger::log('clinica.criada', 'clinic', $resultado['clinic']->id, [
            'plano' => $resultado['subscription']->plan?->name,
            'banco' => $resultado['connection']->database_name,
        ], $resultado['clinic']);

        /**
         * A senha gerada volta uma única vez, na tela seguinte. Ela não é
         * guardada em log nem em sessão: repassar o acesso ao cliente é tarefa do
         * operador, por um canal que ele escolhe.
         */
        return redirect()
            ->route('painel.clinicas.show', $resultado['clinic'])
            ->with('senha_provisoria', $resultado['password']);
    }

    public function show(Clinic $clinic): View
    {
        return view('platform.clinicas.show', [
            'clinica' => $clinic->load([
                'tenantConnection',
                'users',
                'integrationTokens' => fn ($query) => $query->latest(),
            ]),
            'assinaturas' => $clinic->subscriptions()->with('plan')->latest()->get(),
            'acoes' => PlatformAuditLog::query()
                ->forClinic($clinic)
                ->with('adminUser')
                ->latest()
                ->limit(10)
                ->get(),
            'planos' => $this->planosAtivos(),
            'estados' => config('tenancy.clinic_states'),
        ]);
    }

    public function edit(Clinic $clinic): View
    {
        return view('platform.clinicas.edit', [
            'clinica' => $clinic,
            'estados' => config('tenancy.clinic_states'),
        ]);
    }

    public function update(ClinicUpdateRequest $request, Clinic $clinic): RedirectResponse
    {
        $antes = $clinic->only(array_keys($request->validated()));

        $clinic->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        PlatformLogger::log('clinica.atualizada', 'clinic', $clinic->id, [
            'antes' => $antes,
            'depois' => $clinic->fresh()->only(array_keys($antes)),
        ], $clinic);

        return redirect()
            ->route('painel.clinicas.show', $clinic)
            ->with('success', 'Dados da clínica atualizados.');
    }

    /**
     * Excluir a clínica é a única operação irreversível do painel, e ela leva
     * junto o banco com todo o histórico clínico. O padrão é preservar o banco:
     * desliga a conexão, revoga os tokens e arquiva o registro. O `descartar_banco`
     * explícito é que apaga de verdade.
     */
    public function destroy(Request $request, Clinic $clinic): RedirectResponse
    {
        $this->authorizePlatformAction();

        $validated = $request->validate([
            'descartar_banco' => ['nullable', 'boolean'],
        ]);

        $descartarBanco = (bool) ($validated['descartar_banco'] ?? false);
        $nome = $clinic->name;

        $clinic->update([
            'is_active' => false,
            'status' => 'cancelled',
            'suspended_at' => now(),
            'suspension_reason' => 'Excluída pelo painel da plataforma.',
        ]);

        $clinic->tenantConnection?->update(['is_active' => false]);

        $clinic->integrationTokens()->active()->get()->each->revoke();

        $clinic->usageMetrics()->delete();

        if ($descartarBanco) {
            $this->provisioner->destroy($clinic);
        } else {
            $clinic->delete();
        }

        PlatformLogger::log('clinica.excluida', 'clinic', $clinic->id, [
            'clinica' => $nome,
            'banco_descartado' => $descartarBanco,
        ], $clinic);

        return redirect()
            ->route('painel.clinicas.index')
            ->with('success', $descartarBanco
                ? "A clínica {$nome} e o banco dela foram removidos."
                : "A clínica {$nome} foi desativada. O banco dela foi preservado.");
    }

    /**
     * Atualiza o schema do banco de uma clínica. É o mesmo caminho do
     * `tenants:migrate` sem o `--all`: aqui a clínica é sempre a escolhida.
     */
    public function migrate(Clinic $clinic): RedirectResponse
    {
        $this->authorizePlatformAction();

        $this->databases->migrate($clinic->refresh());

        PlatformLogger::log('clinica.migrada', 'clinic', $clinic->id, [
            'banco' => $clinic->tenantConnection?->database_name,
        ], $clinic);

        return back()->with('success', 'Schema da clínica atualizado.');
    }

    /**
     * Botão de emergência: desliga ou religa a conexão sem derrubar o banco nem
     * apagar dado nenhum. Do lado da clínica vira 403 em todo acesso.
     */
    public function toggleConnection(Clinic $clinic): RedirectResponse
    {
        $this->authorizePlatformAction();

        $clinic->tenantConnection?->update(['is_active' => ! $clinic->tenantConnection->is_active]);

        PlatformLogger::log(
            $clinic->tenantConnection?->is_active ? 'clinica.conexao_religada' : 'clinica.conexao_desligada',
            'clinic',
            $clinic->id,
            null,
            $clinic
        );

        return back()->with('success', 'Conexão da clínica atualizada.');
    }

    /**
     * Gera um novo token de integração. O valor em claro aparece só aqui: o banco
     * guarda o SHA-256, então não há como recuperá-lo depois.
     *
     * As abilities são obrigatórias e explícitas. Deixá-las em branco seria
     * passar a receber `null` no banco, e token sem abilities não faz nada —
     * alguém que esperasse "um token novo já funciona" receberia um token morto
     * sem aviso, e alguém que quisesse tudo teria que marcar o wildcard.
     */
    public function storeToken(Request $request, Clinic $clinic): RedirectResponse
    {
        $this->authorizePlatformAction();

        $selectable = IntegrationAbilities::selectable();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', 'in:'.implode(',', $selectable)],
        ], [
            'abilities.required' => 'Escolha ao menos uma permissão para o token.',
            'abilities.*.in' => 'Permissão de integração desconhecida.',
        ]);

        $abilities = array_values(array_unique($validated['abilities']));

        $token = IntegrationToken::generateToken();

        $clinic->integrationTokens()->create([
            'name' => $validated['name'],
            'token_hash' => IntegrationToken::hashToken($token),
            'abilities' => $abilities,
        ]);

        PlatformLogger::log('clinica.token_gerado', 'clinic', $clinic->id, [
            'integracao' => $validated['name'],
            'abilities' => $abilities,
        ], $clinic);

        return back()->with('token', $token);
    }

    /**
     * @return Collection<int, Plan>
     */
    protected function planosAtivos(): Collection
    {
        return Plan::query()->where('is_active', true)->orderBy('sort_order')->get();
    }

    protected function authorizePlatformAction(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin() === true, 403, 'Apenas o super admin pode alterar clínicas.');
    }
}
