<?php

namespace App\Http\Controllers;

use App\Http\Requests\OdontogramConditionRequest;
use App\Http\Requests\OdontogramToothRequest;
use App\Models\AuditLog;
use App\Models\Odontogram;
use App\Models\OdontogramTooth;
use App\Models\OdontogramToothCondition;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\ToothCondition;
use App\Services\AuditLogger;
use App\Support\Odontograma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OdontogramaController extends Controller
{
    public function index(Paciente $paciente): View
    {
        $this->authorize('odontograma.view');

        return view('odontograma.index', [
            'paciente' => $paciente,
            'payload' => $this->payload($paciente),
        ]);
    }

    /**
     * Estado completo do odontograma em JSON — é o que o editor consome ao
     * recarregar e o que os testes usam para provar a persistência.
     */
    public function dados(Paciente $paciente): JsonResponse
    {
        $this->authorize('odontograma.view');

        return response()->json($this->payload($paciente));
    }

    public function update(Request $request, Paciente $paciente): JsonResponse
    {
        $this->authorize('odontograma.update');

        $dados = $request->validate([
            'denticao' => ['sometimes', 'string', 'in:'.implode(',', array_keys(config('odontograma.denticoes')))],
            'notacao' => ['sometimes', 'string', 'in:'.implode(',', array_keys(config('odontograma.notacoes')))],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);

        $odontogram = $this->odontogramDo($paciente);
        $antes = $odontogram->only(array_keys($dados));
        $odontogram->update($dados);

        AuditLogger::log('atualizar_odontograma', 'odontograms', $odontogram->id, $antes, $odontogram->only(array_keys($dados)));

        return response()->json($this->payload($paciente));
    }

    public function updateTooth(OdontogramToothRequest $request, Paciente $paciente, int $dente): JsonResponse
    {
        $this->garantirDenteValido($dente);

        $odontogram = $this->odontogramDo($paciente);
        $denteModel = $this->denteDo($odontogram, $dente);

        $dados = $request->validated();
        $antes = $denteModel->only(array_keys($dados));
        $denteModel->update($dados);

        AuditLogger::log('atualizar_dente', 'odontogram_teeth', $denteModel->id, $antes, $denteModel->only(array_keys($dados)));

        return response()->json($this->dentePayload($denteModel->fresh(['condicoes.condition'])));
    }

    public function storeCondition(OdontogramConditionRequest $request, Paciente $paciente, int $dente): JsonResponse
    {
        $this->garantirDenteValido($dente);

        $odontogram = $this->odontogramDo($paciente);
        $denteModel = $this->denteDo($odontogram, $dente);

        $condicao = $denteModel->condicoes()->create([
            ...$request->validated(),
            'profissional_id' => $this->profissionalAtual()?->id,
        ]);

        AuditLogger::log(
            'adicionar_condicao_dente',
            'odontogram_tooth_conditions',
            $condicao->id,
            depois: [
                'dente' => $dente,
                'condicao' => $condicao->tooth_condition_id,
                'superficie' => $condicao->superficie,
                'camada' => $condicao->camada,
            ],
        );

        return response()->json($this->dentePayload($denteModel->fresh(['condicoes.condition'])))->setStatusCode(201);
    }

    public function destroyCondition(Paciente $paciente, int $dente, OdontogramToothCondition $condicao): JsonResponse
    {
        $this->authorize('odontograma.update');
        $this->garantirDenteValido($dente);

        $odontogram = $this->odontogramDo($paciente);
        $denteModel = $this->denteDo($odontogram, $dente);

        abort_unless($condicao->odontogram_tooth_id === $denteModel->id, 404);

        $antes = [
            'dente' => $dente,
            'condicao' => $condicao->tooth_condition_id,
            'superficie' => $condicao->superficie,
            'camada' => $condicao->camada,
        ];
        $condicao->delete();

        AuditLogger::log('remover_condicao_dente', 'odontogram_tooth_conditions', $condicao->id, antes: $antes);

        return response()->json($this->dentePayload($denteModel->fresh(['condicoes.condition'])));
    }

    public function destroyTooth(Paciente $paciente, int $dente): JsonResponse
    {
        $this->authorize('odontograma.update');
        $this->garantirDenteValido($dente);

        $odontogram = $this->odontogramDo($paciente);
        $denteModel = $this->denteDo($odontogram, $dente);

        $antes = $this->dentePayload($denteModel->load(['condicoes.condition']));
        $denteModel->condicoes()->delete();
        $denteModel->update(['status' => 'presente', 'observacao' => null, 'profissional_id' => null]);

        AuditLogger::log('limpar_dente', 'odontogram_teeth', $denteModel->id, antes: $antes);

        return response()->json($this->dentePayload($denteModel->fresh(['condicoes.condition'])));
    }

    /**
     * Trilha de auditoria do odontograma: profissional, data/hora, alteração,
     * estado anterior e novo estado — reutiliza o AuditLogger do sistema.
     */
    public function historico(Paciente $paciente): JsonResponse
    {
        $this->authorize('odontograma.view');

        $odontogram = $paciente->odontogramas()->first();
        $dentesIds = $odontogram?->dentes()->pluck('id') ?? collect();
        $condicoesIds = OdontogramToothCondition::whereIn('odontogram_tooth_id', $dentesIds)->pluck('id');

        $registros = AuditLog::query()
            ->where(function ($query) use ($odontogram, $dentesIds, $condicoesIds) {
                $query->where(fn ($q) => $q->where('tabela', 'odontograms')->where('registro_id', $odontogram?->id))
                    ->orWhere(fn ($q) => $q->where('tabela', 'odontogram_teeth')->whereIn('registro_id', $dentesIds))
                    ->orWhere(fn ($q) => $q->where('tabela', 'odontogram_tooth_conditions')->whereIn('registro_id', $condicoesIds));
            })
            ->with('user')
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (AuditLog $log) => [
                'data_hora' => $log->created_at?->format('d/m/Y H:i'),
                'profissional' => $log->user?->name,
                'acao' => $log->acao,
                'antes' => $log->antes,
                'depois' => $log->depois,
            ]);

        return response()->json(['historico' => $registros]);
    }

    private function payload(Paciente $paciente): array
    {
        $odontogram = $this->odontogramDo($paciente);
        $arcadas = $this->arcadas($odontogram->denticao);

        $registrados = $odontogram->dentes()
            ->with('condicoes.condition')
            ->get()
            ->keyBy('dente');

        $todosOsDentes = collect($arcadas)
            ->flatMap(fn ($linhas) => collect($linhas)->flatMap(fn ($linha) => $linha['dentes']))
            ->unique()
            ->map(function (int $numero) use ($registrados) {
                $dente = $registrados->get($numero) ?? new OdontogramTooth(['dente' => $numero, 'status' => 'presente']);

                return $this->dentePayload($dente);
            })
            ->values();

        return [
            'paciente' => ['id' => $paciente->id, 'nome' => $paciente->nome],
            'odontogram' => [
                'id' => $odontogram->id,
                'titulo' => $odontogram->titulo,
                'denticao' => $odontogram->denticao,
                'notacao' => $odontogram->notacao,
                'notas' => $odontogram->notas,
                'atualizado_em' => $odontogram->updated_at?->format('d/m/Y H:i'),
            ],
            'arcadas' => $arcadas,
            'dentes' => $todosOsDentes,
            'catalogo' => ToothCondition::orderBy('is_default', 'desc')->orderBy('name')->get([
                'id', 'name', 'codigo', 'cor', 'icone', 'is_default',
            ]),
            'camadas' => config('odontograma.camadas'),
            'superficies' => config('odontograma.superficies'),
            'status_dente' => config('odontograma.status_dente'),
            'denticoes' => collect(config('odontograma.denticoes'))
                ->map(fn ($denticao) => $denticao['label']),
            'notacoes' => config('odontograma.notacoes'),
        ];
    }

    /**
     * Linhas da arcada na ordem visual: quadrante direito da tela primeiro,
     * meio da arcada no centro — vale para permanente, decídua e mista.
     *
     * @return array<string, array<int, array{label: string, dentes: array<int, int>}>>
     */
    private function arcadas(string $denticao): array
    {
        $selecionadas = $denticao === 'mista'
            ? ['permanente', 'decidua']
            : [$denticao];

        $arcadas = ['superior' => [], 'inferior' => []];

        foreach ($selecionadas as $tipo) {
            $config = config("odontograma.denticoes.{$tipo}");
            [$quadrante1, $quadrante2, $quadrante3, $quadrante4] = array_chunk($config['dentes'], count($config['dentes']) / 4);

            // Tela: quadrante direito do paciente à esquerda, arcada no centro.
            $arcadas['superior'][] = [
                'label' => $config['label'],
                'dentes' => array_merge(array_reverse($quadrante1), $quadrante2),
            ];
            $arcadas['inferior'][] = [
                'label' => $config['label'],
                'dentes' => array_merge(array_reverse($quadrante4), $quadrante3),
            ];
        }

        return $arcadas;
    }

    private function dentePayload(OdontogramTooth $dente): array
    {
        $numero = (int) $dente->dente;

        return [
            'numero' => $numero,
            'nome' => Odontograma::nomeDoDente($numero),
            'universal' => Odontograma::fdiParaUniversal($numero),
            'palmer' => Odontograma::fdiParaPalmer($numero),
            'denticao' => Odontograma::denticao($numero),
            'status' => $dente->status,
            'observacao' => $dente->observacao,
            'condicoes' => $dente->condicoes->map(fn (OdontogramToothCondition $condicao) => [
                'id' => $condicao->id,
                'tooth_condition_id' => $condicao->tooth_condition_id,
                'nome' => $condicao->condition?->name,
                'cor' => $condicao->condition?->cor,
                'superficie' => $condicao->superficie,
                'camada' => $condicao->camada,
                'descricao' => $condicao->descricao,
                'criado_em' => $condicao->created_at?->format('d/m/Y H:i'),
            ])->values(),
        ];
    }

    private function odontogramDo(Paciente $paciente): Odontogram
    {
        return $paciente->odontogramas()->firstOrCreate(
            ['status' => 'ativo'],
            ['titulo' => 'Odontograma principal'],
        );
    }

    private function denteDo(Odontogram $odontogram, int $dente): OdontogramTooth
    {
        return $odontogram->dentes()->firstOrCreate(
            ['dente' => $dente],
            ['status' => 'presente', 'profissional_id' => $this->profissionalAtual()?->id],
        );
    }

    private function garantirDenteValido(int $dente): void
    {
        abort_unless(Odontograma::fdiValido($dente), 422, 'Número de dente FDI inválido.');
    }

    private function profissionalAtual(): ?Professional
    {
        return Professional::where('user_id', auth()->id())->first();
    }
}
