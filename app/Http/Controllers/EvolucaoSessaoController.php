<?php

namespace App\Http\Controllers;

use App\Models\EvolucaoSessao;
use App\Models\Paciente;
use App\Models\Professional;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Evolução por sessão de fisioterapia.
 *
 * A evolução registra o que aconteceu NA sessão — condutas, resposta do
 * paciente, dor antes e depois. Cada registro nasce em rascunho, que é
 * editável, e só vira definitivo quando o profissional assina. Assinado, o
 * registro é imutável: é a assinatura que dá peso à auditoria.
 */
class EvolucaoSessaoController extends Controller
{
    public function index(Paciente $paciente): View
    {
        $this->authorize('evolucao.view');

        $evolucoes = $paciente->evolucoesSessao()
            ->with(['profissional', 'avaliacao'])
            ->latest('data')
            ->paginate(15);

        return view('evolucoes.index', compact('paciente', 'evolucoes'));
    }

    public function create(Paciente $paciente): View
    {
        $this->authorize('evolucao.create');

        $avaliacoes = $paciente->avaliacoes()->orderByDesc('data')->get();

        return view('evolucoes.create', compact('paciente', 'avaliacoes'));
    }

    public function store(Request $request, Paciente $paciente): RedirectResponse
    {
        $this->authorize('evolucao.create');

        $validated = $request->validate($this->regras());

        $evolucao = EvolucaoSessao::create([
            'paciente_id' => $paciente->id,
            'profissional_id' => $this->profissionalDoUsuario()?->id,
            'avaliacao_id' => $validated['avaliacao_id'] ?? null,
            'data' => $validated['data'],
            'conteudo' => $validated['conteudo'],
            'tecnicas' => $validated['tecnicas'] ?? null,
            'resposta_paciente' => $validated['resposta_paciente'] ?? null,
            'dor_antes' => $validated['dor_antes'] ?? null,
            'dor_depois' => $validated['dor_depois'] ?? null,
            'status' => EvolucaoSessao::RASCUNHO,
        ]);

        AuditLogger::log('criar_evolucao_sessao', 'evolucoes_sessao', $evolucao->id, depois: [
            'paciente_id' => $paciente->id,
            'data' => $evolucao->data,
            'status' => $evolucao->status,
        ]);

        return redirect()
            ->route('pacientes.evolucoes-sessao.index', $paciente)
            ->with('success', 'Evolução da sessão registrada.');
    }

    public function edit(Paciente $paciente, EvolucaoSessao $evolucao): View
    {
        $this->authorize('evolucao.update');

        abort_unless($evolucao->paciente_id === $paciente->id, 404);
        abort_if($evolucao->estaFinalizada(), 403, 'Evolução já finalizada: o conteúdo assinado não pode ser alterado.');

        $avaliacoes = $paciente->avaliacoes()->orderByDesc('data')->get();

        return view('evolucoes.edit', compact('paciente', 'evolucao', 'avaliacoes'));
    }

    public function update(Request $request, Paciente $paciente, EvolucaoSessao $evolucao): RedirectResponse
    {
        $this->authorize('evolucao.update');

        abort_unless($evolucao->paciente_id === $paciente->id, 404);
        abort_if($evolucao->estaFinalizada(), 403, 'Evolução já finalizada: o conteúdo assinado não pode ser alterado.');

        $validated = $request->validate($this->regras());

        $antes = $evolucao->only(['data', 'status']);

        $evolucao->update([
            'avaliacao_id' => $validated['avaliacao_id'] ?? null,
            'data' => $validated['data'],
            'conteudo' => $validated['conteudo'],
            'tecnicas' => $validated['tecnicas'] ?? null,
            'resposta_paciente' => $validated['resposta_paciente'] ?? null,
            'dor_antes' => $validated['dor_antes'] ?? null,
            'dor_depois' => $validated['dor_depois'] ?? null,
        ]);

        AuditLogger::log('atualizar_evolucao_sessao', 'evolucoes_sessao', $evolucao->id, antes: $antes, depois: $evolucao->only(['data', 'status']));

        return redirect()
            ->route('pacientes.evolucoes-sessao.index', $paciente)
            ->with('success', 'Evolução da sessão atualizada.');
    }

    /**
     * Assina a evolução: a partir daqui o registro não muda mais.
     */
    public function finalize(Paciente $paciente, EvolucaoSessao $evolucao): RedirectResponse
    {
        $this->authorize('evolucao.update');

        abort_unless($evolucao->paciente_id === $paciente->id, 404);

        if ($evolucao->estaFinalizada()) {
            return back()->with('warning', 'Esta evolução já estava finalizada.');
        }

        $evolucao->finalize();

        AuditLogger::log('finalizar_evolucao_sessao', 'evolucoes_sessao', $evolucao->id, depois: [
            'paciente_id' => $paciente->id,
            'profissional_id' => $evolucao->profissional_id,
            'finalizada_em' => $evolucao->finalizada_em?->toDateTimeString(),
        ]);

        return back()->with('success', 'Evolução finalizada. A partir de agora ela não pode ser alterada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function regras(): array
    {
        return [
            'avaliacao_id' => ['nullable', 'integer', 'exists:tenant.avaliacoes,id'],
            'data' => ['required', 'date'],
            'conteudo' => ['required', 'string', 'max:5000'],
            'tecnicas' => ['nullable', 'string', 'max:2000'],
            'resposta_paciente' => ['nullable', 'string', 'max:2000'],
            'dor_antes' => ['nullable', 'integer', 'min:0', 'max:10'],
            'dor_depois' => ['nullable', 'integer', 'min:0', 'max:10'],
        ];
    }

    private function profissionalDoUsuario(): ?Professional
    {
        return Professional::where('user_id', auth()->id())->first();
    }
}
