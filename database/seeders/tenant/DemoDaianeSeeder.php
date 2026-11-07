<?php

namespace Database\Seeders\tenant;

use App\Models\AccountsPayable;
use App\Models\AccountsPayablePayment;
use App\Models\AccountsReceivable;
use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Central\Clinic;
use App\Models\Central\User;
use App\Models\ConsumoSessao;
use App\Models\EvolucaoSessao;
use App\Models\FinancialCategory;
use App\Models\Paciente;
use App\Models\PacoteSessao;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Procedure;
use App\Models\Professional;
use App\Models\Room;
use App\Models\Specialty;
use App\Models\Supplier;
use App\Services\Tenant\TenantManager;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Demonstração da clínica Daiane.
 *
 * Reaproveita a demonstração de fisioterapia (avaliação, evolução, pacotes e
 * financeiro) e acrescenta os serviços que são específicos da operação dela:
 * Pilates, laser e massagem, além de contas a pagar com despesas recorrentes.
 *
 * É um seeder de DEMONSTRAÇÃO: mexe no catálogo e cria dados fictícios. Só roda
 * em ambiente controlado, via `tenants:seed --demo-daiane`.
 */
class DemoDaianeSeeder extends Seeder
{
    private Clinic $clinica;

    private User $admin;

    private User $financeiro;

    /** @var array<string, int> */
    private array $status;

    public function run(): void
    {
        // Reexecutar a demonstração em um tenant já semeado duplicaria agenda,
        // despesas e consumos porque as datas são relativas a "hoje". O
        // marcador abaixo só existe depois que a Daiane foi semeada; nesse
        // caso o seeder não roda de novo. Para recriar do zero, apague o banco
        // do tenant e rode `tenants:migrate` + `tenants:seed --demo-daiane`.
        if (Payment::query()->where('observacoes', 'Importado da planilha: GISELE ALVES - sessão de laser')->exists()) {
            $this->command?->info('Demonstração da Daiane já existe neste tenant; nada foi duplicado.');

            return;
        }

        $this->call(DemoFisioterapiaSeeder::class);

        $this->clinica = $this->resolveClinic();
        $this->admin = $this->resolveAdmin();
        $this->financeiro = User::query()->where('email', 'financeiro.fisio@ljdemo.test')->first() ?? $this->admin;
        $this->status = AppointmentStatus::query()->pluck('id', 'name')->all();

        $this->ajustarCatalogo();
        $this->semearPacotesDaiane();
        $this->semearAgendaDaiane();
        $this->semearConsumosDaiane();
        $this->vincularPagamentosAosPacientes();
        $this->semearPagamentosDaiane();
        $this->semearContasPagar();
        $this->semearContaReceberEmAberto();
    }

    private function resolveClinic(): Clinic
    {
        $clinic = app(TenantManager::class)->current();

        if ($clinic === null) {
            throw new RuntimeException('DemoDaianeSeeder precisa de uma clínica resolvida.');
        }

        return $clinic;
    }

    private function resolveAdmin(): User
    {
        $admin = User::query()
            ->where('clinic_id', $this->clinica->id)
            ->orderBy('id')
            ->first();

        if ($admin === null) {
            throw new RuntimeException("Nenhum usuário encontrado para a clínica [{$this->clinica->name}].");
        }

        return $admin;
    }

