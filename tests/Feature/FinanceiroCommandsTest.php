<?php

namespace Tests\Feature;

use App\Models\Paciente;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

/**
 * Comandos de diagnóstico e vínculo dos pagamentos importados.
 *
 * O fluxo é sempre assistido: o padrão é dry-run e só `--apply` grava. Sem
 * essa garantia, um comando "de manutenção" poderia reescrever milhares de
 * lançamentos da planilha sem ninguém pedir.
 */
class FinanceiroCommandsTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    public function test_vinculo_de_pagamentos_e_dry_run_por_padrao(): void
    {
        $clinica = $this->criarClinicaTeste('Clínica Comandos');

        $paciente = Paciente::create(['nome' => 'Gisele Alves', 'status' => 'ativo']);

        $pagamento = Payment::create([
            'data' => '2026-10-10',
            'valor' => 300,
            'observacoes' => 'Paciente: GISELE ALVES',
        ]);

        $this->artisan('financeiro:vincular-pagamentos', ['--clinic' => $clinica->id])
            ->assertSuccessful();

        $this->assertNull($pagamento->refresh()->paciente_id, 'O dry-run não pode gravar vínculo.');

        $this->artisan('financeiro:vincular-pagamentos', [
            '--clinic' => $clinica->id,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame($paciente->id, $pagamento->refresh()->paciente_id);
    }

    public function test_diagnostico_de_pagamentos_e_somente_leitura(): void
    {
        $clinica = $this->criarClinicaTeste('Clínica Diagnóstico');

        Payment::create([
            'data' => '2026-10-10',
            'valor' => 180,
            'observacoes' => 'Importado da planilha: GISELE ALVES',
        ]);

        $this->artisan('financeiro:diagnosticar-pagamentos', ['--clinic' => $clinica->id])
            ->assertSuccessful();

        $this->assertTenantDatabaseCount('payments', 1);
        $this->assertTenantDatabaseHas('payments', [
            'valor' => 180,
            'paciente_id' => null,
        ]);
    }
}
