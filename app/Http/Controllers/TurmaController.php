<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Turma;
use App\Models\TurmaPresenca;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Turmas de Pilates.
 *
 * A grade semanal e o calendário mensal vêm das turmas cadastradas, não de
 * pacotes por paciente. A lista de presença é por data e por aluno, e marcar
 * presença duas vezes no mesmo dia atualiza a mesma linha.
 *
 * As permissões são as mesmas dos pacotes de sessões (`pacotes.*`): a turma é
 * parte do mesmo módulo operacional, e reutilizar as permissões existentes
 * evita criar linhas novas em cada banco de clínica em produção.
 */
class TurmaController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('pacotes.view');

        $mes = $this->mesReferencia($request);

        $turmas = Turma::query()
            ->with(['profissional:id,nome'])
            ->withCount(['pacientes as pacientes_count' => fn ($query) => $query->where('turma_pacientes.is_active', true)])
            ->where('is_active', true)
            ->orderBy('dia_semana')
            ->orderBy('hora_inicio')
            ->get();

        $grade = $turmas->groupBy('dia_semana');

        return view('turmas.index', [
            'turmas' => $turmas,
            'grade' => $grade,
            'calendario' => $this->montarCalendario($turmas, $mes),
            'mes' => $mes,
        ]);
    }

    public function create(): View
    {
        $this->authorize('pacotes.create');

        return view('turmas.create', [
            'profissionais' => Professional::query()
                ->where('is_active', true)
                ->orderBy('nome')
                ->get(['id', 'nome']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('pacotes.create');

        $turma = Turma::create($this->validated($request));

        AuditLogger::log('criar_turma', 'turmas', $turma->id, depois: [
            'nome' => $turma->nome,
            'dia_semana' => $turma->dia_semana,
            'hora_inicio' => $turma->hora_inicio,
        ]);

        return redirect()
            ->route('turmas.show', $turma)
            ->with('success', 'Turma criada.');
    }

    public function show(Request $request, Turma $turma): View
    {
        $this->authorize('pacotes.view');

        $turma->load(['profissional:id,nome', 'pacientes:id,nome,status']);

        /*
         * Só os alunos com vínculo ativo aparecem na chamada. Quem foi removido
         * continua no histórico de presenças, mas não volta para a lista nem
         * pode receber presença nova.
         */
        $alunosAtivos = $turma->pacientes
            ->where('pivot.is_active', true)
            ->values();

        $data = $request->filled('data')
            ? Carbon::parse($request->string('data')->toString())->startOfDay()
            : today();

        $presencas = $turma->presencas()
            ->whereDate('data', $data)
            ->get()
            ->keyBy('paciente_id');

        $historico = $turma->presencas()
            ->with('paciente:id,nome')
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->groupBy(fn (TurmaPresenca $presenca): string => $presenca->data->format('Y-m-d'));

        return view('turmas.show', [
            'turma' => $turma,
            'alunosAtivos' => $alunosAtivos,
            'data' => $data,
            'presencas' => $presencas,
            'historico' => $historico,
            'pacientesDisponiveis' => Paciente::query()
                ->where('status', 'ativo')
                ->whereNotIn('id', $alunosAtivos->pluck('id'))
                ->orderBy('nome')
                ->get(['id', 'nome']),
        ]);
    }

    public function update(Request $request, Turma $turma): RedirectResponse
    {
        $this->authorize('pacotes.update');

        $antes = $turma->only(['nome', 'dia_semana', 'hora_inicio', 'is_active']);

        $turma->update($this->validated($request));

        AuditLogger::log('atualizar_turma', 'turmas', $turma->id, antes: $antes, depois: $turma->only(['nome', 'dia_semana', 'hora_inicio', 'is_active']));

        return redirect()
            ->route('turmas.show', $turma)
            ->with('success', 'Turma atualizada.');
    }

    /**
     * Encerra a turma sem apagar o histórico de alunos e presenças.
     */
    public function destroy(Turma $turma): RedirectResponse
    {
        $this->authorize('pacotes.delete');

        $turma->update(['is_active' => false]);

        AuditLogger::log('encerrar_turma', 'turmas', $turma->id, antes: ['is_active' => true], depois: ['is_active' => false]);

        return redirect()
            ->route('turmas.index')
            ->with('success', 'Turma encerrada. O histórico de presenças foi preservado.');
    }

    public function adicionarPaciente(Request $request, Turma $turma): RedirectResponse
    {
        $this->authorize('pacotes.update');

        $validated = $request->validate([
            'paciente_id' => ['required', 'integer', 'exists:tenant.pacientes,id'],
            'data_inicio' => ['nullable', 'date'],
        ]);

        $turma->pacientes()->syncWithoutDetaching([
            $validated['paciente_id'] => [
                'data_inicio' => $validated['data_inicio'] ?? today()->toDateString(),
                'data_fim' => null,
                'is_active' => true,
            ],
        ]);

        /*
         * `syncWithoutDetaching` não altera um vínculo que já existe. Se o aluno
         * foi removido antes, o pivot fica com `is_active = false`; a atualização
         * explícita é o que o reativa em vez de deixar um vínculo morto.
         */
        $turma->pacientes()->updateExistingPivot($validated['paciente_id'], [
            'data_inicio' => $validated['data_inicio'] ?? today()->toDateString(),
            'data_fim' => null,
            'is_active' => true,
        ]);

        AuditLogger::log('adicionar_paciente_turma', 'turmas', $turma->id, depois: [
            'paciente_id' => $validated['paciente_id'],
        ]);

        return back()->with('success', 'Aluno adicionado à turma.');
    }

    public function removerPaciente(Turma $turma, Paciente $paciente): RedirectResponse
    {
        $this->authorize('pacotes.update');

        $turma->pacientes()->updateExistingPivot($paciente->id, [
            'data_fim' => today()->toDateString(),
            'is_active' => false,
        ]);

        AuditLogger::log('remover_paciente_turma', 'turmas', $turma->id, antes: [
            'paciente_id' => $paciente->id,
        ]);

        return back()->with('success', 'Aluno removido da turma. O histórico de presenças foi preservado.');
    }

    public function registrarPresenca(Request $request, Turma $turma): RedirectResponse
    {
        $this->authorize('pacotes.update');

        $validated = $request->validate([
            'paciente_id' => ['required', 'integer', 'exists:tenant.pacientes,id'],
            'data' => ['required', 'date'],
            'status' => ['required', 'in:'.implode(',', TurmaPresenca::STATUS)],
            'observacoes' => ['nullable', 'string', 'max:500'],
        ]);

        abort_unless(
            $turma->pacientes()->whereKey($validated['paciente_id'])->wherePivot('is_active', true)->exists(),
            404,
        );

        /*
         * A busca é por `whereDate`, não por igualdade na coluna `data`: o cast
         * `date` grava a meia-noite como `Y-m-d H:i:s`, então comparar a string
         * `Y-m-d` nunca casaria e a segunda marcação tentaria inserir de novo,
         * batendo na chave única. `whereDate` compara só o dia e mantém a
         * marcação idempotente.
         */
        $data = Carbon::parse($validated['data'])->startOfDay();

        $presenca = TurmaPresenca::query()
            ->where('turma_id', $turma->id)
            ->where('paciente_id', $validated['paciente_id'])
            ->whereDate('data', $data->toDateString())
            ->first();

        if ($presenca) {
            $presenca->update([
                'status' => $validated['status'],
                'observacoes' => $validated['observacoes'] ?? null,
            ]);
        } else {
            $presenca = TurmaPresenca::create([
                'turma_id' => $turma->id,
                'paciente_id' => $validated['paciente_id'],
                'data' => $data,
                'status' => $validated['status'],
                'observacoes' => $validated['observacoes'] ?? null,
            ]);
        }

        AuditLogger::log('registrar_presenca_turma', 'turma_presencas', $presenca->id, depois: [
            'turma_id' => $turma->id,
            'paciente_id' => $validated['paciente_id'],
            'data' => $presenca->data->format('Y-m-d'),
            'status' => $presenca->status,
        ]);

        return back()->with('success', 'Presença registrada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'servico' => ['nullable', 'string', 'max:40'],
            'profissional_id' => ['nullable', 'integer', 'exists:tenant.professionals,id'],
            'dia_semana' => ['required', 'integer', 'between:1,7'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fim' => ['nullable', 'date_format:H:i', 'after:hora_inicio'],
            'capacidade' => ['nullable', 'integer', 'min:1', 'max:500'],
            'sala' => ['nullable', 'string', 'max:80'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['servico'] = $validated['servico'] ?? 'PILATES';
        $validated['is_active'] = true;

        return $validated;
    }

    private function mesReferencia(Request $request): Carbon
    {
        if ($request->filled('mes')) {
            try {
                return Carbon::createFromFormat('Y-m', $request->string('mes')->toString())->startOfMonth();
            } catch (\Throwable) {
                // Mês inválido não pode quebrar a tela: cai no mês atual.
            }
        }

        return today()->startOfMonth();
    }

    /**
     * Uma entrada por dia do mês, com as turmas que acontecem naquele dia.
     *
     * @param  Collection<int, Turma>  $turmas
     * @return list<array{data: Carbon, turmas: Collection<int, Turma>}>
     */
    private function montarCalendario(Collection $turmas, Carbon $mes): array
    {
        $dias = [];

        $dia = $mes->copy()->startOfMonth();

        while ($dia->month === $mes->month) {
            $doDia = $turmas
                ->where('dia_semana', $dia->dayOfWeekIso)
                ->values();

            $dias[] = ['data' => $dia->copy(), 'turmas' => $doDia];

            $dia->addDay();
        }

        return $dias;
    }
}
