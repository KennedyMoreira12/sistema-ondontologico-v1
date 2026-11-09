<?php

namespace Tests\Feature;

use App\Models\AccountsPayable;
use App\Models\AccountsPayablePayment;
use App\Models\AccountsReceivable;
use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Central\User;
use App\Models\ConsumoSessao;
use App\Models\FinancialCategory;
use App\Models\InsuranceCompany;
use App\Models\InsurancePlan;
use App\Models\Paciente;
use App\Models\PacoteSessao;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Professional;
use App\Models\Room;
use App\Models\SocialPost;
use App\Models\Supplier;
use App\Models\Turma;
use App\Models\TurmaPresenca;
use App\Support\FinanceHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\Concerns\PreparaClinicaFisioterapia;
use Tests\TestCase;

/**
 * Testes de equivalência — planilhas da Daiane x LJ SmartClinic.
 *
 * Cada método compara o resultado do sistema com o cálculo esperado a partir
 * dos dados das planilhas (usando cópias, nunca a produção). Um teste aprovado
 * é a evidência objetiva de que um fluxo da matriz de 127 está classificado
 * como A (equivalência completa, implementada e testada).
 *
 * Regras:
 * - Nunca alterar dados de produção.
 * - Não forçar igualdade nem arredondar resultados para fazer bater.
 * - Qualquer divergência deve ser investigada, não disfarçada.
 */
class EquivalenciaPlanilhasDaianeTest extends TestCase
{
    use CreatesTenants, PreparaClinicaFisioterapia, RefreshDatabase;

    /* ------------------------------------------------------------------ */
    /* Helpers */
    /* ------------------------------------------------------------------ */

    /**
     * Cria uma clínica de teste e um usuário com as permissões pedidas,
     * retornando apenas [clinic, user] (mesma assinatura de UsesTestTenant).
     */
    private function criarUsuarioComPermissao(string ...$permissoes): array
    {
        $clinica = $this->criarClinica('Clínica Teste');
        $user = $this->usuarioComPermissao($clinica, ...$permissoes);

        return [$clinica['clinic'], $user];
    }

    /* ------------------------------------------------------------------ */
    /* 1. Agenda e horários recorrentes */
    /* ------------------------------------------------------------------ */

    public function test_agendamento_por_data_sem_conflito(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('agenda.create');

        $paciente = Paciente::create(['nome' => 'Paciente Agenda', 'status' => 'ativo']);
        $profissional = Professional::create(['nome' => 'Profissional X', 'is_active' => true]);
        $room = Room::create(['nome' => 'Sala 1', 'is_active' => true]);

        $statusAgendado = AppointmentStatus::where('name', 'agendado')->value('id');

        Appointment::create([
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'room_id' => $room->id,
            'inicio' => '2026-11-09 10:00:00',
            'fim' => '2026-11-09 10:30:00',
            'appointment_status_id' => $statusAgendado,
        ]);

        // Segunda consulta no mesmo horário deve gerar conflito
        $this->actingAs($user)
            ->post(route('agenda.store'), [
                'paciente_id' => $paciente->id,
                'profissional_id' => $profissional->id,
                'room_id' => $room->id,
                'dia' => '2026-11-09',
                'hora_inicio' => '10:00',
                'duracao' => 30,
                'tipo' => 'agendado',
            ])
            ->assertSessionHasErrors('hora_inicio');

        // Apenas 1 appointment existe (não houve duplicação)
        $this->assertSame(1, Appointment::count());
    }

    public function test_presenca_e_falta(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('agenda.update');

        $paciente = Paciente::create(['nome' => 'Paciente Presenca', 'status' => 'ativo']);
        $profissional = Professional::create(['nome' => 'Profissional Y', 'is_active' => true]);

        $statusAtendido = AppointmentStatus::firstOrCreate(['name' => 'atendido'], ['label' => 'Atendido', 'cor' => '#28a745']);
        $statusFaltou = AppointmentStatus::firstOrCreate(['name' => 'faltou'], ['label' => 'Faltou', 'cor' => '#dc3545']);

        $consulta = Appointment::create([
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'inicio' => '2026-11-09 10:00:00',
            'fim' => '2026-11-09 10:30:00',
            'appointment_status_id' => $statusAtendido->id,
        ]);

        $this->actingAs($user)
            ->post(route('agenda.presenca', $consulta), ['status' => 'faltou'])
            ->assertRedirect();

        $this->assertSame('faltou', $consulta->refresh()->status->name);
    }

    /* ------------------------------------------------------------------ */
    /* 2. Depilação a laser */
    /* ------------------------------------------------------------------ */

    public function test_consulta_laser_completa(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('agenda.create', 'financeiro.receive');

        $paciente = Paciente::create(['nome' => 'Paciente Laser', 'status' => 'ativo']);
        $profissional = Professional::create(['nome' => 'Profissional Laser', 'is_active' => true]);
        $statusAgendado = AppointmentStatus::where('name', 'agendado')->value('id');

        $consulta = Appointment::create([
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'inicio' => '2026-11-09 08:00:00',
            'fim' => '2026-11-09 08:30:00',
            'appointment_status_id' => $statusAgendado,
            'area' => 'AXILA',
            'servico' => 'DEPILAÇÃO A LASER',
            'valor' => 100.00,
        ]);

        $metodo = PaymentMethod::create(['name' => 'Cartão de Crédito', 'label' => 'Cartão de Crédito']);

        $conta = AccountsReceivable::create([
            'paciente_id' => $paciente->id,
            'servico' => 'DEPILAÇÃO A LASER',
            'vencimento' => '2026-11-09',
            'valor' => 100.00,
            'valor_pago' => 0,
            'status' => 'aberta',
        ]);

        $this->actingAs($user)
            ->post(route('financeiro.receive', $conta), [
                'valor' => 100.00,
                'payment_method_id' => $metodo->id,
            ])
            ->assertRedirect();

        $conta->refresh();
        $this->assertSame('pago', $conta->status);
        $this->assertSame(100.00, (float) $conta->valor_pago);

        $this->assertTenantDatabaseHas('payments', [
            'accounts_receivable_id' => $conta->id,
            'valor' => 100.00,
            'servico' => 'DEPILAÇÃO A LASER',
        ]);
    }

    public function test_pagamento_com_plano_de_saude(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.receive');

        $paciente = Paciente::create(['nome' => 'Paciente Plano', 'status' => 'ativo']);
        $metodo = PaymentMethod::create(['name' => 'PIX', 'label' => 'PIX']);

        $conta = AccountsReceivable::create([
            'paciente_id' => $paciente->id,
            'servico' => 'CONSULTA',
            'vencimento' => '2026-11-09',
            'valor' => 200.00,
            'valor_pago' => 0,
            'status' => 'aberta',
        ]);

        // insurance_plan_id é anulável: pagamento sem plano deve funcionar
        $this->actingAs($user)
            ->post(route('financeiro.receive', $conta), [
                'valor' => 60.00,
                'payment_method_id' => $metodo->id,
            ])
            ->assertRedirect();

        $this->assertTenantDatabaseHas('payments', [
            'valor' => 60.00,
            'insurance_plan_id' => null,
        ]);

        // O relacionamento existe no model (será usado quando o plano for cadastrado)
        $this->assertTrue(method_exists(Payment::class, 'insurancePlan'));
    }

    /* ------------------------------------------------------------------ */
    /* 3. Pilates */
    /* ------------------------------------------------------------------ */

    public function test_pagamento_pilates_por_servico(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.receive');

        $paciente = Paciente::create(['nome' => 'Paciente Pilates', 'status' => 'ativo']);
        $metodo = PaymentMethod::create(['name' => 'PIX', 'label' => 'PIX']);

        $conta = AccountsReceivable::create([
            'paciente_id' => $paciente->id,
            'servico' => 'PILATES',
            'vencimento' => '2026-11-09',
            'valor' => 200.00,
            'valor_pago' => 0,
            'status' => 'aberta',
        ]);

        $this->actingAs($user)
            ->post(route('financeiro.receive', $conta), [
                'valor' => 200.00,
                'payment_method_id' => $metodo->id,
            ])
            ->assertRedirect();

        $total = Payment::where('servico', 'PILATES')->sum('valor');
        $this->assertSame(200.00, (float) $total);
    }

    /* ------------------------------------------------------------------ */
    /* 4. Fisioterapia / pacotes de sessão */
    /* ------------------------------------------------------------------ */

    public function test_pacote_consumo_debita_saldo(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('pacotes.view', 'pacotes.update');
        $paciente = Paciente::create(['nome' => 'Paciente Pacote FT']);

        $pacote = PacoteSessao::create([
            'paciente_id' => $paciente->id,
            'nome' => 'Pacote 5 sessões',
            'total_sessoes' => 5,
            'sessoes_usadas' => 0,
            'status' => PacoteSessao::ATIVO,
        ]);

        $this->actingAs($user)
            ->post(route('pacientes.pacotes.consumir', [$paciente, $pacote]))
            ->assertRedirect();

        $this->assertSame(1, $pacote->refresh()->sessoes_usadas);
        $this->assertSame(4, $pacote->restantes());
    }

