<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\ConsumoSessao;
use App\Models\Paciente;
use App\Models\PacoteSessao;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Professional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

/**
 * Relatórios da operação da clínica.
 *
 * O que a Daiane lia nas planilhas precisa continuar legível no sistema:
 * receitas por serviço e forma, presença/falta com taxa e saldo de pacotes.
 * O CSV existe para quem ainda quiser abrir no Excel — com BOM e ponto e
 * vírgula, e sem transformar texto digitado em fórmula.
 */
class RelatorioTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    private function periodo(): array
    {
        return [
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString(),
        ];
    }

    private function criarPaciente(string $nome = 'Paciente Relatório'): Paciente
    {
        return Paciente::create(['nome' => $nome, 'status' => 'ativo']);
    }

    private function criarConsulta(
        Paciente $paciente,
        AppointmentStatus $status,
        Carbon $inicio,
        string $servico = 'Pilates',
    ): Appointment {
        return Appointment::create([
            'paciente_id' => $paciente->id,
            'profissional_id' => Professional::firstOrCreate(['nome' => 'Dra. Daiane'], ['is_active' => true])->id,
            'appointment_status_id' => $status->id,
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addHour(),
            'titulo' => 'Sessão de '.$servico,
            'servico' => $servico,
        ]);
    }

    public function test_receitas_agrupam_por_servico_forma_e_mes(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('relatorios.view');

        $paciente = $this->criarPaciente();
        $dinheiro = PaymentMethod::create(['name' => 'dinheiro', 'label' => 'Dinheiro']);
        $pix = PaymentMethod::create(['name' => 'pix', 'label' => 'PIX']);

        Payment::create([
            'paciente_id' => $paciente->id,
            'payment_method_id' => $dinheiro->id,
            'data' => now()->startOfMonth()->toDateString(),
            'valor' => 300,
            'servico' => 'Pilates',
        ]);

        Payment::create([
            'paciente_id' => $paciente->id,
            'payment_method_id' => $pix->id,
            'data' => now()->startOfMonth()->addDay()->toDateString(),
            'valor' => 200,
            'servico' => 'Depilação a laser',
        ]);

        [$de, $ate] = $this->periodo();

        $this->actingAs($user)
            ->get(route('relatorios.receitas', ['de' => $de, 'ate' => $ate]))
            ->assertOk()
            ->assertSee('Receitas do período')
            ->assertSee('Pilates')
            ->assertSee('Depilação a laser')
            ->assertSee('Dinheiro')
            ->assertSee('PIX')
            ->assertSee('R$ 500,00');
    }

    /**
     * O último dia do período precisa incluir lançamentos com hora. No SQLite
     * a coluna `data` guarda data e hora, e comparar só com `Y-m-d` deixava o
     * pagamento das 18h30 de fora do relatório.
     */
    public function test_receitas_incluem_pagamento_com_hora_no_ultimo_dia_do_periodo(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('relatorios.view');

        $paciente = $this->criarPaciente('Paciente Hora');
        $pix = PaymentMethod::create(['name' => 'pix', 'label' => 'PIX']);

        Payment::create([
            'paciente_id' => $paciente->id,
            'payment_method_id' => $pix->id,
            'data' => now()->endOfMonth()->setTime(18, 30),
            'valor' => 90,
            'servico' => 'Massagem',
        ]);

        [$de, $ate] = $this->periodo();

        $this->actingAs($user)
            ->get(route('relatorios.receitas', ['de' => $de, 'ate' => $ate]))
            ->assertOk()
            ->assertSee('Massagem')
            ->assertSee('R$ 90,00');

        $csv = $this->actingAs($user)
            ->get(route('relatorios.exportar', ['tipo' => 'receitas', 'de' => $de, 'ate' => $ate]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Massagem', $csv);
        $this->assertStringContainsString('90,00', $csv);
    }

    public function test_presenca_e_faltas_calculam_taxa_do_periodo(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('relatorios.view');

        $paciente = $this->criarPaciente();
        $atendido = AppointmentStatus::create(['name' => 'atendido', 'label' => 'Atendido', 'cor' => '#198754']);
        $faltou = AppointmentStatus::create(['name' => 'faltou', 'label' => 'Faltou', 'cor' => '#dc3545']);
        $agendado = AppointmentStatus::create(['name' => 'agendado', 'label' => 'Agendado', 'cor' => '#0d6efd']);

        $primeiroDia = now()->startOfMonth()->setTime(9, 0);
        $this->criarConsulta($paciente, $atendido, $primeiroDia);
        $this->criarConsulta($paciente, $atendido, $primeiroDia->copy()->addDay());
        $this->criarConsulta($paciente, $faltou, $primeiroDia->copy()->addDays(2));
        $this->criarConsulta($paciente, $agendado, $primeiroDia->copy()->addDays(3));

        [$de, $ate] = $this->periodo();

        $this->actingAs($user)
            ->get(route('relatorios.presenca-faltas', ['de' => $de, 'ate' => $ate]))
            ->assertOk()
            ->assertSee('Presença e faltas')
            ->assertSee('Presenças')
            ->assertSee('Taxa de faltas')
            ->assertSee('25,0%')
            ->assertSee('2')
            ->assertSee('1 aguardando');
    }

    public function test_pacotes_mostram_contratado_usado_e_restante(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('relatorios.view');

        $paciente = $this->criarPaciente('Paciente Pacote');
        $profissional = Professional::create(['nome' => 'Dra. Daiane', 'is_active' => true]);

        $pacote = PacoteSessao::create([
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'nome' => 'Pacote Pilates 10 sessões',
            'servico' => 'Pilates',
            'total_sessoes' => 10,
            'sessoes_usadas' => 3,
            'valor_sessao' => 120,
            'valor_total' => 1200,
        ]);

        ConsumoSessao::create([
            'pacote_id' => $pacote->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'data' => now()->startOfMonth()->toDateString(),
            'valor' => 120,
        ]);

        [$de, $ate] = $this->periodo();

        $this->actingAs($user)
            ->get(route('relatorios.pacotes', ['de' => $de, 'ate' => $ate]))
            ->assertOk()
            ->assertSee('Pacotes e sessões')
            ->assertSee('Pacote Pilates 10 sessões')
            ->assertSee('Paciente Pacote')
            ->assertSee('7')
            ->assertSee('10');
    }

    public function test_periodo_invertido_e_normalizado(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('relatorios.view');

        $paciente = $this->criarPaciente('Paciente Período Invertido');
        $pix = PaymentMethod::create(['name' => 'pix', 'label' => 'PIX']);

        Payment::create([
            'paciente_id' => $paciente->id,
            'payment_method_id' => $pix->id,
            'data' => now()->startOfMonth()->toDateString(),
            'valor' => 120,
            'servico' => 'Massagem',
        ]);

        $this->actingAs($user)
            ->get(route('relatorios.receitas', [
                'de' => now()->endOfMonth()->toDateString(),
                'ate' => now()->startOfMonth()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Paciente Período Invertido')
            ->assertSee('R$ 120,00');
    }

    public function test_exportacao_csv_de_receitas_tem_bom_e_cabecalho(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('relatorios.view');

        $paciente = $this->criarPaciente('Paciente CSV');
        $dinheiro = PaymentMethod::create(['name' => 'dinheiro', 'label' => 'Dinheiro']);

        Payment::create([
            'paciente_id' => $paciente->id,
            'payment_method_id' => $dinheiro->id,
            'data' => now()->startOfMonth()->toDateString(),
            'valor' => 300,
            'servico' => 'Pilates',
            'observacoes' => '=1+1',
        ]);

        [$de, $ate] = $this->periodo();

        $resposta = $this->actingAs($user)
            ->get(route('relatorios.exportar', ['tipo' => 'receitas', 'de' => $de, 'ate' => $ate]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $resposta->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Data;Paciente;Serviço;"Forma de pagamento";Valor;Observações', $csv);
        $this->assertStringContainsString('"Paciente CSV";Pilates;Dinheiro;300,00', $csv);
        $this->assertStringContainsString("'=1+1", $csv);
    }

    public function test_exportacao_csv_com_tipo_desconhecido_retorna_404(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('relatorios.view');

        $this->actingAs($user)
            ->get(route('relatorios.exportar', ['tipo' => 'inexistente']))
            ->assertNotFound();
    }

    public function test_usuario_sem_permissao_nao_acessa_relatorios(): void
    {
        [, $user] = $this->criarUsuarioSemPapel();

        $this->actingAs($user)
            ->get(route('relatorios.receitas'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('relatorios.exportar', ['tipo' => 'receitas']))
            ->assertForbidden();
    }
}
