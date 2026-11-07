<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClinicProfile;
use App\Models\FiscalCompany;
use App\Models\Nfse;
use App\Services\Fiscal\AuditFiscal;
use App\Services\Fiscal\FiscalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Configuração fiscal da clínica e consulta de NFS-e (SendNF).
 *
 * A clínica resolvida é sempre a do tenant ativo (ResolveTenant), nunca um
 * id enviado pelo navegador. Os documentos fiscais vivem no banco da própria
 * clínica, isolados por conexão `tenant`.
 *
 * A emissão real (POST /v1/nfse e cancelamento) existe no cliente SendNF, mas
 * permanece bloqueada: FiscalService::canEmit() exige SENDFN_ENABLED=true,
 * opt-in local, status 'ready' e empresa cadastrada na SendNF. Enquanto não
 * houver autorização explícita, a rota de transmissão retorna 403.
 */
class FiscalController extends Controller
{
    public function __construct(protected FiscalService $fiscal) {}

    public function index(): View
    {
        $this->authorize('fiscal.view');

        $perfil = ClinicProfile::current();
        $company = FiscalCompany::current();
        $drafts = Nfse::query()->orderByDesc('created_at')->limit(10)->get();

        return view('admin.fiscal.index', [
            'perfil' => $perfil,
            'company' => $company,
            'drafts' => $drafts,
            'canEmit' => $this->fiscal->canEmit(),
            'integrationEnabled' => $this->fiscal->isEnabled(),
        ]);
    }

    public function updateConfig(Request $request): RedirectResponse
    {
        $this->authorize('fiscal.update');

        $dados = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'cnpj' => ['nullable', 'string', 'max:30'],
        ]);

        $company = FiscalCompany::current();

        if (array_key_exists('enabled', $dados)) {
            $company->update([
                'enabled' => (bool) $dados['enabled'],
                'enabled_at' => $dados['enabled'] ? now() : null,
                'enabled_by_user_id' => $dados['enabled'] ? auth()->id() : null,
            ]);
        }

        if (array_key_exists('cnpj', $dados) && filled($dados['cnpj'])) {
            $company->update(['cnpj' => preg_replace('/\D/', '', $dados['cnpj'])]);
        }

        AuditFiscal::log('atualizar_config', $company->id, depois: [
            'enabled' => $company->enabled,
            'cnpj' => $company->cnpj,
        ]);

        return back()->with('success', 'Configuração fiscal atualizada.');
    }

    public function register(Request $request): RedirectResponse
    {
        $this->authorize('fiscal.update');

        $request->validate([
            'cnpj' => ['required', 'string', 'max:30'],
        ]);

        $clinica = tenant();
        $company = FiscalCompany::current();

        try {
            $this->fiscal->registerCompany((string) $clinica->id, $request->cnpj);

            return back()->with('success', 'Empresa cadastrada na SendNF. Consulte o readiness para verificar o status.');
        } catch (\Throwable $e) {
            return back()->withErrors(['fiscal' => $e->getMessage()]);
        }
    }

    public function syncReadiness(): RedirectResponse
    {
        $this->authorize('fiscal.view');

        $company = FiscalCompany::current();

        try {
            $result = $this->fiscal->syncReadiness($company);
            $ready = $result['readiness']['nfse_ready'] ?? false;

            return back()->with('success', $ready
                ? 'Empresa pronta para emissão na SendNF.'
                : 'Readiness consultado. Ainda há pendências para emissão.');
        } catch (\Throwable $e) {
            return back()->withErrors(['fiscal' => $e->getMessage()]);
        }
    }

    public function nfseIndex(): View
    {
        $this->authorize('fiscal.view');

        $drafts = Nfse::query()->orderByDesc('created_at')->paginate(15);

        return view('admin.fiscal.nfse.index', [
            'drafts' => $drafts,
            'canEmit' => $this->fiscal->canEmit(),
        ]);
    }

    public function nfseShow(Nfse $nfse): View
    {
        $this->authorize('fiscal.view');

        return view('admin.fiscal.nfse.show', [
            'nfse' => $nfse,
            'events' => $nfse->events()->orderByDesc('created_at')->get(),
            'canEmit' => $this->fiscal->canEmit(),
            'integrationEnabled' => $this->fiscal->isEnabled(),
        ]);
    }

    public function nfsePreview(Nfse $nfse, Request $request): RedirectResponse
    {
        $this->authorize('fiscal.update');

        $request->validate([
            'tomador_documento' => ['required', 'string'],
            'tomador_nome' => ['required', 'string'],
            'servico_descricao' => ['required', 'string'],
            'valor_servico_cents' => ['required', 'integer', 'min:0'],
        ]);

        $company = FiscalCompany::current();

        try {
            $this->fiscal->previewDraft($company, array_merge($request->only([
                'tomador_documento',
                'tomador_nome',
                'tomador_email',
                'servico_descricao',
                'valor_servico_cents',
            ]), [
                'idempotency_key' => $nfse->idempotency_key,
            ]));

            return back()->with('success', 'Pré-emissão consultada na SendNF (sem transmissão).');
        } catch (\Throwable $e) {
            return back()->withErrors(['fiscal' => $e->getMessage()]);
        }
    }

    public function nfseEmit(Nfse $nfse): RedirectResponse
    {
        $this->authorize('fiscal.emit');

        if (! $this->fiscal->canEmit()) {
            abort(403, 'Emissão fiscal não habilitada para esta clínica.');
        }

        // Rota de transmissão real mantida bloqueada até autorização explícita.
        abort(403, 'A emissão real de NFS-e permanece bloqueada.');
    }

    public function nfseCancel(Nfse $nfse): RedirectResponse
    {
        $this->authorize('fiscal.cancel');

        if (! $this->fiscal->canEmit()) {
            abort(403, 'Emissão fiscal não habilitada para esta clínica.');
        }

        abort(403, 'O cancelamento de NFS-e permanece bloqueado.');
    }

    public function nfsePdf(Nfse $nfse)
    {
        $this->authorize('fiscal.view');

        if (! filled($nfse->sendnf_id)) {
            abort(404, 'Documento não transmitido à SendNF.');
        }

        $response = $this->fiscal->getDocumentPdf($nfse->sendnf_id);

        return response($response->body(), $response->status())
            ->header('Content-Type', $response->header('Content-Type') ?: 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="nfse-'.$nfse->id.'.pdf"');
    }

    public function nfseXml(Nfse $nfse)
    {
        $this->authorize('fiscal.view');

        if (! filled($nfse->sendnf_id)) {
            abort(404, 'Documento não transmitido à SendNF.');
        }

        $response = $this->fiscal->getDocumentXml($nfse->sendnf_id);

        return response($response->body(), $response->status())
            ->header('Content-Type', $response->header('Content-Type') ?: 'application/xml')
            ->header('Content-Disposition', 'inline; filename="nfse-'.$nfse->id.'.xml"');
    }
}