    /**
     * A fisioterapia genérica já removeu o catálogo odontológico. Aqui entram
     * os serviços que a Daiane vende e que não existiam na demonstração de
     * fisioterapia.
     */
    private function ajustarCatalogo(): void
    {
        $procedimentos = [
            ['codigo' => 'PIL_IND', 'nome' => 'Pilates individual (sessão)', 'categoria' => 'Pilates', 'duracao_minutos' => 50, 'valor_padrao' => 120.00],
            ['codigo' => 'MASS_TER', 'nome' => 'Massagem terapêutica (sessão)', 'categoria' => 'Massagem', 'duracao_minutos' => 60, 'valor_padrao' => 160.00],
            ['codigo' => 'LASER_DEP', 'nome' => 'Depilação a laser (sessão)', 'categoria' => 'Laser', 'duracao_minutos' => 30, 'valor_padrao' => 180.00],
            ['codigo' => 'FISIO_DOM', 'nome' => 'Fisioterapia domiciliar', 'categoria' => 'Fisioterapia', 'duracao_minutos' => 60, 'valor_padrao' => 220.00],
        ];

        foreach ($procedimentos as $procedimento) {
            Procedure::query()->firstOrCreate(['codigo' => $procedimento['codigo']], $procedimento);
        }

        foreach (['Pilates', 'Massoterapia', 'Laserterapia'] as $especialidade) {
            Specialty::query()->firstOrCreate(['name' => $especialidade]);
        }
    }

    /**
     * Pacotes ativos para os serviços que a agenda do dia vai consumir.
     */
    private function semearPacotesDaiane(): void
    {
        $hoje = Carbon::today();

        $ana = Paciente::query()->where('cpf', '433.552.671-15')->firstOrFail();
        $fernanda = Paciente::query()->where('cpf', '578.125.903-08')->firstOrFail();
        $rafael = Paciente::query()->where('cpf', '876.341.512-30')->firstOrFail();

        $camila = Professional::query()->where('nome', 'Dra. Camila Fisioterapia')->firstOrFail();

        PacoteSessao::query()->firstOrCreate(
            ['paciente_id' => $ana->id, 'nome' => 'Pacote Pilates - 8 sessões'],
            [
                'profissional_id' => $camila->id,
                'servico' => 'Pilates individual (sessão)',
                'total_sessoes' => 8,
                'sessoes_usadas' => 1,
                'valor_sessao' => 120.00,
                'valor_total' => 960.00,
                'status' => PacoteSessao::ATIVO,
                'validade_em' => $hoje->copy()->addMonths(2),
            ]
        );

        PacoteSessao::query()->firstOrCreate(
            ['paciente_id' => $fernanda->id, 'nome' => 'Pacote Massagem - 5 sessões'],
            [
                'profissional_id' => $camila->id,
                'servico' => 'Massagem terapêutica (sessão)',
                'total_sessoes' => 5,
                'sessoes_usadas' => 2,
                'valor_sessao' => 160.00,
                'valor_total' => 800.00,
                'status' => PacoteSessao::ATIVO,
                'validade_em' => $hoje->copy()->addMonths(3),
            ]
        );

        PacoteSessao::query()->firstOrCreate(
            ['paciente_id' => $rafael->id, 'nome' => 'Pacote Laser - 6 sessões'],
            [
                'profissional_id' => $camila->id,
                'servico' => 'Depilação a laser (sessão)',
                'total_sessoes' => 6,
                'sessoes_usadas' => 1,
                'valor_sessao' => 180.00,
                'valor_total' => 1080.00,
                'status' => PacoteSessao::ATIVO,
                'validade_em' => $hoje->copy()->addMonths(4),
            ]
        );
    }

    /**
     * Agenda da semana com os serviços da Daiane, incluindo presença e falta
     * para alimentar os relatórios e a tela de atendimento do dia.
     */
    private function semearAgendaDaiane(): void
    {
        $hoje = Carbon::today();

        $ana = Paciente::query()->where('cpf', '433.552.671-15')->firstOrFail();
        $fernanda = Paciente::query()->where('cpf', '578.125.903-08')->firstOrFail();
        $rafael = Paciente::query()->where('cpf', '876.341.512-30')->firstOrFail();

        $camila = Professional::query()->where('nome', 'Dra. Camila Fisioterapia')->firstOrFail();
        $sala2 = Room::query()->where('nome', 'Sala 2')->firstOrFail();
        $sala3 = Room::query()->where('nome', 'Sala 3')->firstOrFail();

        $this->agendar($ana, $camila, $hoje->copy()->setTime(11, 30), 50, 'confirmado', 'Pilates individual', 'Pilates individual (sessão)', 'Pilates', 120.00, $sala2);
        $this->agendar($fernanda, $camila, $hoje->copy()->setTime(16, 0), 60, 'agendado', 'Massagem terapêutica', 'Massagem terapêutica (sessão)', 'Massagem', 160.00, $sala3);
        $this->agendar($rafael, $camila, $hoje->copy()->setTime(17, 0), 30, 'agendado', 'Depilação a laser', 'Depilação a laser (sessão)', 'Laser', 180.00, $sala3);

        $this->agendar($rafael, $camila, $hoje->copy()->subDay()->setTime(10, 0), 30, 'atendido', 'Depilação a laser', 'Depilação a laser (sessão)', 'Laser', 180.00, $sala2);
        $this->agendar($ana, $camila, $hoje->copy()->subDays(3)->setTime(11, 0), 50, 'faltou', 'Pilates individual', 'Pilates individual (sessão)', 'Pilates', 120.00, $sala2);
    }

