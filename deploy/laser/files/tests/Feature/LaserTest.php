<?php

namespace Tests\Feature;

use App\Models\AplicacaoLaser;
use App\Models\Central\Clinic;
use App\Models\Central\User;
use App\Models\Paciente;
use App\Models\PacoteLaser;
use App\Models\RegiaoLaser;
use App\Services\Tenant\VerticalManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\Concerns\PreparaClinicaFisioterapia;
use Tests\TestCase;

/**
 * Regiões a Laser: catálogo de regiões, pacotes-modelo e histórico.
 *
 * O módulo é exclusivo da vertical `laser`. O que estes testes protegem:
 *
 * - a clínica odontológica não abre o módulo nem digitando a URL;
 * - o histórico não some quando uma região ou pacote é excluído (a exclusão é
 *   recusada enquanto houver vínculo);
 * - o número da sessão é calculado no servidor, por paciente e região, e não
 *   vem do formulário;
 * - o pacote escolhido precisa ser da mesma região da aplicação;
 * - o histórico aparece no prontuário e não vaza para outra clínica.
 */
class LaserTest extends TestCase
{
    use CreatesTenants, PreparaClinicaFisioterapia, RefreshDatabase;

    /**
     * @return array{clinica: array{clinic: Clinic, user: ?User, token: string}, user: User}
     */
    private function clinicaLaser(string ...$permissoes): array
    {
        $clinica = $this->criarClinica('Clínica de Laser');
        $clinica['clinic']->update(['vertical' => VerticalManager::LASER]);

        $user = $this->usuarioComPermissao($clinica, ...$permissoes);

        return ['clinica' => $clinica, 'user' => $user];
    }

    private function regiao(string $nome = 'Axilas'): RegiaoLaser
    {
        return RegiaoLaser::create(['nome' => $nome, 'is_active' => true]);
    }

    public function test_cria_regiao_de_laser(): void
    {
        ['user' => $user] = $this->clinicaLaser('regioes_laser.view', 'regioes_laser.create');

        $this->actingAs($user)
            ->post(route('regioes_laser.store'), [
                'nome' => 'Axilas',
                'descricao' => 'Região das axilas',
                'ordem' => 1,
                'is_active' => 1,
            ])
            ->assertRedirect(route('regioes_laser.index'));

        $this->assertTenantDatabaseHas('laser_regioes', [
            'nome' => 'Axilas',
            'ordem' => 1,
            'is_active' => true,
        ]);
    }

    public function test_atualiza_regiao_de_laser(): void
    {
        ['user' => $user] = $this->clinicaLaser('regioes_laser.view', 'regioes_laser.update');
        $regiao = $this->regiao();

        $this->actingAs($user)
            ->put(route('regioes_laser.update', $regiao), [
                'nome' => 'Axilas e braços',
                'ordem' => 2,
                'is_active' => 1,
            ])
            ->assertRedirect(route('regioes_laser.index'));

        $this->assertTenantDatabaseHas('laser_regioes', [
            'id' => $regiao->id,
            'nome' => 'Axilas e braços',
            'ordem' => 2,
        ]);
    }

    public function test_regiao_com_pacote_nao_pode_ser_excluida(): void
    {
        ['user' => $user] = $this->clinicaLaser('regioes_laser.view', 'regioes_laser.delete');
        $regiao = $this->regiao();

        PacoteLaser::create([
            'regiao_id' => $regiao->id,
            'nome' => 'Axilas — 10 sessões',
            'total_sessoes' => 10,
        ]);

        $this->actingAs($user)
            ->delete(route('regioes_laser.destroy', $regiao))
            ->assertSessionHas('error');

        $this->assertTenantDatabaseHas('laser_regioes', ['id' => $regiao->id]);
    }

    public function test_exclui_regiao_sem_vinculos(): void
    {
        ['user' => $user] = $this->clinicaLaser('regioes_laser.view', 'regioes_laser.delete');
        $regiao = $this->regiao();

        $this->actingAs($user)
            ->delete(route('regioes_laser.destroy', $regiao))
            ->assertRedirect(route('regioes_laser.index'));

        $this->assertTenantDatabaseMissing('laser_regioes', ['id' => $regiao->id]);
    }

