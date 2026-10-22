<?php

namespace Tests\Feature;

use App\Models\Avaliacao;
use App\Models\ConsumoSessao;
use App\Models\EvolucaoSessao;
use App\Models\Paciente;
use App\Models\PacoteSessao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\Concerns\PreparaClinicaFisioterapia;
use Tests\TestCase;

/**
 * Pacotes de sessões de fisioterapia.
 *
 * O saldo do pacote é dinheiro já recebido: a regra é dura. Nenhuma sessão é
 * consumida sem saldo, o saldo nunca fica negativo, e nunca passa do total
 * contratado — mesmo com duas atendentes marcando sessão ao mesmo tempo.
 */
class PacoteSessaoTest extends TestCase
{
    use CreatesTenants, PreparaClinicaFisioterapia, RefreshDatabase;

    public function test_cria_pacote_de_sessoes(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('pacotes.view', 'pacotes.create');
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        $this->actingAs($user)
            ->post(route('pacientes.pacotes.store', $paciente), [
                'nome' => 'Pacote 10 sessões',
                'total_sessoes' => 10,
                'valor_sessao' => 120,
                'valor_total' => 1200,
            ])
            ->assertRedirect(route('pacientes.pacotes.index', $paciente));

        $this->assertTenantDatabaseHas('pacotes_sessao', [
            'paciente_id' => $paciente->id,
            'nome' => 'Pacote 10 sessões',
            'total_sessoes' => 10,
            'sessoes_usadas' => 0,
        ]);
    }

    public function test_consumo_de_sessao_debita_o_saldo(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('pacotes.view', 'pacotes.update');
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        $pacote = PacoteSessao::create([
            'paciente_id' => $paciente->id,
            'nome' => 'Pacote 10 sessões',
            'total_sessoes' => 10,
            'valor_sessao' => 120,
            'valor_total' => 1200,
        ]);

        $this->actingAs($user)
            ->post(route('pacientes.pacotes.consumir', [$paciente, $pacote]), [
                'data' => '2026-10-20',
            ])
            ->assertRedirect();

        $this->assertTenantDatabaseHas('pacotes_sessao', [
            'id' => $pacote->id,
            'sessoes_usadas' => 1,
        ]);

        $this->assertTenantDatabaseHas('consumo_sessao', [
            'pacote_id' => $pacote->id,
            'paciente_id' => $paciente->id,
        ]);
    }

    public function test_saldo_nunca_fica_negativo(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('pacotes.view', 'pacotes.update');
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        $pacote = PacoteSessao::create([
            'paciente_id' => $paciente->id,
            'nome' => 'Pacote 2 sessões',
            'total_sessoes' => 2,
        ]);

        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($user)
                ->post(route('pacientes.pacotes.consumir', [$paciente, $pacote]))
                ->assertRedirect();
        }

        // A terceira sessão não existe: o pacote tem duas.
        $this->actingAs($user)
            ->post(route('pacientes.pacotes.consumir', [$paciente, $pacote]))
            ->assertSessionHas('error');

        $this->assertTenantDatabaseHas('pacotes_sessao', [
            'id' => $pacote->id,
            'sessoes_usadas' => 2,
        ]);

