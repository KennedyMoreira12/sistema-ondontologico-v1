<?php

namespace App\Http\Controllers;

use App\Exceptions\PacoteSessaoIndisponivel;
use App\Exceptions\SaldoDeSessoesInsuficiente;
use App\Models\Paciente;
use App\Models\PacoteSessao;
use App\Models\Professional;
use App\Services\AuditLogger;
use App\Services\Fisioterapia\PacoteSessaoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pacotes de sessões de fisioterapia.
 *
 * O consumo de sessão é transacional e não aceita saldo negativo: quem
 * consome é o `PacoteSessaoService`, e o controller só traduz o erro para a
 * tela. Duas atendentes marcando sessão ao mesmo tempo não conseguem
 * consumir mais do que o pacote tem.
 */
class PacoteSessaoController extends Controller
{
    public function __construct(private PacoteSessaoService $pacotes) {}

    public function index(Paciente $paciente): View
    {
        $this->authorize('pacotes.view');

        $pacotes = $paciente->pacotesSessao()
            ->with('profissional')
            ->latest()
            ->paginate(15);

        return view('pacotes.index', compact('paciente', 'pacotes'));
    }

    public function create(Paciente $paciente): View
    {
        $this->authorize('pacotes.create');

        return view('pacotes.create', compact('paciente'));
    }

    public function store(Request $request, Paciente $paciente): RedirectResponse
    {
        $this->authorize('pacotes.create');

        $validated = $request->validate($this->regras());

        $pacote = PacoteSessao::create([
            'paciente_id' => $paciente->id,
            'profissional_id' => $validated['profissional_id'] ?? null,
            'nome' => $validated['nome'],
            'total_sessoes' => $validated['total_sessoes'],
            'sessoes_usadas' => 0,
            'valor_sessao' => $validated['valor_sessao'] ?? 0,
            'valor_total' => $validated['valor_total'] ?? 0,
            'status' => PacoteSessao::ATIVO,
            'validade_em' => $validated['validade_em'] ?? null,
        ]);

        AuditLogger::log('criar_pacote_sessao', 'pacotes_sessao', $pacote->id, depois: [
            'paciente_id' => $paciente->id,
            'nome' => $pacote->nome,
            'total_sessoes' => $pacote->total_sessoes,
            'valor_total' => $pacote->valor_total,
        ]);

        return redirect()
            ->route('pacientes.pacotes.index', $paciente)
            ->with('success', 'Pacote de sessões criado.');
    }

    public function show(Paciente $paciente, PacoteSessao $pacote): View
    {
        $this->authorize('pacotes.view');

        abort_unless($pacote->paciente_id === $paciente->id, 404);

        $consumos = $pacote->consumos()->with('profissional')->latest('data')->paginate(20);

        return view('pacotes.show', compact('paciente', 'pacote', 'consumos'));
    }

    /**
     * Consome uma sessão do pacote.
     */
    public function consumir(Request $request, Paciente $paciente, PacoteSessao $pacote): RedirectResponse
    {
        $this->authorize('pacotes.update');

        abort_unless($pacote->paciente_id === $paciente->id, 404);

        $validated = $request->validate([
            'data' => ['nullable', 'date'],
            'evolucao_sessao_id' => ['nullable', 'integer', 'exists:tenant.evolucoes_sessao,id'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $consumo = $this->pacotes->consumir($pacote, [
                'paciente_id' => $paciente->id,
                'profissional_id' => $this->profissionalDoUsuario()?->id,
                'evolucao_sessao_id' => $validated['evolucao_sessao_id'] ?? null,
                'data' => $validated['data'] ?? null,
                'observacoes' => $validated['observacoes'] ?? null,
            ]);
        } catch (SaldoDeSessoesInsuficiente|PacoteSessaoIndisponivel $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLogger::log('consumir_sessao_pacote', 'consumo_sessao', $consumo->id, depois: [
            'pacote_id' => $pacote->id,
            'paciente_id' => $paciente->id,
            'sessoes_usadas' => $pacote->fresh()?->sessoes_usadas,
            'total_sessoes' => $pacote->total_sessoes,
        ]);

        return back()->with('success', 'Sessão consumida do pacote.');
    }

    /**
     * Cancela o pacote: o que já foi consumido continua no extrato.
     */
    public function destroy(Paciente $paciente, PacoteSessao $pacote): RedirectResponse
    {
        $this->authorize('pacotes.update');

        abort_unless($pacote->paciente_id === $paciente->id, 404);

        if ($pacote->status === PacoteSessao::CANCELADO) {
            return back()->with('warning', 'Este pacote já está cancelado.');
        }

        $antes = $pacote->only(['status', 'sessoes_usadas']);

        $pacote->update(['status' => PacoteSessao::CANCELADO]);

        AuditLogger::log('cancelar_pacote_sessao', 'pacotes_sessao', $pacote->id, antes: $antes, depois: $pacote->only(['status', 'sessoes_usadas']));

        return back()->with('success', 'Pacote cancelado. O extrato das sessões consumidas foi preservado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function regras(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'total_sessoes' => ['required', 'integer', 'min:1', 'max:1000'],
            'valor_sessao' => ['nullable', 'numeric', 'min:0'],
            'valor_total' => ['nullable', 'numeric', 'min:0'],
            'profissional_id' => ['nullable', 'integer', 'exists:tenant.professionals,id'],
            'validade_em' => ['nullable', 'date'],
        ];
    }

    private function profissionalDoUsuario(): ?Professional
    {
        return Professional::where('user_id', auth()->id())->first();
    }
}
