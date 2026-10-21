<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnamneseStoreRequest;
use App\Services\Tenant\TenantManager;
use App\Http\Requests\ClinicalNoteStoreRequest;
use App\Http\Requests\MedicalAlertStoreRequest;
use App\Http\Requests\PacienteStoreRequest;
use App\Http\Requests\PacienteUpdateRequest;
use App\Http\Requests\PatientDocumentStoreRequest;
use App\Models\Anamnese;
use App\Models\ClinicalNote;
use App\Models\MedicalAlert;
use App\Models\Paciente;
use App\Models\PatientDocument;
use App\Models\Professional;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PacienteController extends Controller
{
    public function __construct(private TenantManager $tenants)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('pacientes.view');

        $termo = $request->string('q')->trim();

        $pacientes = Paciente::query()
            ->withCount(['consultas', 'orcamentos'])
            ->when($termo->isNotEmpty(), function ($query) use ($termo) {
                $query->where(function ($q) use ($termo) {
                    $q->where('nome', 'like', "%{$termo}%")
                        ->orWhere('cpf', 'like', "%{$termo}%")
                        ->orWhere('email', 'like', "%{$termo}%")
                        ->orWhere('phone', 'like', "%{$termo}%")
                        ->orWhere('celular', 'like', "%{$termo}%");
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->string('status'));
            })
            ->orderBy('nome')
            ->paginate(15)
            ->withQueryString();

        return view('pacientes.index', compact('pacientes'));
    }

    public function create(): View
    {
        $this->authorize('pacientes.create');

        return view('pacientes.create');
    }

    public function store(PacienteStoreRequest $request): RedirectResponse
    {
        $paciente = Paciente::create([
            ...$request->validated(),
            'clinica_id' => Auth::user()->clinica_id,
        ]);

        AuditLogger::log('criar', 'pacientes', $paciente->id, depois: $paciente->only(['nome', 'cpf', 'email']));

        return redirect()->route('pacientes.show', $paciente)->with('success', 'Paciente cadastrado com sucesso.');
    }

    public function show(Paciente $paciente): View
    {
        $this->authorize('pacientes.view');

        $paciente->load([
            'alertas',
            'documentos',
            'anamneses',
            'consultas' => fn ($q) => $q->latest('inicio')->limit(5),
        ]);

        $planos = $paciente->planosTratamento()->withCount('items')->latest()->limit(5)->get();
        $orcamentos = $paciente->orcamentos()->latest()->limit(5)->get();

        return view('pacientes.show', compact('paciente', 'planos', 'orcamentos'));
    }

    public function edit(Paciente $paciente): View
    {
        $this->authorize('pacientes.update');

        return view('pacientes.edit', compact('paciente'));
    }

    public function update(PacienteUpdateRequest $request, Paciente $paciente): RedirectResponse
    {
        $before = $paciente->only(['nome', 'cpf', 'email', 'phone']);

        $paciente->update($request->validated());

        AuditLogger::log('atualizar', 'pacientes', $paciente->id, antes: $before, depois: $paciente->only(['nome', 'cpf', 'email', 'phone']));

        return redirect()->route('pacientes.show', $paciente)->with('success', 'Paciente atualizado com sucesso.');
    }

    public function destroy(Paciente $paciente): RedirectResponse
    {
        $this->authorize('pacientes.delete');

        AuditLogger::log('inativar', 'pacientes', $paciente->id, antes: $paciente->only(['nome', 'status']));

        $paciente->update(['status' => 'inativo']);
        $paciente->delete();

        return redirect()->route('pacientes.index')->with('success', 'Paciente excluído (soft delete).');
    }

    public function prontuario(Paciente $paciente): View
    {
        $this->authorize('prontuario.view');

        $evolucoes = $paciente->evolucoes()->with('profissional')->latest()->paginate(20);
        $alertas = $paciente->alertas()->get();
        $dentistas = Professional::where('tipo', 'dentista')->orderBy('nome')->get();

        return view('pacientes.prontuario', compact('paciente', 'evolucoes', 'alertas', 'dentistas'));
    }

    public function storeEvolucao(ClinicalNoteStoreRequest $request, Paciente $paciente): RedirectResponse
    {
        $profissional = Professional::where('user_id', auth()->id())->first();

        ClinicalNote::create([
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional?->id,
            'tipo' => $request->input('tipo', 'evolucao'),
            'conteudo' => $request->validated('conteudo'),
        ]);

        AuditLogger::log('registrar_evolucao', 'clinical_notes', $paciente->id, depois: ['tipo' => $request->input('tipo', 'evolucao')], user: auth()->user());

        return back()->with('success', 'Evolução registrada no prontuário.');
    }

    public function storeAnamnese(AnamneseStoreRequest $request, Paciente $paciente): RedirectResponse
    {
        $respostas = $request->input('respostas');

        if (is_string($respostas)) {
            $respostas = collect(preg_split('/\r\n|\r|\n/', $respostas))
                ->map(fn ($linha) => trim($linha))
                ->filter()
                ->mapWithKeys(function (string $linha) {
                    $partes = explode(':', $linha, 2);

                    return [trim($partes[0]) => trim($partes[1] ?? '')];
                })
                ->all();
        }

        Anamnese::create([
            ...$request->validated(),
            'respostas' => $respostas,
            'paciente_id' => $paciente->id,
            'profissional_id' => Professional::where('user_id', auth()->id())->first()?->id,
        ]);

        return back()->with('success', 'Anamnese registrada.');
    }

    public function storeAlerta(MedicalAlertStoreRequest $request, Paciente $paciente): RedirectResponse
    {
        MedicalAlert::create([
            ...$request->validated(),
            'paciente_id' => $paciente->id,
        ]);

        AuditLogger::log('criar_alerta', 'medical_alerts', $paciente->id, depois: $request->validated());

        return back()->with('success', 'Alerta clínico registrado.');
    }

    public function destroyAlerta(Paciente $paciente, MedicalAlert $alerta): RedirectResponse
    {
        $this->authorize('alertas.update');

        $alerta->update(['is_active' => false]);

        return back()->with('success', 'Alerta desativado.');
    }

    public function storeDocumento(PatientDocumentStoreRequest $request, Paciente $paciente): RedirectResponse
    {
        $this->authorize('documentos.create');

        // Garante que o documento pertence ao paciente da clínica atual (banco tenant)
        abort_unless($documentoPertenceAoPaciente($paciente, null), 403);

        $tenantId = $this->tenants->id();
        $path = $request->file('arquivo')->store("clinicas/{$tenantId}/pacientes/{$paciente->id}/documentos", 'local');

        PatientDocument::create([
            'paciente_id' => $paciente->id,
            'user_id' => auth()->id(),
            'tipo' => $request->validated('tipo'),
            'titulo' => $request->validated('titulo'),
            'arquivo' => $path,
            'mime_type' => $request->file('arquivo')->getMimeType(),
            'tamanho' => $request->file('arquivo')->getSize(),
        ]);

        AuditLogger::log('anexar_documento', 'patient_documents', $paciente->id, depois: ['tipo' => $request->validated('tipo'), 'tenant_id' => $tenantId]);

        return back()->with('success', 'Documento anexado.');
    }

    public function downloadDocumento(Paciente $paciente, PatientDocument $documento): StreamedResponse
    {
        $this->authorize('pacientes.view');

        // Nunca confie na URL: o documento precisa pertencer ao paciente informado
        abort_if($documento->paciente_id !== $paciente->id, 403);

        $disk = Storage::disk(str_starts_with($documento->arquivo, 'clinicas/') ? 'local' : 'public');

        if (! $disk->exists($documento->arquivo)) {
            abort(404);
        }

        return $disk->download($documento->arquivo);
    }

    public function destroyDocumento(Paciente $paciente, PatientDocument $documento): RedirectResponse
    {
        $this->authorize('documentos.delete');

        abort_if($documento->paciente_id !== $paciente->id, 403);

        $disk = Storage::disk(str_starts_with($documento->arquivo, 'clinicas/') ? 'local' : 'public');
        $disk->delete($documento->arquivo);

        AuditLogger::log('excluir_documento', 'patient_documents', $paciente->id, antes: ['arquivo' => $documento->arquivo, 'tenant_id' => $this->tenants->id()]);

        $documento->delete();

        return back()->with('success', 'Documento removido.');
    }
}
