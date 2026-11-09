<?php

namespace App\Http\Controllers;

use App\Models\AccountsPayablePayment;
use App\Models\AccountsReceivable;
use App\Models\Appointment;
use App\Models\ConsumoSessao;
use App\Models\Paciente;
use App\Models\PacoteSessao;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RelatorioController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('relatorios.view');

        [$de, $ate] = $this->periodo($request);

        $resumoFinanceiro = [
            'a_receber' => (float) AccountsReceivable::where('status', '!=', 'pago')->sum('valor'),
            'em_aberto' => (float) AccountsReceivable::where('status', '!=', 'pago')->sum(DB::raw('valor - valor_pago')),
            'pago' => (float) AccountsReceivable::where('status', 'pago')->sum('valor'),
            'recebido_no_periodo' => (float) DB::connection('tenant')->table('payments')
                ->whereBetween('data', [$de->copy()->startOfDay(), $ate->copy()->endOfDay()])
                ->sum('valor'),
        ];

        $contas = AccountsReceivable::query()
            ->with('paciente')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->whereBetween('vencimento', [$de->copy()->startOfDay(), $ate->copy()->endOfDay()])
            ->orderBy('vencimento')
            ->get();

        $consultas = Appointment::query()
            ->with(['paciente', 'profissional', 'status'])
            ->when($request->filled('status_agenda'), fn ($q) => $q->whereHas('status', fn ($s) => $s->where('name', $request->string('status_agenda'))))
            ->whereBetween('inicio', [$de->copy()->startOfDay(), $ate->copy()->endOfDay()])
            ->orderBy('inicio')
            ->get();

        $resumoAgenda = [
            'total' => $consultas->count(),
            'atendidas' => $consultas->where('status.name', 'atendido')->count(),
            'canceladas' => $consultas->where('status.name', 'cancelado')->count(),
            'faltas' => $consultas->where('status.name', 'faltou')->count(),
        ];

        $produtosBaixo = Product::where('controlar_estoque', true)
            ->whereColumn('estoque', '<=', 'estoque_minimo')
            ->orderBy('estoque')
            ->get();

        $movimentacoes = StockMovement::query()
            ->with(['product', 'user'])
            ->whereBetween('created_at', [$de->copy()->startOfDay(), $ate->copy()->endOfDay()])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $resumoEstoque = [
            'produtos' => Product::count(),
            'baixo' => $produtosBaixo->count(),
            'entradas' => $movimentacoes->where('tipo', 'entrada')->sum('quantidade'),
            'saidas' => $movimentacoes->where('tipo', 'saida')->sum('quantidade'),
        ];

        return view('relatorios.index', compact(
            'de',
            'ate',
            'resumoFinanceiro',
            'contas',
            'consultas',
            'resumoAgenda',
            'produtosBaixo',
            'movimentacoes',
            'resumoEstoque',
        ));
    }

    /**
     * Receitas do período por serviço, forma de pagamento e mês.
     *
     * É a leitura que substitui as abas de PAGAMENTOS: o total do período, o
     * que entrou em cada serviço e a comparação com as saídas pagas.
     */
    public function receitasMensais(Request $request): View
    {
        $this->authorize('relatorios.view');

        [$de, $ate] = $this->periodo($request);

        $pagamentos = Payment::query()
            ->with(['paciente:id,nome', 'paymentMethod:id,label'])
            ->whereBetween('data', [$de->copy()->startOfDay(), $ate->copy()->endOfDay()])
            ->orderBy('data')
            ->orderBy('id')
            ->get();

        $totalRecebido = (float) $pagamentos->sum('valor');

        $porServico = $pagamentos
            ->groupBy(fn (Payment $pagamento): string => $pagamento->servico ?: 'Sem serviço')
            ->map(fn ($grupo, string $servico): array => [
                'servico' => $servico,
                'quantidade' => $grupo->count(),
                'total' => (float) $grupo->sum('valor'),
            ])
            ->sortByDesc('total')
            ->values();

        $porForma = $pagamentos
            ->groupBy(fn (Payment $pagamento): string => $pagamento->paymentMethod?->label ?? 'Sem forma')
            ->map(fn ($grupo, string $forma): array => [
                'forma' => $forma,
                'quantidade' => $grupo->count(),
                'total' => (float) $grupo->sum('valor'),
            ])
            ->sortByDesc('total')
            ->values();

        $porMes = $pagamentos
            ->groupBy(fn (Payment $pagamento): string => $pagamento->data?->format('Y-m') ?? 'Sem data')
            ->map(fn ($grupo, string $mes): array => [
                'mes' => $mes,
                'quantidade' => $grupo->count(),
                'total' => (float) $grupo->sum('valor'),
            ])
            ->sortBy('mes')
            ->values();

        $totalPago = (float) AccountsPayablePayment::query()
            ->whereBetween('data', [$de->copy()->startOfDay(), $ate->copy()->endOfDay()])
            ->sum('valor');

        $semPaciente = $pagamentos->whereNull('paciente_id');

        return view('relatorios.receitas-mensais', [
            'de' => $de,
            'ate' => $ate,
            'pagamentos' => $pagamentos,
            'totalRecebido' => $totalRecebido,
            'totalPago' => $totalPago,
            'saldo' => round($totalRecebido - $totalPago, 2),
            'porServico' => $porServico,
            'porForma' => $porForma,
            'porMes' => $porMes,
            'semPacienteQuantidade' => $semPaciente->count(),
            'semPacienteValor' => (float) $semPaciente->sum('valor'),
        ]);
    }

    /**
     * Presença, falta e cancelamento no período.
     */
    public function presencaFaltas(Request $request): View
    {
        $this->authorize('relatorios.view');

        [$de, $ate] = $this->periodo($request);

        $consultas = Appointment::query()
            ->with(['paciente:id,nome', 'profissional:id,nome', 'status:id,name,label,cor'])
            ->whereBetween('inicio', [$de->copy()->startOfDay(), $ate->copy()->endOfDay()])
            ->orderBy('inicio')
            ->get();

        $total = $consultas->count();
        $atendidos = $consultas->filter(fn (Appointment $c): bool => $c->status?->name === 'atendido')->count();
        $faltas = $consultas->filter(fn (Appointment $c): bool => $c->status?->name === 'faltou')->count();
        $cancelados = $consultas->filter(fn (Appointment $c): bool => $c->status?->name === 'cancelado')->count();
        $aguardando = $consultas->filter(fn (Appointment $c): bool => in_array($c->status?->name, ['agendado', 'confirmado', 'em_atendimento'], true))->count();

        $porProfissional = $consultas
            ->groupBy(fn (Appointment $c): string => $c->profissional?->nome ?? 'Sem profissional')
            ->map(function ($grupo, string $nome): array {
                $total = $grupo->count();
                $faltas = $grupo->filter(fn (Appointment $c): bool => $c->status?->name === 'faltou')->count();

                return [
                    'profissional' => $nome,
                    'total' => $total,
                    'atendidos' => $grupo->filter(fn (Appointment $c): bool => $c->status?->name === 'atendido')->count(),
                    'faltas' => $faltas,
                    'taxa_faltas' => $total > 0 ? round($faltas / $total * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('total')
            ->values();

        $porMes = $consultas
            ->groupBy(fn (Appointment $c): string => $c->inicio->format('Y-m'))
            ->map(function ($grupo, string $mes): array {
                $total = $grupo->count();
                $faltas = $grupo->filter(fn (Appointment $c): bool => $c->status?->name === 'faltou')->count();

                return [
                    'mes' => $mes,
                    'total' => $total,
                    'atendidos' => $grupo->filter(fn (Appointment $c): bool => $c->status?->name === 'atendido')->count(),
                    'faltas' => $faltas,
                    'taxa_faltas' => $total > 0 ? round($faltas / $total * 100, 1) : 0.0,
                ];
            })
            ->sortBy('mes')
            ->values();

        /*
         * Equivalente às fórmulas COUNTIF/SUM da planilha DEPILAÇÃO LASER:
         * a planilha conta presenças por área (AXILA, PERNAS...) em cada dia.
         * O agrupamento usa a área gravada no agendamento; consultas sem área
         * ficam em "Sem área" em vez de sumirem do total.
         */
        $porArea = $consultas
            ->groupBy(fn (Appointment $c): string => $c->area ?: 'Sem área')
            ->map(function ($grupo, string $area): array {
                $total = $grupo->count();
                $faltas = $grupo->filter(fn (Appointment $c): bool => $c->status?->name === 'faltou')->count();

                return [
                    'area' => $area,
                    'total' => $total,
                    'atendidos' => $grupo->filter(fn (Appointment $c): bool => $c->status?->name === 'atendido')->count(),
                    'faltas' => $faltas,
                    'taxa_faltas' => $total > 0 ? round($faltas / $total * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('total')
            ->values();

        return view('relatorios.presenca-faltas', [
            'de' => $de,
            'ate' => $ate,
            'consultas' => $consultas,
            'total' => $total,
            'atendidos' => $atendidos,
            'faltas' => $faltas,
            'cancelados' => $cancelados,
            'aguardando' => $aguardando,
            'taxaFaltas' => $total > 0 ? round($faltas / $total * 100, 1) : 0.0,
            'porProfissional' => $porProfissional,
            'porMes' => $porMes,
            'porArea' => $porArea,
        ]);
    }

    /**
     * Pacotes de sessões: contratado, consumido e restante.
     */
    public function pacotesSessoes(Request $request): View
    {
        $this->authorize('relatorios.view');

        [$de, $ate] = $this->periodo($request);

        $pacotes = PacoteSessao::query()
            ->with(['paciente:id,nome', 'profissional:id,nome'])
            ->orderByDesc('id')
            ->get();

        $consumos = ConsumoSessao::query()
            ->with([
                'pacote:id,nome,servico,total_sessoes,sessoes_usadas',
                'paciente:id,nome',
                'profissional:id,nome',
            ])
            ->whereBetween('data', [$de->copy()->startOfDay(), $ate->copy()->endOfDay()])
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->get();

        $ativos = $pacotes->filter(fn (PacoteSessao $pacote): bool => $pacote->estaAtivo());

        $porServico = $consumos
            ->groupBy(fn (ConsumoSessao $consumo): string => $consumo->pacote?->servico ?: 'Sem serviço')
            ->map(fn ($grupo, string $servico): array => [
                'servico' => $servico,
                'quantidade' => $grupo->count(),
                'reposicoes' => $grupo->where('reposicao', true)->count(),
            ])
            ->sortByDesc('quantidade')
            ->values();

        return view('relatorios.pacotes-sessoes', [
            'de' => $de,
            'ate' => $ate,
            'pacotes' => $pacotes,
            'consumos' => $consumos,
            'totalPacotes' => $pacotes->count(),
            'pacotesAtivos' => $ativos->count(),
            'sessoesContratadas' => (int) $pacotes->sum('total_sessoes'),
            'sessoesUsadas' => (int) $pacotes->sum('sessoes_usadas'),
            'sessoesRestantes' => max(0, (int) $pacotes->sum('total_sessoes') - (int) $pacotes->sum('sessoes_usadas')),
            'consumidasNoPeriodo' => $consumos->count(),
            'reposicoesNoPeriodo' => $consumos->where('reposicao', true)->count(),
            'porServico' => $porServico,
        ]);
    }

    /**
     * Aniversariantes do período.
     *
     * Lista pacientes cujo aniversário (dia/mês de nascimento) ocorre no
     * período informado, com sinalizador "chegando" para aniversários em até
     * 2 dias. Equivalente funcional às fórmulas "TÁ CHEGANDO!" da planilha
     * ANIVERSARIANTES.xlsx. Não dispara alertas nem notificações — apenas
     * disponibiliza o relatório para consulta.
     */
    public function aniversariantes(Request $request): View
    {
        $this->authorize('relatorios.view');

        $mes = $request->filled('mes') ? (int) $request->integer('mes') : now()->month;
        $diaChegando = 2;

        $hoje = now();
        $anoAtual = $hoje->year;

        $pacientes = Paciente::query()
            ->whereNotNull('nascimento')
            ->where('status', 'ativo')
            ->get()
            ->map(function (Paciente $paciente) use ($mes, $anoAtual, $diaChegando, $hoje): ?array {
                $nascimento = $paciente->nascimento;
                if ($nascimento === null) {
                    return null;
                }

                $mesNascimento = $nascimento->month;
                $diaNascimento = $nascimento->day;

                if ($mesNascimento !== $mes) {
                    return null;
                }

                $aniversarioAnoAtual = \Carbon\Carbon::create($anoAtual, $mesNascimento, $diaNascimento);
                $diasAteAniversario = $hoje->diffInDays($aniversarioAnoAtual, false);

                // Se já passou este ano, verificar no próximo ano
                if ($diasAteAniversario < 0) {
                    $aniversarioAnoAtual = \Carbon\Carbon::create($anoAtual + 1, $mesNascimento, $diaNascimento);
                    $diasAteAniversario = $hoje->diffInDays($aniversarioAnoAtual, false);
                }

                $chegando = $diasAteAniversario >= 0 && $diasAteAniversario <= $diaChegando;

                return [
                    'paciente' => $paciente,
                    'nascimento' => $nascimento,
                    'dias_ate_aniversario' => $diasAteAniversario,
                    'chegando' => $chegando,
                    'idade' => $hoje->diffInYears($nascimento),
                ];
            })
            ->filter()
            ->sortBy(fn (array $item): int => $item['dias_ate_aniversario'])
            ->values();

        return view('relatorios.aniversariantes', [
            'mes' => $mes,
            'pacientes' => $pacientes,
            'total' => $pacientes->count(),
            'chegando' => $pacientes->filter(fn (array $item): bool => $item['chegando'])->count(),
        ]);
    }

    /**
     * Exportação CSV compatível com Excel: BOM UTF-8 e separador ponto e vírgula.
     */
    public function exportarCsv(Request $request, string $tipo): StreamedResponse
    {
        $this->authorize('relatorios.view');

        [$de, $ate] = $this->periodo($request);

        $linhas = $this->linhasCsv($tipo, $de, $ate);

        abort_if($linhas === null, 404, 'Tipo de exportação desconhecido.');

        $nomeArquivo = sprintf(
            '%s_%s_%s.csv',
            $tipo,
            $de->format('Y-m-d'),
            $ate->format('Y-m-d'),
        );

        return response()->streamDownload(function () use ($linhas): void {
            $saida = fopen('php://output', 'w');

            fwrite($saida, "\xEF\xBB\xBF");

            foreach ($linhas as $linha) {
                fputcsv($saida, array_map($this->celulaCsv(...), $linha), ';');
            }

            fclose($saida);
        }, $nomeArquivo, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periodo(Request $request): array
    {
        $de = $request->filled('de') ? $request->date('de')->startOfDay() : now()->startOfMonth();
        $ate = $request->filled('ate') ? $request->date('ate')->endOfDay() : now()->endOfDay();

        /*
         * O usuário pode digitar a data final antes da inicial. Em vez de
         * devolver uma tela vazia sem pista, o intervalo é invertido para o
         * período que ele quis consultar.
         */
        if ($de->greaterThan($ate)) {
            [$de, $ate] = [$ate->copy()->startOfDay(), $de->copy()->endOfDay()];
        }

        return [$de, $ate];
    }

    /**
     * @return array<int, array<int, string|int|float|null>>|null
     */
    private function linhasCsv(string $tipo, Carbon $de, Carbon $ate): ?array
    {
        return match ($tipo) {
            'receitas' => $this->linhasReceitas($de, $ate),
            'presenca' => $this->linhasPresenca($de, $ate),
            'pacotes' => $this->linhasPacotes($de, $ate),
            default => null,
        };
    }

    /**
     * @return array<int, array<int, string|int|float|null>>
     */
    private function linhasReceitas(Carbon $de, Carbon $ate): array
    {
        $linhas = [
            ['Data', 'Paciente', 'Serviço', 'Forma de pagamento', 'Valor', 'Observações'],
        ];

        Payment::query()
            ->with(['paciente:id,nome', 'paymentMethod:id,label'])
            ->whereBetween('data', [$de->copy()->startOfDay(), $ate->copy()->endOfDay()])
            ->orderBy('data')
            ->orderBy('id')
            ->each(function (Payment $pagamento) use (&$linhas): void {
                $linhas[] = [
                    $pagamento->data?->format('d/m/Y') ?? '',
                    $pagamento->paciente?->nome ?? 'Não vinculado',
                    $pagamento->servico ?? '',
                    $pagamento->paymentMethod?->label ?? '',
                    number_format((float) $pagamento->valor, 2, ',', '.'),
                    $pagamento->observacoes ?? '',
                ];
            });

        return $linhas;
    }

    /**
     * @return array<int, array<int, string|int|float|null>>
     */
    private function linhasPresenca(Carbon $de, Carbon $ate): array
    {
        $linhas = [
            ['Data', 'Hora', 'Paciente', 'Profissional', 'Serviço', 'Área', 'Status'],
        ];

        Appointment::query()
            ->with(['paciente:id,nome', 'profissional:id,nome', 'status:id,label'])
            ->whereBetween('inicio', [$de->copy()->startOfDay(), $ate->copy()->endOfDay()])
            ->orderBy('inicio')
            ->each(function (Appointment $consulta) use (&$linhas): void {
                $linhas[] = [
                    $consulta->inicio->format('d/m/Y'),
                    $consulta->inicio->format('H:i'),
                    $consulta->paciente?->nome ?? '',
                    $consulta->profissional?->nome ?? '',
                    $consulta->servico ?? '',
                    $consulta->area ?? '',
                    $consulta->status?->label ?? '',
                ];
            });

        return $linhas;
    }

    /**
     * @return array<int, array<int, string|int|float|null>>
     */
    private function linhasPacotes(Carbon $de, Carbon $ate): array
    {
        $linhas = [
            ['Pacote', 'Paciente', 'Serviço', 'Total de sessões', 'Usadas', 'Restantes', 'Status', 'Validade'],
        ];

        PacoteSessao::query()
            ->with('paciente:id,nome')
            ->orderByDesc('id')
            ->each(function (PacoteSessao $pacote) use (&$linhas): void {
                $linhas[] = [
                    $pacote->nome,
                    $pacote->paciente?->nome ?? '',
                    $pacote->servico ?? '',
                    $pacote->total_sessoes,
                    $pacote->consumidas(),
                    $pacote->restantes(),
                    $pacote->status,
                    $pacote->validade_em?->format('d/m/Y') ?? '',
                ];
            });

        return $linhas;
    }

    /**
     * Evita que um texto digitado em campo livre seja interpretado como
     * fórmula ao abrir o CSV no Excel.
     */
    private function celulaCsv(mixed $valor): string
    {
        $texto = (string) $valor;

        if ($texto !== '' && in_array($texto[0], ['=', '+', '-', '@'], true)) {
            return "'".$texto;
        }

        return $texto;
    }
}
