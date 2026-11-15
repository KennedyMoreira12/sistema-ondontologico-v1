<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\DocumentSignature;
use App\Models\Paciente;
use App\Services\AuditLogger;
use App\Services\Documentos\AssinaturaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContratoController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('contratos.view');

        $contratos = Contract::with(['paciente', 'user', 'assinaturaAtiva.user'])
            ->when($request->filled('busca'), function ($query, $busca) {
                $query->whereHas('paciente', fn ($q) => $q->where('nome', 'like', "%{$busca}%"));
            })
            ->when($request->filled('status'), function ($query, $status) {
                $query->where('status', $status);
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $statuses = Contract::query()->select('status')->distinct()->orderBy('status')->pluck('status');

        return view('contratos.index', compact('contratos', 'statuses'));
    }

    public function create(): View
    {
        $this->authorize('contratos.create');

        $pacientes = Paciente::where('status', 'ativo')->orderBy('nome')->get();

        return view('contratos.create', compact('pacientes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('contratos.create');

        $validated = $request->validate([
            'paciente_id' => ['required', 'exists:tenant.pacientes,id'],
            'tipo' => ['required', 'string', 'max:100'],
            'conteudo' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:pendente,assinado,cancelado'],
            'assinado_em' => ['nullable', 'date'],
        ]);

        $contrato = Contract::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'versao' => 1,
            'conteudo_hash' => DocumentSignature::hashDoConteudo(1, $validated['conteudo'] ?? null),
        ]);

        AuditLogger::log('criar', 'contracts', $contrato->id, depois: $contrato->only(['paciente_id', 'tipo', 'status', 'versao']));

        return redirect()->route('contratos.index')->with('success', 'Contrato cadastrado com sucesso.');
    }

    public function edit(Contract $contrato): View
    {
        $this->authorize('contratos.update');

        $pacientes = Paciente::where('status', 'ativo')->orderBy('nome')->get();

        return view('contratos.edit', compact('contrato', 'pacientes'));
    }

    /**
     * Atualiza o contrato e versiona o texto quando ele já estava assinado.
     *
     * Mudar o texto assinado nunca apaga a assinatura antiga: ela é marcada
     * como substituída, a versão sobe e o contrato volta para pendente. O
     * paciente precisa assinar a versão nova.
     */
    public function update(Request $request, Contract $contrato, AssinaturaService $assinaturas): RedirectResponse
    {
        $this->authorize('contratos.update');

        $validated = $request->validate([
            'paciente_id' => ['required', 'exists:tenant.pacientes,id'],
            'tipo' => ['required', 'string', 'max:100'],
            'conteudo' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:pendente,assinado,cancelado'],
            'assinado_em' => ['nullable', 'date'],
        ]);

        $antes = $contrato->only(['paciente_id', 'tipo', 'status', 'versao']);

        $conteudoMudou = ($contrato->conteudo ?? '') !== ($validated['conteudo'] ?? '');
        $precisaNovaAssinatura = $contrato->assinaturaAtiva()->exists() || $contrato->status === 'assinado';

        if ($conteudoMudou) {
            if ($precisaNovaAssinatura) {
                $assinaturas->superseder($contrato, DocumentSignature::TIPO_TERMO, 'termo_alterado');

                $validated['versao'] = ((int) $contrato->versao) + 1;
                $validated['status'] = 'pendente';
                $validated['assinado_em'] = null;
            } else {
                $validated['versao'] = (int) $contrato->versao;
            }

            $validated['conteudo_hash'] = DocumentSignature::hashDoConteudo(
                (int) $validated['versao'],
                $validated['conteudo'] ?? null,
            );
        }

        $contrato->update($validated);

        if ($conteudoMudou && $precisaNovaAssinatura) {
            AuditLogger::log(
                'termo_alterado_requer_nova_assinatura',
                'contracts',
                $contrato->id,
                antes: $antes,
                depois: [
                    'versao' => $contrato->versao,
                    'status' => $contrato->status,
                    'assinatura_anterior_preservada' => true,
                ],
            );
        }

        AuditLogger::log('atualizar', 'contracts', $contrato->id, antes: $antes, depois: $contrato->only(['paciente_id', 'tipo', 'status', 'versao']));

        return redirect()->route('contratos.index')->with('success', 'Contrato atualizado com sucesso.');
    }

    public function destroy(Contract $contrato): RedirectResponse
    {
        $this->authorize('contratos.delete');

        AuditLogger::log('excluir', 'contracts', $contrato->id, antes: ['tipo' => $contrato->tipo, 'status' => $contrato->status]);

        $contrato->delete();

        return redirect()->route('contratos.index')->with('success', 'Contrato excluído com sucesso.');
    }

    /**
     * PDF do termo com a assinatura e as evidências de integridade.
     */
    public function pdf(Contract $contrato)
    {
        $this->authorize('contratos.view');

        $contrato->load(['paciente', 'user', 'assinaturaAtiva.user']);

        $pdf = Pdf::loadView('assinaturas.pdf-termo', [
            'contrato' => $contrato,
            'assinatura' => $contrato->assinaturaAtiva,
            'clinica' => tenant(),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('termo-'.$contrato->id.'.pdf');
    }
}