    private function agendar(
        Paciente $paciente,
        Professional $profissional,
        Carbon $inicio,
        int $duracao,
        string $status,
        string $titulo,
        string $servico,
        string $area,
        float $valor,
        Room $sala,
    ): Appointment {
        return Appointment::query()->firstOrCreate(
            ['paciente_id' => $paciente->id, 'inicio' => $inicio],
            [
                'clinica_id' => $this->clinica->id,
                'profissional_id' => $profissional->id,
                'room_id' => $sala->id,
                'appointment_status_id' => $this->status[$status],
                'fim' => $inicio->copy()->addMinutes($duracao),
                'titulo' => $titulo,
                'servico' => $servico,
                'area' => $area,
                'valor' => $valor,
                'retorno' => false,
            ]
        );
    }

    /**
     * Sessões debitadas dos pacotes.
     *
     * O `DemoFisioterapiaSeeder` grava o saldo usado, mas o filtro por data
     * dele não casa com o datetime gravado em `evolucoes_sessao`. Aqui o
     * consumo é criado a partir do `appointment_id`, que é a chave idempotente
     * usada pelo fluxo real (`AtendimentoService`).
     */
    private function semearConsumosDaiane(): void
    {
        $hoje = Carbon::today();

        $camila = Professional::query()->where('nome', 'Dra. Camila Fisioterapia')->firstOrFail();
        $sala2 = Room::query()->where('nome', 'Sala 2')->firstOrFail();
        $sala3 = Room::query()->where('nome', 'Sala 3')->firstOrFail();

        $ana = Paciente::query()->where('cpf', '433.552.671-15')->firstOrFail();
        $fernanda = Paciente::query()->where('cpf', '578.125.903-08')->firstOrFail();
        $rafael = Paciente::query()->where('cpf', '876.341.512-30')->firstOrFail();

        $pilates = $this->agendar($ana, $camila, $hoje->copy()->subDays(7)->setTime(11, 30), 50, 'atendido', 'Pilates individual', 'Pilates individual (sessão)', 'Pilates', 120.00, $sala2);
        $massagem1 = $this->agendar($fernanda, $camila, $hoje->copy()->subDays(10)->setTime(16, 0), 60, 'atendido', 'Massagem terapêutica', 'Massagem terapêutica (sessão)', 'Massagem', 160.00, $sala3);
        $massagem2 = $this->agendar($fernanda, $camila, $hoje->copy()->subDays(8)->setTime(16, 0), 60, 'atendido', 'Massagem terapêutica', 'Massagem terapêutica (sessão)', 'Massagem', 160.00, $sala3);

        $laser = Appointment::query()
            ->where('paciente_id', $rafael->id)
            ->whereDate('inicio', $hoje->copy()->subDay()->toDateString())
            ->first();

        $this->consumir($pilates, 'Pilates individual (sessão)');
        $this->consumir($massagem1, 'Massagem terapêutica (sessão)');
        $this->consumir($massagem2, 'Massagem terapêutica (sessão)');

        if ($laser !== null) {
            $this->consumir($laser, 'Depilação a laser (sessão)');
        }

        // Pacotes de fisioterapia criados pelo seeder anterior: o consumo vem
        // da evolução clínica, que já aponta para o atendimento.
        EvolucaoSessao::query()
            ->whereNotNull('appointment_id')
            ->each(function (EvolucaoSessao $evolucao): void {
                $pacote = PacoteSessao::query()
                    ->where('paciente_id', $evolucao->paciente_id)
                    ->whereNull('servico')
                    ->orderBy('id')
                    ->first();

                if ($pacote === null) {
                    return;
                }

                ConsumoSessao::query()->firstOrCreate(
                    ['appointment_id' => $evolucao->appointment_id],
                    [
                        'pacote_id' => $pacote->id,
                        'paciente_id' => $evolucao->paciente_id,
                        'profissional_id' => $evolucao->profissional_id,
                        'evolucao_sessao_id' => $evolucao->id,
                        'data' => $evolucao->data?->toDateString(),
                        'valor' => $pacote->valor_sessao,
                        'observacoes' => 'Consumo da demonstração',
                    ]
                );
            });
    }

