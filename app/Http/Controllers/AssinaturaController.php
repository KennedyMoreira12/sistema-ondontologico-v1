<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\DocumentSignature;
use App\Models\EvolucaoSessao;
use App\Models\Paciente;
use App\Services\AuditLogger;
use App\Services\Documentos\AssinaturaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Assinatura manuscrita eletrônica: termo de responsabilidade e presença.
 *
 * O desenho chega como data URI PNG e vira uma linha imutável em
 * `document_signatures`. Nada aqui apaga ou substitui uma assinatura: quando o
 * termo muda, a antiga é marcada como substituída e continua no histórico.
 */
class AssinaturaController extends Controller
{
    public function index(Paciente $paciente): View
    {
        abort_unless(Gate::any(['prontuario.view', 'contratos.view']), 403);

        $assinaturas = $paciente->assinaturas()
            ->with(['user', 'signable'])
            ->orderByDesc('signed_at')
            ->paginate(20);

        return view('assinaturas.index', compact('paciente', 'assinaturas'));
    }

    /**
     * Assina o termo de responsabilidade do paciente.
     *
     * Se a clínica ainda não cadastrou um termo, a primeira assinatura cria um
     * contrato com o texto padrão — e assina exatamente esse texto.
     */
    public function assinarTermo(
        Request $request,
        Paciente $paciente,
        AssinaturaService $assinaturas,
    ): RedirectResponse {
        abort_unless(Gate::any(['contratos.create', 'contratos.update', 'prontuario.create']), 403);

        $validated = $request->validate([
            'assinatura' => ['required', 'string', 'max:6000000'],
        ]);

        $contrato = Contract::query()
            ->where('paciente_id', $paciente->id)
            ->where('tipo', Contract::TIPO_TERMO_RESPONSABILIDADE)
            ->latest('id')
            ->first();

        if ($contrato?->assinaturaAtiva()->exists()) {
            return back()->withErrors([
                'assinatura' => 'O termo de responsabilidade já está assinado. Altere o termo para exigir uma nova assinatura.',
            ]);
        }

        if ($contrato === null) {
            $conteudo = Contract::textoTermoPadrao($paciente->nome, tenant()?->name);

            $contrato = Contract::create([
                'paciente_id' => $paciente->id,
                'user_id' => $request->user()->id,
                'tipo' => Contract::TIPO_TERMO_RESPONSABILIDADE,
                'conteudo' => $conteudo,
                'status' => 'pendente',
                'versao' => 1,
                'conteudo_hash' => DocumentSignature::hashDoConteudo(1, $conteudo),
            ]);

            AuditLogger::log('criar', 'contracts', $contrato->id, depois: [
                'paciente_id' => $paciente->id,
                'tipo' => $contrato->tipo,
                'status' => $contrato->status,
                'versao' => $contrato->versao,
            ]);
        }

        if (blank($contrato->conteudo)) {
            $conteudo = Contract::textoTermoPadrao($paciente->nome, tenant()?->name);
            $contrato->forceFill([
                'conteudo' => $conteudo,
                'conteudo_hash' => DocumentSignature::hashDoConteudo((int) $contrato->versao, $conteudo),
            ])->save();
        }

        $assinaturas->assinarContrato($contrato, $validated['assinatura'], $request->user());

        return redirect()
            ->route('pacientes.prontuario', $paciente)
            ->with('success', 'Assinatura do termo registrada com sucesso.');
    }

    /**
     * Confirma a presença do paciente na sessão com assinatura.
     */
    public function assinarPresenca(
        Request $request,
        Paciente $paciente,
        EvolucaoSessao $evolucao,
        AssinaturaService $assinaturas,
    ): RedirectResponse {
        $this->authorize('evolucao.update');

        abort_unless($evolucao->paciente_id === $paciente->id, 404);

        $validated = $request->validate([
            'assinatura' => ['required', 'string', 'max:6000000'],
        ]);

        $assinaturas->assinarPresenca($evolucao, $validated['assinatura'], $request->user());

        return redirect()
            ->route('pacientes.evolucoes-sessao.index', $paciente)
            ->with('success', 'Presença confirmada por assinatura.');
    }

    /**
     * Assina um contrato existente pela lista de contratos.
     */
    public function assinarContrato(
        Request $request,
        Contract $contrato,
        AssinaturaService $assinaturas,
    ): RedirectResponse {
        $this->authorize('contratos.update');

        $validated = $request->validate([
            'assinatura' => ['required', 'string', 'max:6000000'],
        ]);

        $assinaturas->assinarContrato($contrato, $validated['assinatura'], $request->user());

        return redirect()
            ->route('contratos.index')
            ->with('success', 'Assinatura do contrato registrada com sucesso.');
    }

    /**
     * Entrega o PNG da assinatura para exibição na ficha e no histórico.
     */
    public function imagem(Paciente $paciente, DocumentSignature $assinatura): Response
    {
        abort_unless(Gate::any(['prontuario.view', 'contratos.view']), 403);

        abort_if($assinatura->paciente_id !== $paciente->id, 404);

        $binario = $assinatura->binario();

        abort_if($binario === null, 404);

        return response($binario, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="assinatura-'.$assinatura->id.'.png"',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
