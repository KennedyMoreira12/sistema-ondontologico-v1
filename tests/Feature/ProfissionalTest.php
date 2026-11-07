<?php

namespace Tests\Feature;

use App\Models\Professional;
use App\Models\Specialty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

class ProfissionalTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    public function test_usuario_sem_role_nao_acessa_profissionais(): void
    {
        [, $user] = $this->criarUsuarioSemPapel();

        $this->actingAs($user)
            ->get(route('profissionais.index'))
            ->assertForbidden();
    }

    public function test_index_lista_profissionais_com_especialidades(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('profissionais.view');

        $especialidade = Specialty::create(['name' => 'Implantodontia']);
        $profissional = Professional::create(['nome' => 'Dra. Ana']);
        $profissional->specialties()->attach($especialidade->id);

        $this->actingAs($user)
            ->get(route('profissionais.index'))
            ->assertOk()
            ->assertSee('Dra. Ana')
            ->assertSee('Implantodontia');
    }

    public function test_cria_profissional_com_especialidades(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('profissionais.create');

        $especialidade = Specialty::create(['name' => 'Ortodontia']);

        $this->actingAs($user)
            ->post(route('profissionais.store'), [
                'nome' => 'Dr. Carlos',
                'tipo' => 'dentista',
                'cro' => 'SP-12345',
                'specialties' => [$especialidade->id],
                'is_active' => '1',
            ])
            ->assertRedirect(route('profissionais.index'));

        $this->assertTenantDatabaseHas('professionals', ['nome' => 'Dr. Carlos']);
        $this->assertTenantDatabaseHas('professional_specialty', [
            'specialty_id' => $especialidade->id,
        ]);
    }

    public function test_atualiza_profissional(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('profissionais.update');

        $profissional = Professional::create(['nome' => 'Dr. Carlos']);

        $this->actingAs($user)
            ->put(route('profissionais.update', $profissional), [
                'nome' => 'Dr. Carlos Silva',
                'tipo' => 'auxiliar',
                'is_active' => '0',
            ])
            ->assertRedirect(route('profissionais.index'));

        $this->assertTenantDatabaseHas('professionals', [
            'id' => $profissional->id,
            'nome' => 'Dr. Carlos Silva',
        ]);
    }

    public function test_cria_fisioterapeuta_com_crefito(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('profissionais.create');

        $this->actingAs($user)
            ->post(route('profissionais.store'), [
                'nome' => 'Dra. Marina Alves',
                'tipo' => 'fisioterapeuta',
                'registro_profissional' => 'F-123456',
                'conselho_regional' => 'CREFITO-1',
                'is_active' => '1',
            ])
            ->assertRedirect(route('profissionais.index'));

        $this->assertTenantDatabaseHas('professionals', [
            'nome' => 'Dra. Marina Alves',
            'tipo' => 'fisioterapeuta',
            'registro_profissional' => 'F-123456',
            'conselho_regional' => 'CREFITO-1',
        ]);
    }

    public function test_fisioterapeuta_sem_registro_e_recusado(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('profissionais.create');

        $this->actingAs($user)
            ->post(route('profissionais.store'), [
                'nome' => 'Dra. Marina Alves',
                'tipo' => 'fisioterapeuta',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('registro_profissional');

        $this->assertTenantDatabaseMissing('professionals', ['nome' => 'Dra. Marina Alves']);
    }

    public function test_cro_odontologico_continua_valendo(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('profissionais.create');

        $this->actingAs($user)
            ->post(route('profissionais.store'), [
                'nome' => 'Dr. Carlos',
                'tipo' => 'dentista',
                'cro' => 'SP-12345',
                'is_active' => '1',
            ])
            ->assertRedirect(route('profissionais.index'));

        $this->assertTenantDatabaseHas('professionals', [
            'nome' => 'Dr. Carlos',
            'cro' => 'SP-12345',
        ]);
    }

    public function test_busca_encontra_pelo_registro_profissional(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('profissionais.view');

        Professional::create([
            'nome' => 'Dra. Marina Alves',
            'tipo' => 'fisioterapeuta',
            'registro_profissional' => 'F-123456',
        ]);

        $this->actingAs($user)
            ->get(route('profissionais.index', ['busca' => 'F-123456']))
            ->assertOk()
            ->assertSee('Dra. Marina Alves');
    }
}