    private function consumir(Appointment $consulta, string $servico): void
    {
        $pacote = PacoteSessao::query()
            ->where('paciente_id', $consulta->paciente_id)
            ->where('servico', $servico)
            ->orderBy('id')
            ->first();

        if ($pacote === null) {
            return;
        }

        ConsumoSessao::query()->firstOrCreate(
            ['appointment_id' => $consulta->id],
            [
                'pacote_id' => $pacote->id,
                'paciente_id' => $consulta->paciente_id,
                'profissional_id' => $consulta->profissional_id,
                'data' => $consulta->inicio->toDateString(),
                'valor' => $consulta->valor,
                'observacoes' => 'Consumo da demonstração',
            ]
        );
    }

    /**
     * Os pagamentos da demonstração de fisioterapia foram criados sem
     * `paciente_id`. Vincular aqui é seguro porque a conta a receber já aponta
     * para o paciente; não é o merge por nome do importador real.
     */
    private function vincularPagamentosAosPacientes(): void
    {
        Payment::query()
            ->whereNull('paciente_id')
            ->whereNotNull('accounts_receivable_id')
            ->with('accountsReceivable')
            ->each(function (Payment $pagamento): void {
                $conta = $pagamento->accountsReceivable;

                if ($conta?->paciente_id === null) {
                    return;
                }

                $pagamento->forceFill([
                    'paciente_id' => $conta->paciente_id,
                    'servico' => $pagamento->servico ?: $conta->servico,
                ])->save();
            });
    }

    /**
     * Datas e lançamentos de recebimento da demonstração.
     *
     * O seeder de fisioterapia data os pagamentos em relação a "hoje menos
     * alguns dias" e, rodando no começo do mês, eles caem no mês anterior. Os
     * relatórios usam o mês corrente por padrão, então a demonstração ficaria
     * com receita zerada. Aqui as datas são trazidas para o mês corrente e é
     * criado um lançamento importado sem vínculo, que é justamente o caso que
     * a tela de revisão precisa mostrar.
     */
    private function semearPagamentosDaiane(): void
    {
        $hoje = Carbon::today();
        $inicioMes = $hoje->copy()->startOfMonth();

        $pix = PaymentMethod::query()->where('name', 'pix')->firstOrFail();

        // Os dois recebimentos originais da fisioterapia ganham data no mês
        // corrente; o lançamento importado abaixo fica sem vínculo de propósito.
        // A busca é pela observação (e não pela posição) para que reexecutar o
        // seeder não crie um pagamento novo só porque a data mudou.
        $entrada = Payment::query()->where('observacoes', 'Entrada do pacote de fisioterapia')->orderBy('id')->first();
        $integral = Payment::query()->where('observacoes', 'Pagamento integral do pacote')->orderBy('id')->first();

        $entrada?->forceFill(['data' => $inicioMes->copy()->setTime(10, 0)])->save();
        $integral?->forceFill(['data' => $hoje->copy()->setTime(15, 30)])->save();

        Payment::query()->firstOrCreate(
            ['observacoes' => 'Importado da planilha: GISELE ALVES - sessão de laser'],
            [
                'data' => $hoje->copy()->setTime(9, 0),
                'valor' => 180.00,
                'payment_method_id' => $pix->id,
                'servico' => 'Depilação a laser (sessão)',
                'origem' => 'importado',
            ]
        );
    }