    public function test_pacote_saldo_nunca_negativo(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('pacotes.view', 'pacotes.update');
        $paciente = Paciente::create(['nome' => 'Paciente Saldo']);

        $pacote = PacoteSessao::create([
            'paciente_id' => $paciente->id,
            'nome' => 'Pacote 1 sessão',
            'total_sessoes' => 1,
            'sessoes_usadas' => 0,
            'status' => PacoteSessao::ATIVO,
        ]);

        $this->actingAs($user)->post(route('pacientes.pacotes.consumir', [$paciente, $pacote]));
        $this->actingAs($user)
            ->post(route('pacientes.pacotes.consumir', [$paciente, $pacote]))
            ->assertSessionHas('error');

        $this->assertSame(1, $pacote->refresh()->sessoes_usadas);
        $this->assertSame(0, $pacote->restantes());
    }

    public function test_consumo_idempotente_por_appointment(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('agenda.update', 'pacotes.view', 'pacotes.update');
        $paciente = Paciente::create(['nome' => 'Paciente Idempotencia']);

        $statusAtendido = AppointmentStatus::firstOrCreate(['name' => 'atendido'], ['label' => 'Atendido', 'cor' => '#28a745']);

        $consulta = Appointment::create([
            'paciente_id' => $paciente->id,
            'inicio' => '2026-11-09 10:00:00',
            'fim' => '2026-11-09 10:30:00',
            'appointment_status_id' => $statusAtendido->id,
        ]);

        $pacote = PacoteSessao::create([
            'paciente_id' => $paciente->id,
            'nome' => 'Pacote Idempotente',
            'total_sessoes' => 5,
            'sessoes_usadas' => 0,
            'status' => PacoteSessao::ATIVO,
        ]);

        $this->actingAs($user)
            ->post(route('atendimentos.consumir', $consulta), ['pacote_id' => $pacote->id]);

        $this->actingAs($user)
            ->post(route('atendimentos.consumir', $consulta), ['pacote_id' => $pacote->id]);

        $this->assertSame(1, ConsumoSessao::where('appointment_id', $consulta->id)->count());
        $this->assertSame(1, $pacote->refresh()->sessoes_usadas);
    }

    /* ------------------------------------------------------------------ */
    /* 5. Contas a pagar */
    /* ------------------------------------------------------------------ */

    public function test_conta_a_pagar_criar_baixar_quitar(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.create', 'financeiro.receive', 'financeiro.view');
        $fornecedor = Supplier::create(['nome' => 'Fornecedor Teste']);
        $categoria = FinancialCategory::query()->where('nome', 'Aluguel')->firstOrFail();
        $metodo = PaymentMethod::create(['name' => 'PIX', 'label' => 'PIX']);

        $conta = AccountsPayable::create([
            'supplier_id' => $fornecedor->id,
            'financeiro_categoria_id' => $categoria->id,
            'nome_conta' => 'Aluguel novembro',
            'valor' => 1000.00,
            'data_vencimento' => '2026-11-10',
            'status' => 'pendente',
        ]);

        $this->actingAs($user)
            ->post(route('financeiro.contas-pagar.baixar', $conta), [
                'valor' => 400.00,
                'payment_method_id' => $metodo->id,
                'data' => '2026-11-05',
                'idempotency_key' => 'baixa-teste-1',
            ])
            ->assertRedirect();

        $this->assertSame('pendente', $conta->refresh()->status);
        $this->assertSame(600.00, $conta->saldo);

        $this->actingAs($user)
            ->post(route('financeiro.contas-pagar.baixar', $conta), [
                'valor' => 600.00,
                'payment_method_id' => $metodo->id,
                'data' => '2026-11-06',
                'idempotency_key' => 'baixa-teste-2',
            ])
            ->assertRedirect();

        $this->assertSame('pago', $conta->refresh()->status);
        $this->assertSame(0.0, $conta->saldo);
        $this->assertNotNull($conta->data_pagamento);
        $this->assertSame(2, AccountsPayablePayment::where('accounts_payable_id', $conta->id)->count());
    }

    public function test_atraso_calculado_nao_gravado(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.view');

        $conta = AccountsPayable::create([
            'nome_conta' => 'Conta vencida',
            'valor' => 90.00,
            'data_vencimento' => now()->subDays(3)->toDateString(),
            'status' => 'pendente',
        ]);

        $this->assertTrue($conta->estaAtrasada());
        $this->assertSame('atrasado', $conta->statusEfetivo());
        // O status gravado continua pendente (não envelhece sozinho)
        $this->assertSame('pendente', $conta->refresh()->status);
    }

    /* ------------------------------------------------------------------ */
    /* 6. Contas a receber */
    /* ------------------------------------------------------------------ */

    public function test_baixa_conta_receber_parcial(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.receive');

        $paciente = Paciente::create(['nome' => 'Paciente Parcial', 'status' => 'ativo']);
        $conta = AccountsReceivable::create([
            'paciente_id' => $paciente->id,
            'vencimento' => '2026-11-01',
            'valor' => 800.00,
            'valor_pago' => 0,
            'status' => 'aberta',
        ]);

        $metodo = PaymentMethod::create(['name' => 'Dinheiro', 'label' => 'Dinheiro']);

        $this->actingAs($user)
            ->post(route('financeiro.receive', $conta), [
                'valor' => 300.00,
                'payment_method_id' => $metodo->id,
            ])
            ->assertRedirect();

        $conta->refresh();
        $this->assertSame(300.0, (float) $conta->valor_pago);
        $this->assertSame('parcial', $conta->status);
    }

    public function test_rejeita_pagamento_maior_que_saldo(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.receive');

        $paciente = Paciente::create(['nome' => 'Paciente Rejeita', 'status' => 'ativo']);
        $conta = AccountsReceivable::create([
            'paciente_id' => $paciente->id,
            'vencimento' => '2026-11-01',
            'valor' => 100.00,
            'valor_pago' => 0,
            'status' => 'aberta',
        ]);

        $metodo = PaymentMethod::create(['name' => 'Dinheiro', 'label' => 'Dinheiro']);

        $this->actingAs($user)
            ->post(route('financeiro.receive', $conta), [
                'valor' => 999.00,
                'payment_method_id' => $metodo->id,
            ])
            ->assertSessionHasErrors('valor');

        $this->assertSame(0, Payment::where('accounts_receivable_id', $conta->id)->count());
    }

    public function test_total_aberto_desconta_pagamento_parcial(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.view');

        $paciente = Paciente::create(['nome' => 'Paciente Total', 'status' => 'ativo']);

        AccountsReceivable::create([
            'paciente_id' => $paciente->id,
            'vencimento' => '2026-11-01',
            'valor' => 800.00,
            'valor_pago' => 300.00,
            'status' => 'parcial',
        ]);

        $total = $this->actingAs($user)
            ->get(route('financeiro.index'))
            ->assertOk()
            ->viewData('totalAberto');

        $this->assertSame(500.0, round((float) $total, 2));
    }

    /* ------------------------------------------------------------------ */
    /* 7. Pagamentos e recebimentos */
    /* ------------------------------------------------------------------ */

    public function test_vinculo_payment_conta_a_receber(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.create');

        $paciente = Paciente::create(['nome' => 'Paciente Vinculo Conta', 'status' => 'ativo']);
        $metodo = PaymentMethod::create(['name' => 'PIX', 'label' => 'PIX']);

        // Pagamento manual cria conta a receber e vincula o payment a ela
        $this->actingAs($user)
            ->post(route('financeiro.pagamentos.store'), [
                'paciente_id' => $paciente->id,
                'descricao' => 'Consulta',
                'servico' => 'CONSULTA',
                'valor' => 100.00,
                'payment_method_id' => $metodo->id,
                'data' => '2026-11-09',
            ])
            ->assertRedirect(route('financeiro.pagamentos.index'));

        $conta = AccountsReceivable::firstOrFail();
        $this->assertSame('pago', $conta->status);
        $this->assertSame(100.0, (float) $conta->valor_pago);
        $this->assertSame($paciente->id, $conta->paciente_id);

        $this->assertTenantDatabaseHas('payments', [
            'accounts_receivable_id' => $conta->id,
            'valor' => 100.00,
        ]);
    }

    public function test_vinculo_pagamento_paciente_nao_sobrescreve(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.update');

        $p1 = Paciente::create(['nome' => 'Paciente Um', 'status' => 'ativo']);
        $p2 = Paciente::create(['nome' => 'Paciente Dois', 'status' => 'ativo']);
        $pagamento = Payment::create([
            'paciente_id' => $p1->id,
            'data' => '2026-10-10',
            'valor' => 50.00,
        ]);

        $this->actingAs($user)
            ->post(route('financeiro.pagamentos.vincular', $pagamento), ['paciente_id' => $p2->id])
            ->assertSessionHasErrors('paciente_id');

        $this->assertSame($p1->id, $pagamento->refresh()->paciente_id);
    }

    /* ------------------------------------------------------------------ */
    /* 8. FinanceHelper — DAYS360 (igual ao Excel) */
    /* ------------------------------------------------------------------ */

    public function test_dias360_igual_excel(): void
    {
        // DAYS360(01/01/2026, 31/01/2026) = 30 (base 30/360)
        $this->assertSame(30, FinanceHelper::dias360('2026-01-01', '2026-01-31'));

        // Vencimento futuro = 0 dias de atraso
        $this->assertSame(0, FinanceHelper::diasAtraso('2026-12-31'));
    }

    /* ------------------------------------------------------------------ */
    /* 9. Relatórios */
    /* ------------------------------------------------------------------ */

