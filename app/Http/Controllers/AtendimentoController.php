<?php

namespace App\Http\Controllers;

use App\Exceptions\AtendimentoInvalido;
use App\Exceptions\PacoteSessaoIndisponivel;
use App\Exceptions\SaldoDeSessoesInsuficiente;
use App\Models\Appointment;
use App\Models\Paciente;
use App\Models\PacoteSessao;
use App\Models\Professional;
use App\Services\Agenda\AgendaService;
use App\Services\Atendimento\AtendimentoService;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Operação do dia: receber o paciente, marcar presença/falta e debitar a
 * sessão do pacote.
 *
 * Consumir sessão é separado de marcar presença de propósito. A recepção pode
 * registrar que o paciente veio mesmo quando o pacote será acertado depois; e
 * a falta nunca debita sozinha, porque a regra de reposição da cliente ainda
 * não foi confirmada.
 */
class AtendimentoController extends Controller
{
    public function __construct(
        private AgendaService $agenda,
        private AtendimentoService $atendimentos,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('agenda.view');

        $dia = Carbon::parse($request->date('data') ?? today())->startOfDay();
        $profissionalId = $request->integer('profissional_id');

        $consultas = $this->agenda->agendaDoDia($dia, $profissionalId > 0 ? $profissionalId : null)
            ->load('consumos');

        $pacotes = PacoteSessao::query()
            ->whereIn('paciente_id', $consultas->pluck('paciente_id')->filter()->unique()->all())
            ->where('status', PacoteSessao::ATIVO)
            ->whereColumn('sessoes_usadas', '<', 'total_sessoes')
            ->orderBy('id')
            ->get()
            ->groupBy('paciente_id');

        $consumos = $consultas->flatMap(fn (Appointment $consulta) => $consulta->consumos)->keyBy('appointment_id');

        $resumo = [
            'total' => $consultas->count(),
            'presentes' => $consultas->filter(fn (Appointment $consulta): bool => $consulta->status?->name === 'atendido')->count(),
            'faltas' => $consultas->filter(fn (Appointment $consulta): bool => $consulta->status?->name === 'faltou')->count(),
            'sem_pacote' => $consultas
                ->filter(fn (Appointment $consulta): bool => $consulta->status?->name === 'atendido')
                ->filter(fn (Appointment $consulta): bool => ! isset($consumos[$consulta->id]))
                ->count(),
        ];

        return view('atendimento.index', [
            'dia' => $dia,
            'consultas' => $consultas,
            'pacotes' => $pacotes,
            'consumos' => $consumos,
            'resumo' => $resumo,
            'profissionalId' => $profissionalId,
            'profissionais' => Professional::query()->where('is_active', true)->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function consumir(Request $request, Appointment $consulta): RedirectResponse
    {
        $this->authorize('agenda.update');

        $dados = $request->validate([
            'pacote_id' => ['nullable', 'integer', 'exists:tenant.pacotes_sessao,id'],
            'reposicao' => ['nullable', 'boolean'],
        ]);

        $pacote = isset($dados['pacote_id'])
            ? PacoteSessao::query()->findOrFail($dados['pacote_id'])
            : null;

        try {
            $consumo = $this->atendimentos->consumirSessao($consulta, $pacote, (bool) ($dados['reposicao'] ?? false));
        } catch (AtendimentoInvalido|SaldoDeSessoesInsuficiente|PacoteSessaoIndisponivel $excecao) {
            return back()->withErrors(['pacote_id' => $excecao->getMessage()]);
        }

        AuditLogger::log('consumir_sessao', 'consumo_sessao', $consumo->id, depois: [
            'appointment_id' => $consulta->id,
            'pacote_id' => $consumo->pacote_id,
            'reposicao' => $consumo->reposicao,
        ]);

        return back()->with('success', 'Sessão consumida. Saldo do pacote atualizado.');
    }

    /**
     * Histórico do paciente em uma página única: agenda, sessões, pacotes e
     * pagamentos. É a resposta para "onde está o histórico que antes ficava
     * espalhado nas abas da planilha?".
     */
    public function historico(Paciente $paciente): View
    {
        $this->authorize('pacientes.view');

        $consultas = $paciente->consultas()
            ->with(['profissional:id,nome', 'status:id,name,label,cor'])
            ->orderByDesc('inicio')
            ->paginate(20, ['*'], 'consultas');

        $pacotes = $paciente->pacotesSessao()
            ->with(['profissional:id,nome', 'consumos'])
            ->orderByDesc('id')
            ->get();

        $pagamentos = $paciente->pagamentos()
            ->with('paymentMethod:id,label')
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $pacotesAtivos = $pacotes->filter(fn (PacoteSessao $pacote): bool => $pacote->estaAtivo());

        $resumo = [
            'consultas' => $paciente->consultas()->count(),
            'atendidos' => $paciente->consultas()->whereHas('status', fn ($query) => $query->where('name', 'atendido'))->count(),
            'faltas' => $paciente->consultas()->whereHas('status', fn ($query) => $query->where('name', 'faltou'))->count(),
            'pacotes_ativos' => $pacotesAtivos->count(),
            'sessoes_restantes' => $pacotesAtivos->sum(fn (PacoteSessao $pacote): int => $pacote->restantes()),
            'total_pago' => (float) $paciente->pagamentos()->sum('valor'),
        ];

        return view('atendimento.historico', [
            'paciente' => $paciente,
            'consultas' => $consultas,
            'pacotes' => $pacotes,
            'pagamentos' => $pagamentos,
            'resumo' => $resumo,
        ]);
    }
}