    public function test_cria_pacote_de_laser(): void
    {
        ['user' => $user] = $this->clinicaLaser('regioes_laser.view', 'regioes_laser.create');
        $regiao = $this->regiao();

        $this->actingAs($user)
            ->post(route('regioes_laser.pacotes.store'), [
                'regiao_id' => $regiao->id,
                'nome' => 'Axilas — 10 sessões',
                'total_sessoes' => 10,
                'valor' => 900,
                'validade_dias' => 180,
                'is_active' => 1,
            ])
            ->assertRedirect(route('regioes_laser.pacotes.index'));

        $this->assertTenantDatabaseHas('laser_pacotes', [
            'regiao_id' => $regiao->id,
            'nome' => 'Axilas — 10 sessões',
            'total_sessoes' => 10,
            'validade_dias' => 180,
        ]);
    }

    public function test_pacote_com_aplicacoes_nao_pode_ser_excluido(): void
    {
        ['user' => $user] = $this->clinicaLaser('regioes_laser.view', 'regioes_laser.delete');
        $regiao = $this->regiao();
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        $pacote = PacoteLaser::create([
            'regiao_id' => $regiao->id,
            'nome' => 'Axilas — 10 sessões',
            'total_sessoes' => 10,
        ]);

        AplicacaoLaser::create([
            'paciente_id' => $paciente->id,
            'regiao_id' => $regiao->id,
            'pacote_id' => $pacote->id,
            'data' => '2026-11-10',
            'numero_sessao' => 1,
        ]);

        $this->actingAs($user)
            ->delete(route('regioes_laser.pacotes.destroy', $pacote))
            ->assertSessionHas('error');

        $this->assertTenantDatabaseHas('laser_pacotes', ['id' => $pacote->id]);
    }

    public function test_registra_aplicacao_com_numero_de_sessao_sequencial(): void
    {
        ['user' => $user] = $this->clinicaLaser('regioes_laser.view', 'regioes_laser.create');
        $regiao = $this->regiao();
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        foreach (['2026-11-10', '2026-11-17'] as $data) {
            $this->actingAs($user)
                ->post(route('regioes_laser.aplicacoes.store'), [
                    'paciente_id' => $paciente->id,
                    'regiao_id' => $regiao->id,
                    'data' => $data,
                ])
                ->assertRedirect(route('regioes_laser.aplicacoes.index', ['paciente' => $paciente->id]));
        }

        $this->reconectarTenantParaAssercao();

        $this->assertSame(
            [1, 2],
            AplicacaoLaser::query()->orderBy('id')->pluck('numero_sessao')->all(),
        );
    }

    public function test_numero_da_sessao_ignora_o_valor_do_formulario(): void
    {
        ['user' => $user] = $this->clinicaLaser('regioes_laser.view', 'regioes_laser.create');
        $regiao = $this->regiao();
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        $this->actingAs($user)
            ->post(route('regioes_laser.aplicacoes.store'), [
                'paciente_id' => $paciente->id,
                'regiao_id' => $regiao->id,
                'data' => '2026-11-10',
                'numero_sessao' => 99,
            ]);

        $this->assertTenantDatabaseHas('laser_aplicacoes', [
            'paciente_id' => $paciente->id,
            'numero_sessao' => 1,
        ]);
    }

    public function test_numero_da_sessao_reinicia_por_regiao(): void
    {
        ['user' => $user] = $this->clinicaLaser('regioes_laser.view', 'regioes_laser.create');
        $axilas = $this->regiao('Axilas');
        $pernas = $this->regiao('Pernas');
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        foreach ([$axilas, $pernas] as $regiao) {
            $this->actingAs($user)
                ->post(route('regioes_laser.aplicacoes.store'), [
                    'paciente_id' => $paciente->id,
                    'regiao_id' => $regiao->id,
                    'data' => '2026-11-10',
                ]);
        }

        $this->reconectarTenantParaAssercao();

        $this->assertSame(
            [1, 1],
            AplicacaoLaser::query()->orderBy('id')->pluck('numero_sessao')->all(),
        );
    }