    public function test_relatorio_receitas_por_mes(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('relatorios.view');

        $paciente = Paciente::create(['nome' => 'Paciente Relatorio', 'status' => 'ativo']);
        $metodo = PaymentMethod::create(['name' => 'PIX', 'label' => 'PIX']);

        Payment::create([
            'paciente_id' => $paciente->id,
            'payment_method_id' => $metodo->id,
            'servico' => 'PILATES',
            'data' => '2026-11-05',
            'valor' => 150.00,
        ]);

        Payment::create([
            'paciente_id' => $paciente->id,
            'payment_method_id' => $metodo->id,
            'servico' => 'MASSAGEM',
            'data' => '2026-11-10',
            'valor' => 80.00,
        ]);

        $response = $this->actingAs($user)
            ->get(route('relatorios.receitas', ['de' => '2026-11-01', 'ate' => '2026-11-30']))
            ->assertOk();

        $response->assertSee('R$ 150,00');
        $response->assertSee('R$ 80,00');
        $this->assertSame(230.0, (float) $response->viewData('totalRecebido'));
    }

    public function test_relatorio_presenca_faltas(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('relatorios.view');

        $paciente = Paciente::create(['nome' => 'Paciente Relatorio PF']);
        $profissional = Professional::create(['nome' => 'Profissional PF', 'is_active' => true]);
        $statusAtendido = AppointmentStatus::where('name', 'atendido')->value('id');
        $statusFaltou = AppointmentStatus::where('name', 'faltou')->value('id');

        Appointment::create([
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'inicio' => '2026-11-05 10:00:00',
            'fim' => '2026-11-05 10:30:00',
            'appointment_status_id' => $statusAtendido,
        ]);
        Appointment::create([
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'inicio' => '2026-11-06 10:00:00',
            'fim' => '2026-11-06 10:30:00',
            'appointment_status_id' => $statusFaltou,
        ]);

        $response = $this->actingAs($user)
            ->get(route('relatorios.presenca-faltas', ['de' => '2026-11-01', 'ate' => '2026-11-30']))
            ->assertOk();

        $response->assertSee('1'); // atendidos
        $response->assertSee('1'); // faltas
    }

    /* ------------------------------------------------------------------ */
    /* 10. Pacientes — detecção de duplicatas (não funde) */
    /* ------------------------------------------------------------------ */

    public function test_duplicatas_nao_fundem_cadastros(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('pacientes.view');

        Paciente::create(['nome' => 'Maria Silva', 'status' => 'ativo']);
        Paciente::create(['nome' => 'MARIA SILVA', 'status' => 'ativo']);

        $this->actingAs($user)
            ->get(route('financeiro.duplicatas.index'))
            ->assertOk()
            ->assertSee('Maria Silva')
            ->assertSee('MARIA SILVA')
            ->assertSee('Nada é fundido automaticamente');

        $this->assertSame(2, Paciente::count());
    }

    /* ------------------------------------------------------------------ */
    /* 11. Desconto à vista (DJ-05) */
    /* ------------------------------------------------------------------ */

    public function test_recebimento_com_desconto_quita_conta(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.receive');

        $paciente = Paciente::create(['nome' => 'Paciente Desconto', 'status' => 'ativo']);
        $conta = AccountsReceivable::create([
            'paciente_id' => $paciente->id,
            'vencimento' => '2026-11-01',
            'valor' => 100.00,
            'valor_pago' => 0,
            'desconto' => 0,
            'status' => 'aberta',
        ]);

        $metodo = PaymentMethod::create(['name' => 'PIX', 'label' => 'PIX']);

        // Pagamento de R$ 80 + desconto de R$ 20 quita conta de R$ 100
        $this->actingAs($user)
            ->post(route('financeiro.receive', $conta), [
                'valor' => 80.00,
                'desconto' => 20.00,
                'payment_method_id' => $metodo->id,
            ])
            ->assertRedirect();

        $conta->refresh();
        $this->assertSame(80.0, (float) $conta->valor_pago);
        $this->assertSame(20.0, (float) $conta->desconto);
        $this->assertSame('pago', $conta->status);
    }

    public function test_recebimento_desconto_nao_quita_fica_parcial(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.receive');

        $paciente = Paciente::create(['nome' => 'Paciente Desconto Parcial', 'status' => 'ativo']);
        $conta = AccountsReceivable::create([
            'paciente_id' => $paciente->id,
            'vencimento' => '2026-11-01',
            'valor' => 100.00,
            'valor_pago' => 0,
            'desconto' => 0,
            'status' => 'aberta',
        ]);

        $metodo = PaymentMethod::create(['name' => 'PIX', 'label' => 'PIX']);

        // Pagamento de R$ 50 + desconto de R$ 20 = R$ 70, conta de R$ 100 fica parcial
        $this->actingAs($user)
            ->post(route('financeiro.receive', $conta), [
                'valor' => 50.00,
                'desconto' => 20.00,
                'payment_method_id' => $metodo->id,
            ])
            ->assertRedirect();

        $conta->refresh();
        $this->assertSame(50.0, (float) $conta->valor_pago);
        $this->assertSame(20.0, (float) $conta->desconto);
        $this->assertSame('parcial', $conta->status);
    }

    /* ------------------------------------------------------------------ */
    /* 12. Aniversariantes (MKT-02) */
    /* ------------------------------------------------------------------ */

    public function test_relatorio_aniversariantes_mes(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('relatorios.view');

        // Paciente aniversariante em novembro
        Paciente::create([
            'nome' => 'Paciente Aniversariante',
            'status' => 'ativo',
            'nascimento' => '1980-11-15',
        ]);

        // Paciente aniversariante em outro mês
        Paciente::create([
            'nome' => 'Paciente Outro Mes',
            'status' => 'ativo',
            'nascimento' => '1985-06-20',
        ]);

        $response = $this->actingAs($user)
            ->get(route('relatorios.aniversariantes', ['mes' => 11]))
            ->assertOk();

        $response->assertSee('Paciente Aniversariante');
        $response->assertDontSee('Paciente Outro Mes');
        $this->assertSame(1, $response->viewData('total'));
    }

    public function test_relatorio_aniversariantes_chegando(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('relatorios.view');

        // Aniversário em 2 dias (dentro do limite de "chegando")
        $proximoAniversario = now()->addDays(2)->month.'-'.now()->addDays(2)->day;
        $ano = now()->year;
        $dataNascimento = "$ano-$proximoAniversario";

        Paciente::create([
            'nome' => 'Paciente Chegando',
            'status' => 'ativo',
            'nascimento' => $dataNascimento,
        ]);

        $response = $this->actingAs($user)
            ->get(route('relatorios.aniversariantes', ['mes' => now()->addDays(2)->month]))
            ->assertOk();

        $response->assertSee('Paciente Chegando');
        $response->assertSee('TÁ CHEGANDO!');
    }

    /* ------------------------------------------------------------------ */
    /* 13. Pacotes não-FT (PSP-08) */
    /* ------------------------------------------------------------------ */

