<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\NotaFiscal;
use App\Models\Paciente;
use App\Services\Fiscal\FiscalException;
use App\Services\Fiscal\NotaFiscalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Módulo fiscal no painel da clínica.
 *
 * A emissão de verdade fica no NotaFiscalService; aqui só há validação,
 * autorização e resposta HTTP. Erro de provedor vira `session('error')`, não
 * erro 500.
 */
class FiscalController extends Controller
{
    public function __construct(private readonly NotaFiscalService $fiscal) {}

    public function index(Request $request): View
    {
        $this->authorize('fiscal.view');

        $notas = NotaFiscal::query()
            ->with('paciente')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->value()))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->string('tipo')->value()))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $busca = $request->string('busca')->value();
                $q->where(function ($sub) use ($busca) {
                    $sub->where('numero', 'like', "%{$busca}%")
                        ->orWhere('chave', 'like', "%{$busca}%")
                        ->orWhereHas('paciente', fn ($p) => $p->where('nome', 'like', "%{$busca}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $resumo = NotaFiscal::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('fiscal.index', [
            'notas' => $notas,
            'resumo' => $resumo,
            'totalAutorizado' => (float) NotaFiscal::query()->autorizadas()->sum('valor_total'),
            'provider' => $this->fiscal->providerNome(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('fiscal.create');

        return view('fiscal.create', [
            'pacientes' => Paciente::query()->orderBy('nome')->get(['id', 'nome', 'cpf']),
            'agendamentos' => Appointment::query()
                ->with('paciente:id,nome')
                ->orderByDesc('inicio')
                ->limit(100)
                ->get(['id', 'paciente_id', 'inicio', 'titulo']),
            'pacienteSelecionado' => $request->integer('paciente_id') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('fiscal.create');

        $dados = $this->validar($request);

        $nota = DB::transaction(function () use ($dados) {
            $valor = (float) $dados['valor_total'];
            $desconto = (float) ($dados['valor_desconto'] ?? 0);
            $liquido = max(0, $valor - $desconto);
            $aliquota = (float) ($dados['aliquota_iss'] ?? config('fiscal.aliquota_iss', 5));

            return NotaFiscal::create(array_merge($dados, [
                'status' => NotaFiscal::STATUS_RASCUNHO,
                'ambiente' => config('fiscal.ambiente', 'homologacao'),
                'serie' => ($dados['serie'] ?? null) ?: (string) config('fiscal.serie', '1'),                'valor_liquido' => $liquido,
                'aliquota_iss' => $aliquota,
                'valor_iss' => round($liquido * ($aliquota / 100), 2),
            ]));
        });

        return redirect()
            ->route('fiscal.show', $nota)
            ->with('success', 'Rascunho criado. Confira os dados e emita a nota.');
    }

    public function show(NotaFiscal $notaFiscal): View
    {
        $this->authorize('fiscal.view');

        $notaFiscal->load(['paciente', 'consulta']);

        return view('fiscal.show', [
            'notaFiscal' => $notaFiscal,
            'provider' => $this->fiscal->providerNome(),
            'podeEmitir' => $notaFiscal->podeEmitir(),
        ]);
    }

    public function update(Request $request, NotaFiscal $notaFiscal): RedirectResponse
    {
        $this->authorize('fiscal.update');

        if ($notaFiscal->status !== NotaFiscal::STATUS_RASCUNHO) {
            return back()->with('error', 'Só é possível editar rascunhos.');
        }

        $dados = $this->validar($request);
        $valor = (float) $dados['valor_total'];
        $desconto = (float) ($dados['valor_desconto'] ?? 0);
        $liquido = max(0, $valor - $desconto);
        $aliquota = (float) ($dados['aliquota_iss'] ?? config('fiscal.aliquota_iss', 5));

        $notaFiscal->update(array_merge($dados, [
            'valor_liquido' => $liquido,
            'aliquota_iss' => $aliquota,
            'valor_iss' => round($liquido * ($aliquota / 100), 2),
        ]));

        return redirect()->route('fiscal.show', $notaFiscal)->with('success', 'Rascunho atualizado.');
    }

    public function destroy(NotaFiscal $notaFiscal): RedirectResponse
    {
        $this->authorize('fiscal.delete');

        if ($notaFiscal->status !== NotaFiscal::STATUS_RASCUNHO) {
            return back()->with('error', 'Só é possível excluir rascunhos.');
        }

        $notaFiscal->delete();

        return redirect()->route('fiscal.index')->with('success', 'Rascunho excluído.');
    }

    public function emitir(NotaFiscal $notaFiscal): RedirectResponse
    {
        $this->authorize('fiscal.emitir');

        try {
            $notaFiscal = $this->fiscal->emitir($notaFiscal);
        } catch (FiscalException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($notaFiscal->status === NotaFiscal::STATUS_REJEITADA) {
            return back()->with('error', 'Nota rejeitada: '.$notaFiscal->motivo_rejeicao);
        }

        return back()->with('success', 'Nota emitida. Chave '.$notaFiscal->chave);
    }

    public function cancelar(Request $request, NotaFiscal $notaFiscal): RedirectResponse
    {
        $this->authorize('fiscal.cancelar');

        $dados = $request->validate([
            'motivo' => ['required', 'string', 'min:15', 'max:255'],
        ]);

        try {
            $this->fiscal->cancelar($notaFiscal, $dados['motivo']);
        } catch (FiscalException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Nota cancelada.');
    }

    public function consultar(NotaFiscal $notaFiscal): RedirectResponse
    {
        $this->authorize('fiscal.view');

        try {
            $notaFiscal = $this->fiscal->consultar($notaFiscal);
        } catch (FiscalException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Status atualizado: '.$notaFiscal->statusLabel().'.');
    }

    public function xml(NotaFiscal $notaFiscal): Response
    {
        $this->authorize('fiscal.view');

        return response($this->fiscal->xml($notaFiscal), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="nota-fiscal-'.$notaFiscal->id.'.xml"',
        ]);
    }

    public function imprimir(NotaFiscal $notaFiscal): View
    {
        $this->authorize('fiscal.view');

        $notaFiscal->load(['paciente', 'consulta']);

        return view('fiscal.imprimir', compact('notaFiscal'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        return $request->validate([
            'paciente_id' => [
                'nullable',
                'integer',
                function (string $atributo, mixed $valor, \Closure $falhar) {
                    if ($valor !== null && ! Paciente::query()->whereKey($valor)->exists()) {
                        $falhar('Paciente inexistente nesta clínica.');
                    }
                },
            ],
            'consulta_id' => [
                'nullable',
                'integer',
                function (string $atributo, mixed $valor, \Closure $falhar) {
                    if ($valor !== null && ! Appointment::query()->whereKey($valor)->exists()) {
                        $falhar('Agendamento inexistente nesta clínica.');
                    }
                },
            ],
            'tipo' => ['required', Rule::in(['nfse', 'nfe', 'nfce'])],
            'serie' => ['nullable', 'string', 'max:10'],
            'valor_total' => ['required', 'numeric', 'gt:0'],
            'valor_desconto' => ['nullable', 'numeric', 'min:0', 'lte:valor_total'],
            'aliquota_iss' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'codigo_servico' => ['nullable', 'string', 'max:20'],
            'descricao_servico' => ['nullable', 'string', 'max:255'],
            'tomador_nome' => ['nullable', 'string', 'max:255'],
            'tomador_documento' => ['nullable', 'string', 'max:20'],
            'tomador_email' => ['nullable', 'email', 'max:191'],
            'tomador_endereco' => ['nullable', 'string', 'max:255'],
            'tomador_cidade' => ['nullable', 'string', 'max:120'],
            'tomador_uf' => ['nullable', 'string', 'size:2'],
            'tomador_cep' => ['nullable', 'string', 'max:10'],
        ]);
    }
}