    /**
     * Despesas recorrentes com uma conta atrasada, uma paga integral, uma paga
     * em parte e contas futuras. É o que a planilha CONTAS controlava.
     */
    private function semearContasPagar(): void
    {
        $hoje = Carbon::today();

        $pix = PaymentMethod::query()->where('name', 'pix')->firstOrFail();
        $dinheiro = PaymentMethod::query()->where('name', 'dinheiro')->firstOrFail();

        $imobiliaria = Supplier::query()->firstOrCreate(
            ['nome' => 'Imobiliária Centro'],
            [
                'documento' => '55.555.555/0001-55',
                'email' => 'financeiro@imobiliariacentro.test',
                'phone' => '(11) 3555-0000',
                'contato' => 'Setor de locação',
                'is_active' => true,
            ]
        );

        $energia = Supplier::query()->firstOrCreate(
            ['nome' => 'Energia Paulista'],
            [
                'documento' => '66.666.666/0001-66',
                'email' => 'atendimento@energiapaulista.test',
                'phone' => '(11) 3666-0000',
                'contato' => 'Central de atendimento',
                'is_active' => true,
            ]
        );

        $contabilidade = Supplier::query()->firstOrCreate(
            ['nome' => 'Contabilidade Bem Estar'],
            [
                'documento' => '77.777.777/0001-77',
                'email' => 'contato@contabilidadebemestar.test',
                'phone' => '(11) 3777-0000',
                'contato' => 'Escritório contábil',
                'is_active' => true,
            ]
        );

        $fisioMed = Supplier::query()->where('nome', 'FisioMed Distribuidora')->first();

        $aluguel = $this->categoria('Aluguel');
        $energiaCategoria = $this->categoria('Energia');
        $materiais = $this->categoria('Materiais e insumos');
        $folha = $this->categoria('Folha de pagamento');
        $emprestimo = $this->categoria('Empréstimo');
        $servicos = $this->categoria('Serviços');

        $this->contaPagar([
            'supplier_id' => $imobiliaria->id,
            'financeiro_categoria_id' => $aluguel->id,
            'nome_conta' => 'Aluguel da sala',
            'descricao' => 'Aluguel mensal do espaço da clínica',
            'valor' => 3200.00,
            'data_vencimento' => $hoje->copy()->subDays(10),
            'status' => AccountsPayable::PENDENTE,
            'tipo' => 'aluguel',
            'tipo_documento' => 'boleto',
            'observacoes' => 'Conta em atraso para testar o filtro de vencidas.',
        ]);

        $contaEnergia = $this->contaPagar([
            'supplier_id' => $energia->id,
            'financeiro_categoria_id' => $energiaCategoria->id,
            'nome_conta' => 'Energia elétrica',
            'descricao' => 'Fatura de energia do mês anterior',
            'valor' => 480.75,
            'data_vencimento' => $hoje->copy()->subDays(20),
            'data_pagamento' => $hoje->copy()->subDays(19),
            'status' => AccountsPayable::PAGO,
            'tipo' => 'energia',
            'tipo_documento' => 'nf',
            'observacoes' => null,
        ]);

        $contaMaterial = $this->contaPagar([
            'supplier_id' => $fisioMed?->id,
            'financeiro_categoria_id' => $materiais->id,
            'nome_conta' => 'Materiais de consumo',
            'descricao' => 'Gaze, gel condutor e bandagens',
            'valor' => 890.00,
            'data_vencimento' => $hoje->copy()->addDays(5),
            'status' => AccountsPayable::PENDENTE,
            'tipo' => 'fornecedor',
            'tipo_documento' => 'nf',
            'observacoes' => 'Pagamento parcial registrado na demonstração.',
        ]);

        $this->contaPagar([
            'financeiro_categoria_id' => $folha->id,
            'nome_conta' => 'Folha de pagamento',
            'descricao' => 'Salários e encargos da equipe',
            'valor' => 4200.00,
            'data_vencimento' => $hoje->copy()->addDays(10),
            'status' => AccountsPayable::PENDENTE,
            'tipo' => 'outros',
            'tipo_documento' => 'outros',
            'observacoes' => null,
        ]);

        $contaEmprestimo = $this->contaPagar([
            'financeiro_categoria_id' => $emprestimo->id,
            'nome_conta' => 'Parcela do empréstimo',
            'descricao' => 'Parcela mensal do empréstimo da clínica',
            'valor' => 1500.00,
            'data_vencimento' => $hoje->copy()->subDays(5),
            'data_pagamento' => $hoje->copy()->subDays(5),
            'status' => AccountsPayable::PAGO,
            'tipo' => 'emprestimo',
            'tipo_documento' => 'duplicata',
            'observacoes' => null,
        ]);

        $this->contaPagar([
            'supplier_id' => $contabilidade->id,
            'financeiro_categoria_id' => $servicos->id,
            'nome_conta' => 'Honorários contábeis',
            'descricao' => 'Assessoria contábil mensal',
            'valor' => 650.00,
            'data_vencimento' => $hoje->copy()->addDays(2),
            'status' => AccountsPayable::PENDENTE,
            'tipo' => 'servico',
            'tipo_documento' => 'nf',
            'observacoes' => null,
        ]);

        $this->baixa($contaEnergia, 480.75, $hoje->copy()->subDays(19), $pix, 'demo-energia');
        $this->baixa($contaMaterial, 400.00, $hoje->copy()->subDays(1), $dinheiro, 'demo-material-parte-1');
        $this->baixa($contaEmprestimo, 1500.00, $hoje->copy()->subDays(5), $pix, 'demo-emprestimo');

        // A conta de aluguel fica em aberto de propósito: é a que aparece como
        // atrasada na listagem e no resumo do financeiro.
    }