    public function test_pacote_de_outra_regiao_e_recusado(): void
    {
        ['user' => $user] = $this->clinicaLaser('regioes_laser.view', 'regioes_laser.create');
        $axilas = $this->regiao('Axilas');
        $pernas = $this->regiao('Pernas');
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        $pacoteDePernas = PacoteLaser::create([
            'regiao_id' => $pernas->id,
            'nome' => 'Pernas — 10 sessões',
            'total_sessoes' => 10,
        ]);

        $this->actingAs($user)
            ->post(route('regioes_laser.aplicacoes.store'), [
                'paciente_id' => $paciente->id,
                'regiao_id' => $axilas->id,
                'pacote_id' => $pacoteDePernas->id,
                'data' => '2026-11-10',
            ])
            ->assertSessionHasErrors('pacote_id');

        $this->assertTenantDatabaseCount('laser_aplicacoes', 0);
    }

    public function test_historico_de_laser_aparece_no_prontuario(): void
    {
        ['user' => $user] = $this->clinicaLaser('prontuario.view', 'regioes_laser.view');
        $regiao = $this->regiao();
        $paciente = Paciente::create(['nome' => 'Joana Ribeiro']);

        AplicacaoLaser::create([
            'paciente_id' => $paciente->id,
            'regiao_id' => $regiao->id,
            'data' => '2026-11-10',
            'numero_sessao' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('pacientes.prontuario', $paciente))
            ->assertOk()
            ->assertSee('Histórico de laser')
            ->assertSee('Axilas')
            ->assertSee('Sessão nº 1');
    }

    public function test_prontuario_de_clinica_sem_laser_nao_mostra_historico(): void
    {
        $clinica = $this->clinicaOdontologica();
        $user = $this->usuarioComPermissao($clinica, 'prontuario.view', 'regioes_laser.view');
        $paciente = Paciente::create(['nome' => 'Paciente Odontologia']);

        $this->actingAs($user)
            ->get(route('pacientes.prontuario', $paciente))
            ->assertOk()
            ->assertDontSee('Histórico de laser');
    }

    public function test_modulo_de_laser_e_recusado_para_clinica_odontologica(): void
    {
        $clinica = $this->clinicaOdontologica();
        $user = $this->usuarioComPermissao($clinica, 'regioes_laser.view');

        $this->actingAs($user)
            ->get(route('regioes_laser.index'))
            ->assertForbidden();
    }

    public function test_usuario_sem_permissao_nao_abre_o_modulo(): void
    {
        ['user' => $user] = $this->clinicaLaser('dashboard.view');

        $this->actingAs($user)
            ->get(route('regioes_laser.index'))
            ->assertForbidden();
    }

    public function test_historico_filtra_por_paciente(): void
    {
        ['user' => $user] = $this->clinicaLaser('regioes_laser.view');
        $regiao = $this->regiao();

        $joana = Paciente::create(['nome' => 'Joana Ribeiro']);
        $marcos = Paciente::create(['nome' => 'Marcos Alves']);

        foreach ([$joana, $marcos] as $paciente) {
            AplicacaoLaser::create([
                'paciente_id' => $paciente->id,
                'regiao_id' => $regiao->id,
                'data' => '2026-11-10',
                'numero_sessao' => 1,
            ]);
        }

        $this->actingAs($user)
            ->get(route('regioes_laser.aplicacoes.index', ['paciente' => $joana->id]))
            ->assertOk()
            ->assertViewHas('aplicacoes', function ($aplicacoes) use ($joana): bool {
                return $aplicacoes->total() === 1
                    && $aplicacoes->first()->paciente_id === $joana->id;
            });
    }

    public function test_dados_de_laser_nao_vazam_para_outra_clinica(): void
    {
        $this->clinicaLaser('regioes_laser.view');
        $this->regiao();

        $outra = $this->criarClinica('Outra Clínica');
        $this->conectar($outra['clinic']);

        $this->assertSame(0, RegiaoLaser::count());
        $this->assertSame(0, PacoteLaser::count());
        $this->assertSame(0, AplicacaoLaser::count());
    }
}