        $this->assertTenantDatabaseCount('consumo_sessao', 2);
    }

    public function test_consumo_recusado_nao_deixa_consumo_no_extrato(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('pacotes.view', 'pacotes.update');
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        $pacote = PacoteSessao::create([
            'paciente_id' => $paciente->id,
            'nome' => 'Pacote 1 sessão',
            'total_sessoes' => 1,
        ]);

        $this->actingAs($user)->post(route('pacientes.pacotes.consumir', [$paciente, $pacote]));

        $this->actingAs($user)
            ->post(route('pacientes.pacotes.consumir', [$paciente, $pacote]))
            ->assertSessionHas('error');

        $this->assertTenantDatabaseCount('consumo_sessao', 1);
        $this->assertTenantDatabaseHas('pacotes_sessao', [
            'id' => $pacote->id,
            'sessoes_usadas' => 1,
        ]);
    }

    public function test_pacote_cancelado_nao_consome(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('pacotes.view', 'pacotes.update');
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        $pacote = PacoteSessao::create([
            'paciente_id' => $paciente->id,
            'nome' => 'Pacote cancelado',
            'total_sessoes' => 10,
            'status' => PacoteSessao::CANCELADO,
        ]);

        $this->actingAs($user)
            ->post(route('pacientes.pacotes.consumir', [$paciente, $pacote]))
            ->assertSessionHas('error');

        $this->assertTenantDatabaseHas('pacotes_sessao', [
            'id' => $pacote->id,
            'sessoes_usadas' => 0,
        ]);

        $this->assertTenantDatabaseCount('consumo_sessao', 0);
    }

    public function test_pacote_com_saldo_zerado_e_concluido(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('pacotes.view', 'pacotes.update');
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        $pacote = PacoteSessao::create([
            'paciente_id' => $paciente->id,
            'nome' => 'Pacote 1 sessão',
            'total_sessoes' => 1,
        ]);

        $this->actingAs($user)->post(route('pacientes.pacotes.consumir', [$paciente, $pacote]));

        $this->assertTenantDatabaseHas('pacotes_sessao', [
            'id' => $pacote->id,
            'sessoes_usadas' => 1,
            'status' => PacoteSessao::CONCLUIDO,
        ]);
    }

    public function test_cancelar_pacote_preserva_o_extrato(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('pacotes.view', 'pacotes.update');
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        $pacote = PacoteSessao::create([
            'paciente_id' => $paciente->id,
            'nome' => 'Pacote 10 sessões',
            'total_sessoes' => 10,
        ]);

        $this->actingAs($user)->post(route('pacientes.pacotes.consumir', [$paciente, $pacote]));

        $this->actingAs($user)
            ->delete(route('pacientes.pacotes.destroy', [$paciente, $pacote]))
            ->assertRedirect();

        $this->assertTenantDatabaseHas('pacotes_sessao', [
            'id' => $pacote->id,
            'status' => PacoteSessao::CANCELADO,
            'sessoes_usadas' => 1,
        ]);

        $this->assertTenantDatabaseCount('consumo_sessao', 1);
    }

    public function test_pacote_de_um_paciente_nao_abre_pelo_outro(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('pacotes.view', 'pacotes.update');

        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);
        $outro = Paciente::create(['nome' => 'Outro Paciente']);

        $pacote = PacoteSessao::create([
            'paciente_id' => $paciente->id,
            'nome' => 'Pacote 10 sessões',
            'total_sessoes' => 10,
        ]);

        $this->actingAs($user)
            ->post(route('pacientes.pacotes.consumir', [$outro, $pacote]))
            ->assertNotFound();

        $this->assertTenantDatabaseHas('pacotes_sessao', [
            'id' => $pacote->id,
            'sessoes_usadas' => 0,
        ]);
    }

    public function test_clinica_odontologica_nao_abre_os_pacotes(): void
    {
        $clinica = $this->clinicaOdontologica();
        $user = $this->usuarioComPermissao($clinica, 'pacotes.view', 'pacotes.create');

        $paciente = Paciente::create(['nome' => 'Paciente Odontologia']);

        $this->actingAs($user)
            ->get(route('pacientes.pacotes.index', $paciente))
            ->assertForbidden();
    }

    /**
     * Regressão: o extrato de sessões do pacote.
     *
     * A coluna chama `pacote_id`. Sem a chave explícita, `hasMany` deduz
     * `pacote_sessao_id` a partir do nome do model e a tela do pacote vira
     * 500 com "no such column: consumo_sessao.pacote_sessao_id".
     */
    public function test_detalhe_do_pacote_lista_os_consumos_e_abre_a_tela(): void
    {
        ['user' => $user] = $this->clinicaFisioterapia('pacotes.view');
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        $pacote = PacoteSessao::create([
            'paciente_id' => $paciente->id,
            'nome' => 'Pacote 10 sessoes',
            'total_sessoes' => 10,
            'sessoes_usadas' => 1,
            'validade_em' => '2027-01-31',
            'status' => PacoteSessao::ATIVO,
        ]);

        ConsumoSessao::create([
            'pacote_id' => $pacote->id,
            'paciente_id' => $paciente->id,
            'data' => '2026-10-19',
        ]);

        $this->assertSame('pacote_id', $pacote->consumos()->getForeignKeyName());
        $this->assertCount(1, $pacote->fresh()->consumos);

        $this->actingAs($user)
            ->get(route('pacientes.pacotes.index', $paciente))
            ->assertOk()
            ->assertSee('31/01/2027');

        $this->actingAs($user)
            ->get(route('pacientes.pacotes.show', [$paciente, $pacote]))
            ->assertOk()
            ->assertSee('19/10/2026');
    }

    /**
     * Regressão: o consumo aponta para a evolução da sessão pela coluna
     * `evolucao_sessao_id` — não `evolucao_id`, que é o nome que o Eloquent
     * deduziria do método e que não existe na tabela.
     */
    public function test_consumo_vinculado_a_evolucao_resolve_a_coluna_certa(): void
    {
        $this->clinicaFisioterapia('pacotes.view');
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        $evolucao = EvolucaoSessao::create([
            'paciente_id' => $paciente->id,
            'data' => '2026-10-19',
            'conteudo' => 'Sessao realizada.',
            'status' => EvolucaoSessao::FINALIZADA,
            'finalizada_em' => '2026-10-19 10:30:00',
        ]);

        $pacote = PacoteSessao::create([
            'paciente_id' => $paciente->id,
            'nome' => 'Pacote 10 sessoes',
            'total_sessoes' => 10,
            'status' => PacoteSessao::ATIVO,
        ]);

        $consumo = ConsumoSessao::create([
            'pacote_id' => $pacote->id,
            'paciente_id' => $paciente->id,
            'evolucao_sessao_id' => $evolucao->id,
            'data' => '2026-10-19',
        ]);

        $this->assertSame(
            'evolucao_sessao_id',
            $consumo->evolucao()->getForeignKeyName()
        );
        $this->assertTrue($consumo->evolucao()->is($evolucao));
    }

    public function test_dados_de_fisioterapia_nao_vazam_para_outra_clinica(): void
    {
        $this->clinicaFisioterapia();

        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        PacoteSessao::create([
            'paciente_id' => $paciente->id,
            'nome' => 'Pacote 10 sessões',
            'total_sessoes' => 10,
        ]);

        $outra = $this->criarClinica('Outra Clínica');

        $this->conectar($outra['clinic']);

        $this->assertSame(0, PacoteSessao::count());
        $this->assertSame(0, ConsumoSessao::count());
        $this->assertSame(0, Avaliacao::count());
        $this->assertSame(0, EvolucaoSessao::count());
    }
}
