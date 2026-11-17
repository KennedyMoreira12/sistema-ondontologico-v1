<?php

namespace App\Http\Controllers;

use App\Models\AplicacaoLaser;
use App\Models\Paciente;
use App\Models\PacoteLaser;
use App\Models\Professional;
use App\Models\RegiaoLaser;
use App\Services\AuditLogger;
use App\Services\Laser\AplicacaoLaserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Histórico de aplicações de laser.
 *
 * A aplicação é o registro clínico do que foi feito: região, pacote de
 * referência, data, profissional e número da sessão. O número é calculado no
 * servidor, por paciente e região, e nunca vem do formulário.
 */
class AplicacaoLaserController extends Controller
{
    public function __construct(private AplicacaoLaserService $aplicacoes) {}

    public function index(Request $request): View
    {
        $this->authorize('regioes_laser.view');

        $aplicacoes = AplicacaoLaser::query()
            ->with(['paciente', 'regiao', 'pacote', 'profissional'])
            ->when($request->filled('paciente'), fn ($query) => $query->where('paciente_id', $request->integer('paciente')))
            ->when($request->filled('regiao'), fn ($query) => $query->where('regiao_id', $request->integer('regiao')))
            ->when($request->filled('de'), fn ($query) => $query->whereDate('data', '>=', $request->input('de')))
            ->when($request->filled('ate'), fn ($query) => $query->whereDate('data', '<=', $request->input('ate')))
            ->latest('data')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $pacientes = Paciente::query()->orderBy('nome')->get(['id', 'nome']);
        $regioes = RegiaoLaser::query()->orderBy('nome')->get();
        $pacotes = PacoteLaser::query()->with('regiao')->orderBy('nome')->get();
        $profissionais = Professional::query()->where('is_active', true)->orderBy('nome')->get();

        return view('laser.historico', compact(
            'aplicacoes', 'pacientes', 'regioes', 'pacotes', 'profissionais',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('regioes_laser.create');

        $validated = $request->validate($this->regras());

        $pacote = null;

        if (! empty($validated['pacote_id'])) {
            $pacote = PacoteLaser::find($validated['pacote_id']);

            if ($pacote !== null && (int) $pacote->regiao_id !== (int) $validated['regiao_id']) {
                throw ValidationException::withMessages([
                    'pacote_id' => 'O pacote selecionado pertence a outra região.',
                ]);
            }
        }

        $aplicacao = $this->aplicacoes->registrar([
            'paciente_id' => $validated['paciente_id'],
            'regiao_id' => $validated['regiao_id'],
            'pacote_id' => $validated['pacote_id'] ?? null,
            'profissional_id' => $validated['profissional_id'] ?? $this->profissionalDoUsuario()?->id,
            'data' => $validated['data'],
            'observacoes' => $validated['observacoes'] ?? null,
        ]);

        AuditLogger::log('registrar_aplicacao_laser', 'laser_aplicacoes', $aplicacao->id, depois: [
            'paciente_id' => $aplicacao->paciente_id,
            'regiao_id' => $aplicacao->regiao_id,
            'pacote_id' => $aplicacao->pacote_id,
            'data' => $aplicacao->data?->toDateString(),
            'numero_sessao' => $aplicacao->numero_sessao,
        ]);

        return redirect()
            ->route('regioes_laser.aplicacoes.index', ['paciente' => $aplicacao->paciente_id])
            ->with('success', 'Aplicação registrada: sessão nº '.$aplicacao->numero_sessao.'.');
    }

    public function destroy(AplicacaoLaser $aplicacao): RedirectResponse
    {
        $this->authorize('regioes_laser.delete');

        $antes = $aplicacao->only([
            'paciente_id', 'regiao_id', 'pacote_id', 'data', 'numero_sessao',
        ]);

        $aplicacao->delete();

        AuditLogger::log('excluir_aplicacao_laser', 'laser_aplicacoes', $aplicacao->id, antes: $antes);

        return back()->with('success', 'Aplicação excluída do histórico.');
    }

    /**
     * @return array<string, mixed>
     */
    private function regras(): array
    {
        return [
            'paciente_id' => ['required', 'integer', 'exists:tenant.pacientes,id'],
            'regiao_id' => ['required', 'integer', 'exists:tenant.laser_regioes,id'],
            'pacote_id' => ['nullable', 'integer', 'exists:tenant.laser_pacotes,id'],
            'profissional_id' => ['nullable', 'integer', 'exists:tenant.professionals,id'],
            'data' => ['required', 'date'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function profissionalDoUsuario(): ?Professional
    {
        return Professional::where('user_id', auth()->id())->first();
    }
}