    private function contaPagar(array $dados): AccountsPayable
    {
        return AccountsPayable::query()->firstOrCreate(
            [
                'nome_conta' => $dados['nome_conta'],
                'data_vencimento' => $dados['data_vencimento'],
            ],
            $dados
        );
    }

    private function baixa(
        AccountsPayable $conta,
        float $valor,
        Carbon $data,
        PaymentMethod $forma,
        string $chave,
    ): void {
        AccountsPayablePayment::query()->firstOrCreate(
            ['idempotency_key' => $chave],
            [
                'accounts_payable_id' => $conta->id,
                'payment_method_id' => $forma->id,
                'user_id' => $this->financeiro->id,
                'data' => $data,
                'valor' => $valor,
                'observacoes' => 'Baixa da demonstração',
            ]
        );
    }

    private function categoria(string $nome): FinancialCategory
    {
        return FinancialCategory::query()
            ->where('nome', $nome)
            ->where('tipo', FinancialCategory::DESPESA)
            ->firstOrFail();
    }

    /**
     * Conta a receber em aberto para validar a baixa manual e o relatório de
     * recebimentos sem depender de um pagamento já existente.
     */
    private function semearContaReceberEmAberto(): void
    {
        $rafael = Paciente::query()->where('cpf', '876.341.512-30')->firstOrFail();

        AccountsReceivable::query()->firstOrCreate(
            [
                'paciente_id' => $rafael->id,
                'descricao' => 'Pacote de laser - 6 sessões',
            ],
            [
                'servico' => 'Depilação a laser (sessão)',
                'vencimento' => Carbon::today()->addDays(7),
                'valor' => 1080.00,
                'valor_pago' => 0,
                'desconto' => 0,
                'status' => 'aberta',
                'observacoes' => 'Conta em aberto criada para demonstração.',
            ]
        );
    }
}
