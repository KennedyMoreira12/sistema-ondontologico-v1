<?php

namespace App\Http\Controllers;

use App\Exceptions\OperacaoFinanceiraInvalida;
use App\Http\Requests\ManualPaymentRequest;
use App\Http\Requests\PaymentLinkRequest;
use App\Models\AccountsReceivable;
use App\Models\Paciente;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\AuditLogger;
use App\Services\Financeiro\RecebimentoService;
use App\Services\Pacientes\PacienteNomeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Pagamentos recebidos.
 *
 * A tela existe por causa dos lançamentos importados da planilha: eles têm
 * valor e data, mas o nome do paciente ficou no texto. Aqui a recepção revisa
 * um lançamento por vez e escolhe o paciente — nunca há merge automático de
 * cadastros. Pagamentos novos podem ser lançados manualmente, sempre criando
 * a conta a receber junto.
 */
class PagamentoController extends Controller
{
    public function __construct(
        private RecebimentoService $recebimentos,
        private PacienteNomeService $nomes,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('financeiro.view');

        $de = $request->filled('de') ? $request->date('de')->startOfDay() : null;
        $ate = $request->filled('ate') ? $request->date('ate')->endOfDay() : null;

        $pagamentos = Payment::query()
            ->with(['paciente:id,nome', 'paymentMethod:id,label'])
            ->when($request->filled('busca'), function ($query) use ($request): void {
                $busca = $request->string('busca')->toString();

                $query->where(function ($sub) use ($busca): void {
                    $sub->where('observacoes', 'like', "%{$busca}%")
                        ->orWhere('servico', 'like', "%{$busca}%")
                        ->orWhereHas('paciente', fn ($paciente) => $paciente->where('nome', 'like', "%{$busca}%"));
                });
            })
            ->when($request->input('vinculo') === 'sem', fn ($query) => $query->whereNull('paciente_id'))
            ->when($request->input('vinculo') === 'com', fn ($query) => $query->whereNotNull('paciente_id'))
            ->when($de !== null, fn ($query) => $query->where('data', '>=', $de))
            ->when($ate !== null, fn ($query) => $query->where('data', '<=', $ate))
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $resumo = [
            'total' => (float) Payment::query()->sum('valor'),
            'quantidade' => Payment::query()->count(),
            'sem_vinculo' => Payment::query()->whereNull('paciente_id')->count(),
            'sem_vinculo_valor' => (float) Payment::query()->whereNull('paciente_id')->sum('valor'),
        ];

        return view('financeiro.pagamentos.index', [
            'pagamentos' => $pagamentos,
            'resumo' => $resumo,
            'formasPagamento' => PaymentMethod::query()->orderBy('label')->get(),
            'pacientes' => Paciente::query()->where('status', 'ativo')->orderBy('nome')->limit(300)->get(['id', 'nome']),
        ]);
    }

    /**
     * Um lançamento por vez, com os candidatos sugeridos pelo nome.
     */
    public function revisar(Payment $pagamento): View
    {
        $this->authorize('financeiro.view');

        $nomeNaPlanilha = $this->nomes->nomeDoPagamento($pagamento);
        $candidatos = $nomeNaPlanilha !== null
            ? $this->nomes->candidatos($nomeNaPlanilha, 10)
            : collect();

        $busca = trim((string) request('paciente'));

        $pacientes = Paciente::query()
            ->select(['id', 'nome', 'status'])
            ->when($busca !== '', fn ($query) => $query->where('nome', 'like', "%{$busca}%"))
            ->orderBy('nome')
            ->limit(100)
            ->get();

        return view('financeiro.pagamentos.revisar', [
            'pagamento' => $pagamento->load(['paciente:id,nome', 'paymentMethod:id,label']),
            'nomeNaPlanilha' => $nomeNaPlanilha,
            'candidatos' => $candidatos,
            'pacientes' => $pacientes,
            'busca' => $busca,
        ]);
    }

    public function vincular(PaymentLinkRequest $request, Payment $pagamento): RedirectResponse
    {
        if ($pagamento->paciente_id !== null) {
            return back()->withErrors([
                'paciente_id' => 'Este pagamento já está vinculado a um paciente. Desfaça o vínculo antes de trocar.',
            ]);
        }

        $paciente = Paciente::query()->findOrFail($request->integer('paciente_id'));

        $this->nomes->vincularPagamento($pagamento, $paciente);

        AuditLogger::log(
            'vincular_pagamento',
            'payments',
            $pagamento->id,
            antes: ['paciente_id' => null],
            depois: ['paciente_id' => $paciente->id],
        );

        return redirect()
            ->route('financeiro.pagamentos.revisar', $pagamento)
            ->with('success', "Pagamento vinculado a {$paciente->nome}.");
    }

    public function desvincular(Payment $pagamento): RedirectResponse
    {
        $this->authorize('financeiro.update');

        $antes = $pagamento->paciente_id;

        if ($antes !== null) {
            $pagamento->forceFill(['paciente_id' => null])->save();

            AuditLogger::log(
                'desvincular_pagamento',
                'payments',
                $pagamento->id,
                antes: ['paciente_id' => $antes],
                depois: ['paciente_id' => null],
            );
        }

        return back()->with('success', 'Vínculo removido. O pagamento continua somando no caixa.');
    }

    /**
     * Lançamento manual: conta a receber quitada + pagamento, na mesma transação.
     */
    public function store(ManualPaymentRequest $request): RedirectResponse
    {
        $dados = $request->validated();

        try {
            $pagamento = DB::connection('tenant')->transaction(function () use ($dados): Payment {
                $conta = AccountsReceivable::create([
                    'paciente_id' => $dados['paciente_id'],
                    'descricao' => $dados['descricao'],
                    'servico' => $dados['servico'] ?? null,
                    'vencimento' => $dados['data'],
                    'valor' => $dados['valor'],
                    'valor_pago' => 0,
                    'status' => 'aberta',
                ]);

                return $this->recebimentos->receber($conta, [
                    'valor' => $dados['valor'],
                    'payment_method_id' => $dados['payment_method_id'],
                    'data' => $dados['data'],
                    'observacoes' => $dados['observacoes'] ?? null,
                    'servico' => $dados['servico'] ?? null,
                ]);
            });
        } catch (OperacaoFinanceiraInvalida $excecao) {
            return back()->withErrors(['valor' => $excecao->getMessage()])->withInput();
        }

        AuditLogger::log('registrar_pagamento_manual', 'payments', $pagamento->id, depois: $pagamento->only(['paciente_id', 'valor', 'data']));

        return redirect()
            ->route('financeiro.pagamentos.index')
            ->with('success', 'Pagamento de R$ '.number_format((float) $pagamento->valor, 2, ',', '.').' registrado.');
    }
}
