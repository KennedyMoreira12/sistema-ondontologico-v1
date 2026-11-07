<?php

namespace App\Http\Controllers;

use App\Models\Avaliacao;
use App\Models\Paciente;
use App\Models\Professional;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Ficha de avaliação inicial da fisioterapia.
 *
 * A ficha é preenchida pelo profissional. NADA aqui é gerado pelo sistema:
 * não há diagnóstico automático, nem score, nem conduta sugerida. Os campos
 * de texto ficam em branco até alguém digitar, e quem digita é o profissional
 * responsável — com o nome dele gravado na auditoria.
 */
class AvaliacaoController extends Controller
{
    public function index(Paciente $paciente): View
    {
        $this->authorize('avaliacao.view');

        $avaliacoes = $paciente->avaliacoes()
            ->with('profissional')
            ->latest('data')
            ->paginate(15);

        return view('avaliacoes.index', compact('paciente', 'avaliacoes'));
    }

    public function create(Paciente $paciente): View
    {
        $this->authorize('avaliacao.create');

        return view('avaliacoes.create', compact('paciente'));
    }

    public function store(Request $request, Paciente $paciente): RedirectResponse
    {
        $this->authorize('avaliacao.create');

        $validated = $request->validate($this->regras());

        $avaliacao = Avaliacao::create([
            'paciente_id' => $paciente->id,
            'profissional_id' => $this->profissionalDoUsuario()?->id,
            'data' => $validated['data'],
            'queixa_principal' => $validated['queixa_principal'],
            'historia_clinica' => $validated['historia_clinica'] ?? null,
            'exame_fisico' => $validated['exame_fisico'] ?? null,
            'hipotese_clinica' => $validated['hipotese_clinica'] ?? null,
            'objetivos' => $validated['objetivos'] ?? null,
            'plano_terapeutico' => $validated['plano_terapeutico'] ?? null,
            'observacoes' => $validated['observacoes'] ?? null,
            'status' => Avaliacao::RASCUNHO,
        ]);

        AuditLogger::log('criar_avaliacao', 'avaliacoes', $avaliacao->id, depois: [
            'paciente_id' => $paciente->id,
            'data' => $avaliacao->data,
            'status' => $avaliacao->status,
        ]);

        return redirect()
            ->route('pacientes.avaliacoes.show', [$paciente, $avaliacao])
            ->with('success', 'Ficha de avaliação registrada.');
    }

    public function show(Paciente $paciente, Avaliacao $avaliacao): View
    {
        $this->authorize('avaliacao.view');

        abort_unless($avaliacao->paciente_id === $paciente->id, 404);

        $avaliacao->load('profissional');

        return view('avaliacoes.show', compact('paciente', 'avaliacao'));
    }

    public function edit(Paciente $paciente, Avaliacao $avaliacao): View
    {
        $this->authorize('avaliacao.update');

        abort_unless($avaliacao->paciente_id === $paciente->id, 404);

        abort_if($avaliacao->estaFinalizada(), 403, 'Ficha já finalizada: o conteúdo assinado não pode ser alterado.');

        return view('avaliacoes.edit', compact('paciente', 'avaliacao'));
    }

    public function update(Request $request, Paciente $paciente, Avaliacao $avaliacao): RedirectResponse
    {
        $this->authorize('avaliacao.update');

        abort_unless($avaliacao->paciente_id === $paciente->id, 404);
        abort_if($avaliacao->estaFinalizada(), 403, 'Ficha já finalizada: o conteúdo assinado não pode ser alterado.');

        $validated = $request->validate($this->regras());

        $antes = $avaliacao->only(['data', 'status']);

        $avaliacao->update([
            'data' => $validated['data'],
            'queixa_principal' => $validated['queixa_principal'],
            'historia_clinica' => $validated['historia_clinica'] ?? null,
            'exame_fisico' => $validated['exame_fisico'] ?? null,
            'hipotese_clinica' => $validated['hipotese_clinica'] ?? null,
            'objetivos' => $validated['objetivos'] ?? null,
            'plano_terapeutico' => $validated['plano_terapeutico'] ?? null,
            'observacoes' => $validated['observacoes'] ?? null,
        ]);

        AuditLogger::log('atualizar_avaliacao', 'avaliacoes', $avaliacao->id, antes: $antes, depois: $avaliacao->only(['data', 'status']));

        return redirect()
            ->route('pacientes.avaliacoes.show', [$paciente, $avaliacao])
            ->with('success', 'Ficha de avaliação atualizada.');
    }

    public function finalize(Paciente $paciente, Avaliacao $avaliacao): RedirectResponse
    {
        $this->authorize('avaliacao.update');

        abort_unless($avaliacao->paciente_id === $paciente->id, 404);

        if ($avaliacao->estaFinalizada()) {
            return back()->with('warning', 'Esta ficha já estava finalizada.');
        }

        $avaliacao->finalize();

        AuditLogger::log('finalizar_avaliacao', 'avaliacoes', $avaliacao->id, depois: [
            'paciente_id' => $paciente->id,
            'finalizada_em' => $avaliacao->finalizada_em?->toDateTimeString(),
        ]);

        return back()->with('success', 'Ficha finalizada. A partir de agora ela não pode ser alterada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function regras(): array
    {
        return [
            'data' => ['required', 'date'],
            'queixa_principal' => ['required', 'string', 'max:2000'],
            'historia_clinica' => ['nullable', 'string', 'max:5000'],
            'exame_fisico' => ['nullable', 'string', 'max:5000'],
            'hipotese_clinica' => ['nullable', 'string', 'max:2000'],
            'objetivos' => ['nullable', 'string', 'max:2000'],
            'plano_terapeutico' => ['nullable', 'string', 'max:5000'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function profissionalDoUsuario(): ?Professional
    {
        return Professional::where('user_id', auth()->id())->first();
    }
}