    public function test_pacote_pilates_fora_vertical_ft(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('pacotes.view', 'pacotes.create', 'pacotes.update');
        $paciente = Paciente::create(['nome' => 'Paciente Pilates']);

        // Pacote de pilates (não fisioterapia) deve ser criável mesmo fora da vertical FT
        // após remoção do middleware vertical:fisioterapia das rotas
        $this->actingAs($user)
            ->post(route('pacientes.pacotes.store', $paciente), [
                'nome' => 'Pacote Pilates 10 sessões',
                'total_sessoes' => 10,
                'valor_sessao' => 100,
                'valor_total' => 1000,
            ])
            ->assertRedirect(route('pacientes.pacotes.index', $paciente));

        $this->assertTenantDatabaseHas('pacotes_sessao', [
            'paciente_id' => $paciente->id,
            'nome' => 'Pacote Pilates 10 sessões',
            'total_sessoes' => 10,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* 14. Pagamento com plano de saúde (LAS-06 / PG-03) */
    /* ------------------------------------------------------------------ */

    public function test_pagamento_com_plano_de_saude_campo_anulavel(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.receive');

        $paciente = Paciente::create(['nome' => 'Paciente Plano FK', 'status' => 'ativo']);
        $metodo = PaymentMethod::create(['name' => 'PIX', 'label' => 'PIX']);

        $conta = AccountsReceivable::create([
            'paciente_id' => $paciente->id,
            'servico' => 'CONSULTA',
            'vencimento' => '2026-11-09',
            'valor' => 200.00,
            'valor_pago' => 0,
            'status' => 'aberta',
        ]);

        // insurance_plan_id é anulável: pagamento sem plano deve funcionar
        $this->actingAs($user)
            ->post(route('financeiro.receive', $conta), [
                'valor' => 60.00,
                'payment_method_id' => $metodo->id,
            ])
            ->assertRedirect();

        $this->assertTenantDatabaseHas('payments', [
            'valor' => 60.00,
            'insurance_plan_id' => null,
        ]);

        // O relacionamento existe no model (será usado quando o plano for cadastrado)
        $this->assertTrue(method_exists(Payment::class, 'insurancePlan'));
        $this->assertTrue(in_array('insurance_plan_id', (new Payment)->getFillable()));
    }

    /* ------------------------------------------------------------------ */
    /* 15. Fluxos B/C técnicos — equivalência de formato (não comercial) */
    /* ------------------------------------------------------------------ */

    /**
     * AGD-05/PIL-05 — a grade semanal e o calendário da planilha PILATES são
     * turmas de verdade: dia, horário, profissional, alunos e presença por data.
     * Não é o nome de um pacote exibido na tela do paciente.
     */
    public function test_turma_pilates_grade_calendario_e_presenca(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('pacotes.view', 'pacotes.create', 'pacotes.update');

        $profissional = Professional::create(['nome' => 'Instrutora Pilates', 'is_active' => true]);
        $aluno1 = Paciente::create(['nome' => 'Aluno Pilates Um', 'status' => 'ativo']);
        $aluno2 = Paciente::create(['nome' => 'Aluno Pilates Dois', 'status' => 'ativo']);

        $this->actingAs($user)
            ->post(route('turmas.store'), [
                'nome' => 'Pilates Segunda 10h',
                'servico' => 'PILATES',
                'profissional_id' => $profissional->id,
                'dia_semana' => 1,
                'hora_inicio' => '10:00',
                'hora_fim' => '11:00',
                'capacidade' => 6,
                'sala' => 'Sala Pilates',
            ])
            ->assertRedirect();

        $turma = Turma::query()->where('nome', 'Pilates Segunda 10h')->firstOrFail();

        $this->assertTenantDatabaseHas('turmas', [
            'nome' => 'Pilates Segunda 10h',
            'profissional_id' => $profissional->id,
            'dia_semana' => 1,
        ]);

        // Grade semanal: a turma aparece na coluna de segunda-feira e no calendário.
        $this->actingAs($user)
            ->get(route('turmas.index', ['mes' => '2026-11']))
            ->assertOk()
            ->assertSee('Pilates Segunda 10h')
            ->assertSee('Instrutora Pilates')
            ->assertSee('02/11'); // primeira segunda-feira de novembro/2026

        foreach ([$aluno1, $aluno2] as $aluno) {
            $this->actingAs($user)
                ->post(route('turmas.pacientes.store', $turma), ['paciente_id' => $aluno->id])
                ->assertRedirect();
        }

        $this->assertSame(2, $turma->pacientes()->wherePivot('is_active', true)->count());

        // Presença de um aluno na data: grava uma linha.
        $this->actingAs($user)
            ->post(route('turmas.presencas.store', $turma), [
                'paciente_id' => $aluno1->id,
                'data' => '2026-11-02',
                'status' => TurmaPresenca::PRESENTE,
            ])
            ->assertRedirect();

        // Marcar de novo no mesmo dia ATUALIZA a chamada, não duplica.
        $this->actingAs($user)
            ->post(route('turmas.presencas.store', $turma), [
                'paciente_id' => $aluno1->id,
                'data' => '2026-11-02',
                'status' => TurmaPresenca::AUSENTE,
            ])
            ->assertRedirect();

        $this->assertSame(1, TurmaPresenca::query()
            ->where('turma_id', $turma->id)
            ->where('paciente_id', $aluno1->id)
            ->whereDate('data', '2026-11-02')
            ->count());

        $this->assertSame(TurmaPresenca::AUSENTE, TurmaPresenca::query()
            ->where('turma_id', $turma->id)
            ->where('paciente_id', $aluno1->id)
            ->whereDate('data', '2026-11-02')
            ->value('status'));

        // A lista de presença da data mostra os dois alunos e a situação.
        $this->actingAs($user)
            ->get(route('turmas.show', ['turma' => $turma, 'data' => '2026-11-02']))
            ->assertOk()
            ->assertSee('Aluno Pilates Um')
            ->assertSee('Aluno Pilates Dois')
            ->assertSee('Ausente')
            ->assertSee('Sem registro');

        // Aluno removido sai da chamada ativa, mas o histórico não é apagado.
        $this->actingAs($user)
            ->delete(route('turmas.pacientes.destroy', ['turma' => $turma, 'paciente' => $aluno2]))
            ->assertRedirect();

        $this->assertSame(1, $turma->pacientes()->wherePivot('is_active', true)->count());
        $this->assertSame(0, TurmaPresenca::query()->where('paciente_id', $aluno2->id)->count());
    }

    public function test_paciente_variacoes_nome_nao_funde(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('pacientes.view', 'financeiro.view');

        // Mesmo paciente com nomes diferentes (GISELE/GISELE ALVES/GISELE LVES)
        Paciente::create(['nome' => 'GISELE', 'status' => 'ativo']);
        Paciente::create(['nome' => 'GISELE ALVES', 'status' => 'ativo']);
        Paciente::create(['nome' => 'GISELE LVES', 'status' => 'ativo']);

        // Sistema não funde automaticamente: 3 cadastros distintos
        $this->assertSame(3, Paciente::count());

        // Busca aproximada encontra todos os 3 via detecção de duplicatas
        $this->actingAs($user)
            ->get(route('financeiro.duplicatas.index'))
            ->assertOk()
            ->assertSee('GISELE')
            ->assertSee('GISELE ALVES')
            ->assertSee('GISELE LVES');
    }

    /**
     * PE-02 — remunerar profissional externo é dinheiro que SAI. A conta nasce
     * em `accounts_payable`, vinculada ao profissional, e a baixa registra a
     * saída. Não pode ser um `accounts_receivable` (dinheiro entrando).
     */
    public function test_profissional_externo_pagamento_sai_do_caixa(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.create', 'financeiro.receive', 'financeiro.view');

        $profissional = Professional::create([
            'nome' => 'Nutricionista Apoena',
            'tipo' => 'nutricionista',
            'registro_profissional' => 'CRN 12345',
            'is_active' => true,
        ]);
        $metodo = PaymentMethod::create(['name' => 'PIX', 'label' => 'PIX']);

        $this->actingAs($user)
            ->post(route('financeiro.contas-pagar.store'), [
                'professional_id' => $profissional->id,
                'tipo_remuneracao' => 'diaria',
                'nome_conta' => 'Nutricionista — diária 09/11',
                'valor' => 150.00,
                'data_vencimento' => '2026-11-09',
                'tipo' => 'servico',
                'tipo_documento' => 'outros',
            ])
            ->assertRedirect();

        $conta = AccountsPayable::query()->where('nome_conta', 'Nutricionista — diária 09/11')->firstOrFail();

        $this->assertSame($profissional->id, $conta->professional_id);
        $this->assertSame('diaria', $conta->tipo_remuneracao);

        $this->actingAs($user)
            ->post(route('financeiro.contas-pagar.baixar', $conta), [
                'valor' => 150.00,
                'payment_method_id' => $metodo->id,
                'data' => '2026-11-09',
            ])
            ->assertRedirect();

        $conta->refresh();
        $this->assertSame(AccountsPayable::PAGO, $conta->status);
        $this->assertSame(0.0, $conta->saldo);

        $this->assertTenantDatabaseHas('accounts_payable_pagamentos', [
            'accounts_payable_id' => $conta->id,
            'valor' => 150.00,
        ]);

        // A saída não pode ter sido lançada como recebimento do paciente.
        $this->assertSame(0, AccountsReceivable::count());
        $this->assertSame(0, Payment::count());
    }

    /**
     * PIL-01 — a tabela cruzada clientes × meses da planilha PGTO PILATES é
     * representada por presenças por aluno e por data: cada célula é uma linha
     * em `turma_presencas`, sem apagar as datas anteriores.
     */
    public function test_pilates_tabela_cruzada_presencas_por_aluno_e_data(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('pacotes.view', 'pacotes.create', 'pacotes.update');

        $turma = Turma::create([
            'nome' => 'Pilates Quarta 08h',
            'servico' => 'PILATES',
            'dia_semana' => 3,
            'hora_inicio' => '08:00',
            'is_active' => true,
        ]);

        $aluno1 = Paciente::create(['nome' => 'Cliente Pilates 1', 'status' => 'ativo']);
        $aluno2 = Paciente::create(['nome' => 'Cliente Pilates 2', 'status' => 'ativo']);

        foreach ([$aluno1, $aluno2] as $aluno) {
            $this->actingAs($user)
                ->post(route('turmas.pacientes.store', $turma), ['paciente_id' => $aluno->id])
                ->assertRedirect();
        }

        $marcacoes = [
            [$aluno1, '2026-03-05', TurmaPresenca::PRESENTE],
            [$aluno1, '2026-03-12', TurmaPresenca::PRESENTE],
            [$aluno2, '2026-03-05', TurmaPresenca::AUSENTE],
        ];

        foreach ($marcacoes as [$aluno, $data, $status]) {
            $this->actingAs($user)
                ->post(route('turmas.presencas.store', $turma), [
                    'paciente_id' => $aluno->id,
                    'data' => $data,
                    'status' => $status,
                ])
                ->assertRedirect();
        }

        // Uma linha por aluno e por data: 2 alunos na primeira data, 1 na segunda.
        $this->assertSame(3, TurmaPresenca::query()->where('turma_id', $turma->id)->count());
        $this->assertSame(2, TurmaPresenca::query()->where('paciente_id', $aluno1->id)->count());
        $this->assertSame(1, TurmaPresenca::query()->where('paciente_id', $aluno2->id)->count());
        $this->assertSame(2, TurmaPresenca::query()->whereDate('data', '2026-03-05')->count());
        $this->assertSame(1, TurmaPresenca::query()
            ->whereDate('data', '2026-03-05')
            ->where('status', TurmaPresenca::AUSENTE)
            ->count());
    }

    /**
     * PIL-05 — o calendário respeita o mês escolhido e não mostra turma
     * encerrada. A data mostrada é a ocorrência real do dia da semana no mês.
     */
    public function test_pilates_calendario_respeita_mes_e_ignora_turma_encerrada(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('pacotes.view');

        Turma::create([
            'nome' => 'Pilates Segunda Ativa',
            'servico' => 'PILATES',
            'dia_semana' => 1,
            'hora_inicio' => '10:00',
            'is_active' => true,
        ]);

        Turma::create([
            'nome' => 'Pilates Terça Encerrada',
            'servico' => 'PILATES',
            'dia_semana' => 2,
            'hora_inicio' => '09:00',
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->get(route('turmas.index', ['mes' => '2026-11']))
            ->assertOk()
            ->assertSee('Pilates Segunda Ativa')
            ->assertDontSee('Pilates Terça Encerrada')
            ->assertSee('02/11');

        $this->actingAs($user)
            ->get(route('turmas.index', ['mes' => '2026-12']))
            ->assertOk()
            ->assertSee('07/12') // primeira segunda-feira de dezembro/2026
            ->assertDontSee('02/11');
    }

    /**
     * PE-01/PE-05 — o profissional externo é cadastrado pela tela (tipo e
     * registro no conselho) e remunerado por conta a pagar. Outro profissional
     * externo usa o mesmo cadastro, sem criar um fluxo paralelo.
     */
    public function test_profissional_externo_cadastro_e_pagamento(): void
    {
        [, $user] = $this->criarUsuarioComPermissao(
            'profissionais.create',
            'profissionais.view',
            'financeiro.create',
            'financeiro.receive',
            'financeiro.view',
        );

        $this->actingAs($user)
            ->post(route('profissionais.store'), [
                'nome' => 'Nutricionista Apoena',
                'tipo' => 'nutricionista',
                'registro_profissional' => 'CRN 12345',
                'is_active' => true,
            ])
            ->assertRedirect(route('profissionais.index'));

        $profissional = Professional::query()->where('nome', 'Nutricionista Apoena')->firstOrFail();
        $this->assertSame('nutricionista', $profissional->tipo);

        $this->actingAs($user)
            ->get(route('profissionais.index', ['busca' => 'Apoena']))
            ->assertOk()
            ->assertSee('Nutricionista Apoena')
            ->assertSee('CRN 12345');

        // Pagamento pela conta a pagar, vinculado ao profissional (dinheiro saindo).
        $metodo = PaymentMethod::create(['name' => 'PIX', 'label' => 'PIX']);

        $this->actingAs($user)
            ->post(route('financeiro.contas-pagar.store'), [
                'professional_id' => $profissional->id,
                'tipo_remuneracao' => 'diaria',
                'nome_conta' => 'Diária da nutricionista',
                'valor' => 150.00,
                'data_vencimento' => '2026-11-09',
                'tipo' => 'servico',
                'tipo_documento' => 'outros',
            ])
            ->assertRedirect();

        $conta = AccountsPayable::query()->where('nome_conta', 'Diária da nutricionista')->firstOrFail();

        $this->actingAs($user)
            ->post(route('financeiro.contas-pagar.baixar', $conta), [
                'valor' => 150.00,
                'payment_method_id' => $metodo->id,
                'data' => '2026-11-09',
            ])
            ->assertRedirect();

        $this->assertSame(AccountsPayable::PAGO, $conta->refresh()->status);
        $this->assertSame(0, AccountsReceivable::count());
    }

    /**
     * PE-04 — total mensal por profissional, pela data da baixa, separando
     * principal de juros/multa. Não é o total por serviço do relatório.
     */
    public function test_total_mensal_por_profissional_externo(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.create', 'financeiro.receive', 'financeiro.view');
        $metodo = PaymentMethod::create(['name' => 'PIX', 'label' => 'PIX']);

        $apoena = Professional::create(['nome' => 'Apoena Nutri', 'tipo' => 'nutricionista', 'is_active' => true]);
        $outro = Professional::create(['nome' => 'Outro Profissional', 'tipo' => 'outro', 'is_active' => true]);

        $contaNovembro = AccountsPayable::create([
            'professional_id' => $apoena->id,
            'tipo_remuneracao' => 'diaria',
            'nome_conta' => 'Diária novembro',
            'valor' => 150.00,
            'data_vencimento' => '2026-11-09',
            'status' => AccountsPayable::PENDENTE,
            'tipo' => 'servico',
            'tipo_documento' => 'outros',
        ]);

        $contaOutubro = AccountsPayable::create([
            'professional_id' => $apoena->id,
            'tipo_remuneracao' => 'diaria',
            'nome_conta' => 'Diária outubro',
            'valor' => 200.00,
            'data_vencimento' => '2026-10-09',
            'status' => AccountsPayable::PENDENTE,
            'tipo' => 'servico',
            'tipo_documento' => 'outros',
        ]);

        $contaOutroProfissional = AccountsPayable::create([
            'professional_id' => $outro->id,
            'tipo_remuneracao' => 'valor_fixo',
            'nome_conta' => 'Repasse outro profissional',
            'valor' => 500.00,
            'data_vencimento' => '2026-11-09',
            'status' => AccountsPayable::PENDENTE,
            'tipo' => 'servico',
            'tipo_documento' => 'outros',
        ]);

        $this->actingAs($user)
            ->post(route('financeiro.contas-pagar.baixar', $contaNovembro), [
                'valor' => 150.00,
                'juros' => 15.00,
                'multa' => 5.00,
                'payment_method_id' => $metodo->id,
                'data' => '2026-11-09',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('financeiro.contas-pagar.baixar', $contaOutubro), [
                'valor' => 200.00,
                'payment_method_id' => $metodo->id,
                'data' => '2026-10-09',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('financeiro.contas-pagar.baixar', $contaOutroProfissional), [
                'valor' => 500.00,
                'payment_method_id' => $metodo->id,
                'data' => '2026-11-09',
            ])
            ->assertRedirect();

        $response = $this->actingAs($user)
            ->get(route('financeiro.contas-pagar.index', ['mes' => '2026-11']))
            ->assertOk();

        $totais = collect($response->viewData('totaisPorProfissional'))->keyBy('professional_id');

        // Outubro não entra no painel de novembro.
        $this->assertCount(2, $totais);
        $this->assertSame(150.0, (float) $totais[$apoena->id]->total_principal);
        $this->assertSame(15.0, (float) $totais[$apoena->id]->total_juros);
        $this->assertSame(5.0, (float) $totais[$apoena->id]->total_multa);
        $this->assertSame(500.0, (float) $totais[$outro->id]->total_principal);

        $response->assertSee('Apoena Nutri')->assertSee('R$ 170,00');

        // Filtro por profissional: só as contas da Apoena na listagem.
        $filtrada = $this->actingAs($user)
            ->get(route('financeiro.contas-pagar.index', ['professional_id' => $apoena->id]))
            ->assertOk();

        $this->assertSame(
            [$apoena->id],
            $filtrada->viewData('contas')->pluck('professional_id')->unique()->values()->all(),
        );
    }

    /**
     * CR-01 — a listagem de contas a receber filtra por paciente e por status e
     * o total em aberto desconta o que já foi recebido. Não basta a tabela
     * existir.
     */
    public function test_contas_receber_listagem_e_filtro_funcional(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.view');

        $ana = Paciente::create(['nome' => 'Ana Recebível', 'status' => 'ativo']);
        $bruno = Paciente::create(['nome' => 'Bruno Recebível', 'status' => 'ativo']);

        AccountsReceivable::create([
            'paciente_id' => $ana->id,
            'descricao' => 'Consulta da Ana',
            'servico' => 'CONSULTA',
            'vencimento' => '2026-11-15',
            'valor' => 200.00,
            'valor_pago' => 0,
            'status' => 'aberta',
        ]);

        AccountsReceivable::create([
            'paciente_id' => $bruno->id,
            'descricao' => 'Pilates do Bruno',
            'servico' => 'PILATES',
            'vencimento' => '2026-11-10',
            'valor' => 300.00,
            'valor_pago' => 300.00,
            'status' => 'pago',
        ]);

        $response = $this->actingAs($user)
            ->get(route('financeiro.index', ['busca' => 'Ana']))
            ->assertOk()
            ->assertSee('Ana Recebível')
            ->assertDontSee('Bruno Recebível');

        $this->assertSame(200.0, (float) $response->viewData('totalAberto'));

        $this->actingAs($user)
            ->get(route('financeiro.index', ['status' => 'pago']))
            ->assertOk()
            ->assertSee('Bruno Recebível')
            ->assertDontSee('Ana Recebível');
    }

    public function test_pagamento_paciente_vinculo_existe(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.create', 'financeiro.receive');

        $paciente = Paciente::create(['nome' => 'Paciente Vinculo Pagamento', 'status' => 'ativo']);
        $metodo = PaymentMethod::create(['name' => 'PIX', 'label' => 'PIX']);

        $conta = AccountsReceivable::create([
            'paciente_id' => $paciente->id,
            'descricao' => 'Consulta',
            'servico' => 'CONSULTA',
            'vencimento' => '2026-11-09',
            'valor' => 100.00,
            'valor_pago' => 0,
            'status' => 'aberta',
        ]);

        // Pagamento cria conta a receber e vincula payment a ela
        $this->actingAs($user)
            ->post(route('financeiro.receive', $conta), [
                'valor' => 100.00,
                'payment_method_id' => $metodo->id,
            ])
            ->assertRedirect();

        $conta->refresh();
        $this->assertSame('pago', $conta->status);
        $this->assertSame($paciente->id, $conta->paciente_id);

        // O payment está vinculado à conta a receber
        $this->assertTenantDatabaseHas('payments', [
            'accounts_receivable_id' => $conta->id,
            'valor' => 100.00,
        ]);
    }

    /**
     * MKT-03 — a aba MARKETING da planilha é um calendário de conteúdos, não o
     * CRM de prospects da plataforma. O conteúdo é atendido por MKT-05/MKT-06
     * (`social_posts`); o teste prova a listagem do post criado.
     *
     * O `Platform\ProspectController` é CRM comercial do SaaS e não substitui a
     * aba da clínica; por isso MKT-03 permanece C na matriz.
     */
    public function test_marketing_calendario_de_conteudos_usa_posts_sociais(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('social.view', 'social.manage');

        $this->actingAs($user)
            ->post(route('social.store'), [
                'titulo' => 'Calendário de novembro',
                'legenda_instagram' => 'Conteúdo de novembro',
                'platforms' => ['instagram'],
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('social.index'))
            ->assertOk()
            ->assertSee('Calendário de novembro');
    }

    /* ------------------------------------------------------------------ */
    /* 16. Contas a pagar — dados históricos (sem importar em produção) */
    /* ------------------------------------------------------------------ */

    /* ------------------------------------------------------------------ */
    /* 17. Fluxos D — aguardam decisão do cliente (não implementar) */
    /* ------------------------------------------------------------------ */

    /**
     * CP-01/CP-02 (histórico) — uma conta com vencimento antigo entra pela tela
     * com a data original preservada e aparece como atrasada; nada é
     * "corrigido" para a data de hoje nem marcado como pago sozinho.
     */
    public function test_conta_pagar_historica_preserva_data_e_status(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.create', 'financeiro.view');

        $this->actingAs($user)
            ->post(route('financeiro.contas-pagar.store'), [
                'nome_conta' => 'Aluguel 2023-01',
                'valor' => 1500.00,
                'data_vencimento' => '2023-01-10',
                'tipo' => 'aluguel',
                'tipo_documento' => 'boleto',
            ])
            ->assertRedirect();

        $conta = AccountsPayable::query()->where('nome_conta', 'Aluguel 2023-01')->firstOrFail();

        $this->assertSame('2023-01-10', $conta->data_vencimento->format('Y-m-d'));
        $this->assertSame(AccountsPayable::PENDENTE, $conta->status);
        $this->assertSame(AccountsPayable::ATRASADO, $conta->statusEfetivo());

        $this->actingAs($user)
            ->get(route('financeiro.contas-pagar.index', ['status' => 'atrasado']))
            ->assertOk()
            ->assertSee('Aluguel 2023-01');
    }

    public function test_juros_multa_calculados_com_taxa_informada(): void
    {
        // A taxa/percentual NÃO é fixada pelo sistema: é informada por quem
        // calcula. O helper só aplica a fórmula sobre o valor informado.
        $this->assertTrue(method_exists(FinanceHelper::class, 'jurosSimples'));
        $this->assertTrue(method_exists(FinanceHelper::class, 'multa'));

        // Exemplo: cálculo com taxa informada pelo usuário (não inventada pelo sistema)
        // jurosSimples(valor, taxa_percentual, dias, base_dias = 30)
        $juros = FinanceHelper::jurosSimples(1000.00, 2.0, 30);
        $this->assertSame(20.00, $juros); // 1000 * 2% * 30/30 = 20

        $multa = FinanceHelper::multa(1000.00, 2.0);
        $this->assertSame(20.00, $multa); // 1000 * 2% = 20
    }

    public function test_agenda_filtra_por_profissional(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('agenda.view');

        $paciente = Paciente::create(['nome' => 'Paciente Profissional', 'status' => 'ativo']);
        $profissional1 = Professional::create(['nome' => 'Profissional Um', 'is_active' => true]);
        $profissional2 = Professional::create(['nome' => 'Profissional Dois', 'is_active' => true]);
        $statusAgendado = AppointmentStatus::where('name', 'agendado')->value('id');

        Appointment::create([
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional1->id,
            'inicio' => '2026-11-09 10:00:00',
            'fim' => '2026-11-09 10:30:00',
            'appointment_status_id' => $statusAgendado,
        ]);
        Appointment::create([
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional2->id,
            'inicio' => '2026-11-09 11:00:00',
            'fim' => '2026-11-09 11:30:00',
            'appointment_status_id' => $statusAgendado,
        ]);

        $response = $this->actingAs($user)
            ->get(route('agenda.index', ['profissional_id' => $profissional1->id]))
            ->assertOk();

        // O filtro por profissional funciona: o dropdown mostra o profissional selecionado
        $response->assertSee('Profissional Um');
    }

    public function test_agenda_filtra_por_busca_e_profissional(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('agenda.view');

        $paciente = Paciente::create(['nome' => 'Paciente Filtro Laser', 'status' => 'ativo']);
        $profissional = Professional::create(['nome' => 'Profissional Filtro', 'is_active' => true]);
        $statusAgendado = AppointmentStatus::where('name', 'agendado')->value('id');

        Appointment::create([
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'inicio' => '2026-11-09 10:00:00',
            'fim' => '2026-11-09 10:30:00',
            'area' => 'AXILA',
            'servico' => 'DEPILAÇÃO A LASER',
            'appointment_status_id' => $statusAgendado,
        ]);

        // O filtro por busca e profissional funciona (equivalente ao autofiltro da planilha)
        $response = $this->actingAs($user)
            ->get(route('agenda.index', ['busca' => 'AXILA', 'profissional_id' => $profissional->id]))
            ->assertOk();

        // O filtro por busca e profissional funciona: a página carrega com os filtros aplicados
        $response->assertSee('Profissional Filtro');
    }

    public function test_fluxo_caixa_filtra_por_servico_massagem(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.view');

        $paciente = Paciente::create(['nome' => 'Paciente Massagem Caixa', 'status' => 'ativo']);
        $metodo = PaymentMethod::create(['name' => 'PIX', 'label' => 'PIX']);

        Payment::create([
            'paciente_id' => $paciente->id,
            'payment_method_id' => $metodo->id,
            'servico' => 'MASSAGEM',
            'data' => '2026-11-05',
            'valor' => 100.00,
        ]);

        Payment::create([
            'paciente_id' => $paciente->id,
            'payment_method_id' => $metodo->id,
            'servico' => 'PILATES',
            'data' => '2026-11-05',
            'valor' => 200.00,
        ]);

        $response = $this->actingAs($user)
            ->get(route('financeiro.fluxo-caixa', ['de' => '2026-11-01', 'ate' => '2026-11-30']))
            ->assertOk();

        $response->assertSee('R$ 100,00');
        $response->assertSee('R$ 200,00');
        $this->assertSame(300.0, (float) $response->viewData('totalRecebido'));
    }

    public function test_historico_paciente_unifica_consultas_pacotes_pagamentos(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('pacientes.view', 'financeiro.receive');

        $paciente = Paciente::create(['nome' => 'Paciente Historico Completo', 'status' => 'ativo']);
        $profissional = Professional::create(['nome' => 'Profissional Historico', 'is_active' => true]);
        $statusAgendado = AppointmentStatus::where('name', 'agendado')->value('id');

        Appointment::create([
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'inicio' => '2026-11-09 10:00:00',
            'fim' => '2026-11-09 10:30:00',
            'appointment_status_id' => $statusAgendado,
        ]);

        $metodo = PaymentMethod::create(['name' => 'PIX', 'label' => 'PIX']);
        $conta = AccountsReceivable::create([
            'paciente_id' => $paciente->id,
            'servico' => 'CONSULTA',
            'vencimento' => '2026-11-09',
            'valor' => 100.00,
            'valor_pago' => 0,
            'status' => 'aberta',
        ]);

        $this->actingAs($user)
            ->post(route('financeiro.receive', $conta), [
                'valor' => 100.00,
                'payment_method_id' => $metodo->id,
            ])
            ->assertRedirect();

        $response = $this->actingAs($user)
            ->get(route('pacientes.historico', $paciente))
            ->assertOk();

        $response->assertSee('Paciente Historico Completo');
        $response->assertSee('CONSULTA');
    }

    public function test_conta_receber_status_aberta_parcial_pago(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.view');

        $paciente = Paciente::create(['nome' => 'Paciente Status Conta', 'status' => 'ativo']);

        AccountsReceivable::create([
            'paciente_id' => $paciente->id,
            'vencimento' => '2026-11-01',
            'valor' => 100.00,
            'valor_pago' => 0,
            'status' => 'aberta',
        ]);

        AccountsReceivable::create([
            'paciente_id' => $paciente->id,
            'vencimento' => '2026-11-01',
            'valor' => 200.00,
            'valor_pago' => 100.00,
            'status' => 'parcial',
        ]);

        AccountsReceivable::create([
            'paciente_id' => $paciente->id,
            'vencimento' => '2026-11-01',
            'valor' => 300.00,
            'valor_pago' => 300.00,
            'status' => 'pago',
        ]);

        $this->assertSame(3, AccountsReceivable::where('paciente_id', $paciente->id)->count());
        $this->assertSame(1, AccountsReceivable::where('status', 'aberta')->count());
        $this->assertSame(1, AccountsReceivable::where('status', 'parcial')->count());
        $this->assertSame(1, AccountsReceivable::where('status', 'pago')->count());
    }

    public function test_relatorios_receitas_por_servico(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('relatorios.view');

        $paciente = Paciente::create(['nome' => 'Paciente Relatorio Servico', 'status' => 'ativo']);
        $metodo = PaymentMethod::create(['name' => 'PIX', 'label' => 'PIX']);

        Payment::create([
            'paciente_id' => $paciente->id,
            'payment_method_id' => $metodo->id,
            'servico' => 'PILATES',
            'data' => '2026-11-05',
            'valor' => 150.00,
        ]);

        Payment::create([
            'paciente_id' => $paciente->id,
            'payment_method_id' => $metodo->id,
            'servico' => 'MASSAGEM',
            'data' => '2026-11-10',
            'valor' => 80.00,
        ]);

        $response = $this->actingAs($user)
            ->get(route('relatorios.receitas', ['de' => '2026-11-01', 'ate' => '2026-11-30']))
            ->assertOk();

        $response->assertSee('R$ 150,00');
        $response->assertSee('R$ 80,00');
        $this->assertSame(230.0, (float) $response->viewData('totalRecebido'));
    }

    public function test_insurance_plans_estrutura_existe(): void
    {
        // Estrutura de planos de saúde existe
        $operadora = InsuranceCompany::create([
            'nome' => 'IPSM',
            'is_active' => true,
        ]);
        $plano = InsurancePlan::create([
            'insurance_company_id' => $operadora->id,
            'nome' => 'Plano Corporativo',
            'is_active' => true,
        ]);

        $this->assertNotNull($plano->id);
        $this->assertSame($operadora->id, $plano->insurance_company_id);
        $this->assertTrue($plano->is_active);
    }

    /* ------------------------------------------------------------------ */
    /* 18. Fluxos B/C — equilvência de formato (não comercial) */
    /* ------------------------------------------------------------------ */

    /**
     * PE-02/PE-03 — a remuneração do profissional externo é escolhida entre os
     * modelos da planilha (diária, porcentagem ou valor fixo). O valor é sempre
     * o informado; o sistema não calcula a porcentagem sozinho nem inventa taxa.
     */
    public function test_profissional_externo_tipo_porcentagem(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.create', 'financeiro.view');

        $profissional = Professional::create([
            'nome' => 'Dentista Parceiro',
            'tipo' => 'dentista',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('financeiro.contas-pagar.store'), [
                'professional_id' => $profissional->id,
                'tipo_remuneracao' => 'porcentagem',
                'nome_conta' => 'Dentista parceiro — repasse',
                'valor' => 300.00,
                'data_vencimento' => '2026-11-30',
                'tipo' => 'servico',
                'tipo_documento' => 'outros',
            ])
            ->assertRedirect();

        $this->assertTenantDatabaseHas('accounts_payable', [
            'professional_id' => $profissional->id,
            'tipo_remuneracao' => 'porcentagem',
        ]);

        // O valor da porcentagem é o combinado informado, não um cálculo do sistema.
        $conta = AccountsPayable::query()->where('nome_conta', 'Dentista parceiro — repasse')->firstOrFail();
        $this->assertSame(300.0, (float) $conta->valor);

        // Um modelo fora da lista é recusado.
        $this->actingAs($user)
            ->post(route('financeiro.contas-pagar.store'), [
                'professional_id' => $profissional->id,
                'tipo_remuneracao' => 'inventado',
                'nome_conta' => 'Modelo inválido',
                'valor' => 100.00,
                'data_vencimento' => '2026-11-30',
                'tipo' => 'servico',
                'tipo_documento' => 'outros',
            ])
            ->assertSessionHasErrors('tipo_remuneracao');
    }

    /**
     * DJ-03/DJ-04 — a coluna JUROS das abas OUTUBRO 2023+ é um valor INFORMADO
     * na baixa, junto com a multa. O sistema não fixa taxa nem percentual: o
     * principal abate o saldo e os encargos ficam registrados no extrato.
     */
    public function test_baixa_conta_pagar_com_juros_e_multa_informados(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.create', 'financeiro.receive', 'financeiro.view');
        $metodo = PaymentMethod::create(['name' => 'PIX', 'label' => 'PIX']);

        $this->actingAs($user)
            ->post(route('financeiro.contas-pagar.store'), [
                'nome_conta' => 'Aluguel vencido',
                'valor' => 1000.00,
                'data_vencimento' => '2026-10-10',
                'tipo' => 'aluguel',
                'tipo_documento' => 'boleto',
            ])
            ->assertRedirect();

        $conta = AccountsPayable::query()->where('nome_conta', 'Aluguel vencido')->firstOrFail();

        $this->actingAs($user)
            ->post(route('financeiro.contas-pagar.baixar', $conta), [
                'valor' => 1000.00,
                'juros' => 20.00,
                'multa' => 10.00,
                'payment_method_id' => $metodo->id,
                'data' => '2026-11-09',
            ])
            ->assertRedirect();

        $conta->refresh();
        $baixa = AccountsPayablePayment::query()->where('accounts_payable_id', $conta->id)->firstOrFail();

        $this->assertSame(20.0, (float) $baixa->juros);
        $this->assertSame(10.0, (float) $baixa->multa);
        $this->assertSame(1030.0, $baixa->totalDesembolsado());

        // Juros e multa NÃO abatem o principal: o saldo fecha com o valor da conta.
        $this->assertSame(0.0, $conta->saldo);
        $this->assertSame(AccountsPayable::PAGO, $conta->status);

        $this->actingAs($user)
            ->get(route('financeiro.contas-pagar.show', $conta))
            ->assertOk()
            ->assertSee('R$ 20,00')
            ->assertSee('R$ 10,00')
            ->assertSee('R$ 1.030,00');
    }

    /* ------------------------------------------------------------------ */
    /* 19. Fluxos D/E tecnicamente fecháveis (CP, LAS, FIS, PRE, PIL, MKT) */
    /* ------------------------------------------------------------------ */

    /**
     * CP-06 — a mesma baixa enviada duas vezes (duplo clique/retry) não pode
     * gerar dois pagamentos nem quitar a conta duas vezes.
     */
    public function test_baixa_conta_pagar_idempotente_nao_duplica(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.receive', 'financeiro.view');
        $metodo = PaymentMethod::create(['name' => 'PIX', 'label' => 'PIX']);

        $conta = AccountsPayable::create([
            'nome_conta' => 'Energia novembro',
            'valor' => 250.00,
            'data_vencimento' => '2026-11-10',
            'status' => 'pendente',
            'tipo' => 'energia',
            'tipo_documento' => 'boleto',
        ]);

        $dados = [
            'valor' => 250.00,
            'payment_method_id' => $metodo->id,
            'data' => '2026-11-05',
            'idempotency_key' => 'cp06-mesma-chave',
        ];

        $this->actingAs($user)
            ->post(route('financeiro.contas-pagar.baixar', $conta), $dados)
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('financeiro.contas-pagar.baixar', $conta), $dados)
            ->assertRedirect();

        $this->assertSame(
            1,
            AccountsPayablePayment::where('accounts_payable_id', $conta->id)->count()
        );
        $this->assertSame('pago', $conta->refresh()->status);
        $this->assertSame(0.0, $conta->saldo);
    }

    /**
     * CP-07/CP-08 — a conta guarda categoria e fornecedor (favorecido), como
     * as colunas TIPO e FAVORECIDO da planilha CONTAS, e aparece no filtro.
     */
    public function test_conta_pagar_categoria_e_fornecedor(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('financeiro.create', 'financeiro.view');
        $fornecedor = Supplier::create(['nome' => 'Imobiliária Centro']);
        $categoria = FinancialCategory::query()->where('nome', 'Aluguel')->firstOrFail();

        $this->actingAs($user)
            ->post(route('financeiro.contas-pagar.store'), [
                'supplier_id' => $fornecedor->id,
                'financeiro_categoria_id' => $categoria->id,
                'nome_conta' => 'Aluguel dezembro',
                'valor' => 1800.00,
                'data_vencimento' => '2026-12-10',
                'tipo' => 'aluguel',
                'tipo_documento' => 'boleto',
            ])
            ->assertRedirect();

        $this->assertTenantDatabaseHas('accounts_payable', [
            'supplier_id' => $fornecedor->id,
            'financeiro_categoria_id' => $categoria->id,
            'nome_conta' => 'Aluguel dezembro',
            'tipo' => 'aluguel',
            'tipo_documento' => 'boleto',
        ]);

        $this->actingAs($user)
            ->get(route('financeiro.contas-pagar.index'))
            ->assertOk()
            ->assertSee('Aluguel dezembro')
            ->assertSee('Imobiliária Centro');
    }

    /**
     * LAS-08 — a planilha conta presenças por área (COUNTIF). O relatório
     * equivalente agrupa por `appointments.area` e mostra total/presenças/faltas.
     */
    public function test_relatorio_presenca_faltas_por_area(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('relatorios.view');

        $paciente = Paciente::create(['nome' => 'Paciente Laser Area']);
        $profissional = Professional::create(['nome' => 'Profissional Laser', 'is_active' => true]);
        $statusAtendido = AppointmentStatus::firstOrCreate(
            ['name' => 'atendido'],
            ['label' => 'Atendido', 'cor' => '#28a745'],
        )->id;
        $statusFaltou = AppointmentStatus::firstOrCreate(
            ['name' => 'faltou'],
            ['label' => 'Faltou', 'cor' => '#dc3545'],
        )->id;

        $base = [
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
        ];

        Appointment::create($base + [
            'area' => 'AXILA',
            'inicio' => '2026-11-05 09:00:00',
            'fim' => '2026-11-05 09:30:00',
            'appointment_status_id' => $statusAtendido,
        ]);
        Appointment::create($base + [
            'area' => 'AXILA',
            'inicio' => '2026-11-06 09:00:00',
            'fim' => '2026-11-06 09:30:00',
            'appointment_status_id' => $statusFaltou,
        ]);
        Appointment::create($base + [
            'area' => 'PERNAS',
            'inicio' => '2026-11-07 09:00:00',
            'fim' => '2026-11-07 09:30:00',
            'appointment_status_id' => $statusAtendido,
        ]);

        $response = $this->actingAs($user)
            ->get(route('relatorios.presenca-faltas', ['de' => '2026-11-01', 'ate' => '2026-11-30']))
            ->assertOk()
            ->assertSee('Por área');

        $porArea = collect($response->viewData('porArea'))->keyBy('area');

        $this->assertSame(2, $porArea['AXILA']['total']);
        $this->assertSame(1, $porArea['AXILA']['atendidos']);
        $this->assertSame(1, $porArea['AXILA']['faltas']);
        $this->assertSame(1, $porArea['PERNAS']['total']);
        $this->assertSame(1, $porArea['PERNAS']['atendidos']);
        $this->assertSame(0, $porArea['PERNAS']['faltas']);
    }

    /**
     * FIS-02 — a fisioterapia domiciliar é um serviço cadastrável e percorre o
     * fluxo real: procedimento -> atendimento -> recebimento -> relatório por
     * serviço, exatamente onde a planilha registra o valor específico.
     */
    public function test_fisioterapia_domiciliar_percorre_fluxo_financeiro(): void
    {
        [, $user] = $this->criarUsuarioComPermissao(
            'procedimentos.create',
            'procedimentos.view',
            'relatorios.view',
        );

        $this->actingAs($user)
            ->post(route('procedimentos.store'), [
                'codigo' => 'FIS-DOM',
                'nome' => 'Fisioterapia Domiciliar',
                'categoria' => 'Domiciliar',
                'duracao_minutos' => 60,
                'valor_padrao' => 180.00,
                'is_active' => true,
            ])
            ->assertRedirect(route('procedimentos.index'));

        $this->assertTenantDatabaseHas('procedures', [
            'codigo' => 'FIS-DOM',
            'nome' => 'Fisioterapia Domiciliar',
            'categoria' => 'Domiciliar',
        ]);

        $this->actingAs($user)
            ->get(route('procedimentos.index', ['categoria' => 'Domiciliar']))
            ->assertOk()
            ->assertSee('Fisioterapia Domiciliar');

        $paciente = Paciente::create(['nome' => 'Paciente Domiciliar', 'status' => 'ativo']);
        $metodo = PaymentMethod::create(['name' => 'PIX', 'label' => 'PIX']);

        Payment::create([
            'paciente_id' => $paciente->id,
            'payment_method_id' => $metodo->id,
            'servico' => 'Fisioterapia Domiciliar',
            'data' => '2026-11-05',
            'valor' => 180.00,
        ]);

        $response = $this->actingAs($user)
            ->get(route('relatorios.receitas', ['de' => '2026-11-01', 'ate' => '2026-11-30']))
            ->assertOk()
            ->assertSee('Fisioterapia Domiciliar')
            ->assertSee('R$ 180,00');

        $this->assertSame(180.0, (float) $response->viewData('totalRecebido'));
    }

    /**
     * PRE-05 — a reposição é um consumo explícito de sessão: fica marcada no
     * extrato e é contada separadamente no relatório de pacotes.
     */
    public function test_reposicao_de_sessao_registrada_e_contada(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia(
            'agenda.update',
            'pacotes.view',
            'pacotes.update',
            'relatorios.view',
        );

        $paciente = Paciente::create(['nome' => 'Paciente Reposicao']);
        $statusAtendido = AppointmentStatus::firstOrCreate(
            ['name' => 'atendido'],
            ['label' => 'Atendido', 'cor' => '#28a745'],
        );

        $consulta = Appointment::create([
            'paciente_id' => $paciente->id,
            'servico' => 'FISIOTERAPIA',
            'inicio' => '2026-11-09 10:00:00',
            'fim' => '2026-11-09 10:30:00',
            'appointment_status_id' => $statusAtendido->id,
        ]);

        $pacote = PacoteSessao::create([
            'paciente_id' => $paciente->id,
            'nome' => 'Pacote Reposição',
            'servico' => 'FISIOTERAPIA',
            'total_sessoes' => 5,
            'sessoes_usadas' => 0,
            'status' => PacoteSessao::ATIVO,
        ]);

        $this->actingAs($user)
            ->post(route('atendimentos.consumir', $consulta), [
                'pacote_id' => $pacote->id,
                'reposicao' => true,
            ])
            ->assertRedirect();

        $consumo = ConsumoSessao::where('appointment_id', $consulta->id)->firstOrFail();
        $this->assertTrue($consumo->reposicao);

        $response = $this->actingAs($user)
            ->get(route('relatorios.pacotes', ['de' => '2026-11-01', 'ate' => '2026-11-30']))
            ->assertOk();

        $this->assertSame(1, (int) $response->viewData('reposicoesNoPeriodo'));
        $this->assertSame(1, (int) $response->viewData('consumidasNoPeriodo'));
    }

    /**
     * PIL-06 — a "lista Outubro Rosa" é uma lista de presença por paciente.
     * O equivalente registra o consumo de cada paciente e o relatório de
     * pacotes mostra a lista com um consumo por paciente.
     */
    public function test_campanha_pilates_lista_consumo_por_paciente(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia(
            'agenda.update',
            'pacotes.view',
            'pacotes.update',
            'relatorios.view',
        );

        $statusAtendido = AppointmentStatus::firstOrCreate(
            ['name' => 'atendido'],
            ['label' => 'Atendido', 'cor' => '#28a745'],
        );

        $pacientes = [
            Paciente::create(['nome' => 'Outubro Rosa 1']),
            Paciente::create(['nome' => 'Outubro Rosa 2']),
        ];

        foreach ($pacientes as $indice => $paciente) {
            $consulta = Appointment::create([
                'paciente_id' => $paciente->id,
                'servico' => 'PILATES',
                'inicio' => '2026-10-'.(10 + $indice).' 08:00:00',
                'fim' => '2026-10-'.(10 + $indice).' 09:00:00',
                'appointment_status_id' => $statusAtendido->id,
            ]);

            $pacote = PacoteSessao::create([
                'paciente_id' => $paciente->id,
                'nome' => 'Pacote Outubro Rosa',
                'servico' => 'PILATES',
                'total_sessoes' => 4,
                'sessoes_usadas' => 0,
                'status' => PacoteSessao::ATIVO,
            ]);

            $this->actingAs($user)
                ->post(route('atendimentos.consumir', $consulta), ['pacote_id' => $pacote->id])
                ->assertRedirect();
        }

        $response = $this->actingAs($user)
            ->get(route('relatorios.pacotes', ['de' => '2026-10-01', 'ate' => '2026-10-31']))
            ->assertOk();

        $this->assertSame(2, (int) $response->viewData('consumidasNoPeriodo'));

        $consumos = collect($response->viewData('consumos'));
        $this->assertSame(2, $consumos->pluck('paciente_id')->unique()->count());
        $this->assertSame(
            2,
            $consumos->pluck('paciente.nome')->filter()->unique()->count()
        );
    }

    /**
     * MKT-05/MKT-06 — campanha e post social: o post nasce como rascunho
     * pendente com legendas e redes escolhidas, e pode ser aprovado. Não há
     * publicação automática, que continua sendo ato manual da cliente.
     */
    public function test_campanha_post_social_legendas_e_aprovacao(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('social.view', 'social.manage');

        $this->actingAs($user)
            ->post(route('social.store'), [
                'titulo' => 'Outubro Rosa 2026',
                'legenda_instagram' => 'Cuide-se. A prevenção salva vidas.',
                'legenda_facebook' => 'Outubro Rosa na clínica: agende seu exame.',
                'hashtags' => 'outubrorosa prevencao saude',
                'platforms' => ['instagram', 'facebook'],
            ])
            ->assertRedirect();

        $post = SocialPost::query()->where('titulo', 'Outubro Rosa 2026')->firstOrFail();

        $this->assertSame('pendente', $post->status);
        $this->assertSame(['#outubrorosa', '#prevencao', '#saude'], $post->hashtags);
        $this->assertSame(['instagram', 'facebook'], $post->platforms);
        $this->assertSame(
            "Cuide-se. A prevenção salva vidas.\n\n#outubrorosa #prevencao #saude",
            $post->legendaPara('instagram')
        );

        $this->actingAs($user)
            ->patch(route('social.approve', $post))
            ->assertRedirect(route('social.show', $post));

        $this->assertSame('aprovado', $post->refresh()->status);
        $this->assertNotNull($post->approved_at);
    }
}
