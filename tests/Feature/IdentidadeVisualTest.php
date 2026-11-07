<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

/**
 * Identidade visual da clínica: logo e cores.
 *
 * A marca é da clínica, e a clínica a configura no próprio painel. O que a
 * clínica NÃO configura continua valendo o padrão — nenhuma cor é inventada em
 * nome dela, e a clínica que já está em uso não troca de cor sozinha.
 */
class IdentidadeVisualTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    public function test_a_clinica_configura_a_propria_marca(): void
    {
        [$clinica, $user] = $this->criarUsuarioComPermissao('admin.view', 'admin.update');

        $this->actingAs($user)
            ->put(route('admin.clinica.update'), [
                'legal_name' => 'Clínica de Fisioterapia',
                'primary_color' => '#123456',
                'secondary_color' => '#654321',
            ])
            ->assertRedirect();

        $clinica->refresh();

        $this->assertSame('#123456', $clinica->primary_color);
        $this->assertSame('#654321', $clinica->secondary_color);
    }

    public function test_a_cor_configurada_vai_para_o_layout(): void
    {
        [$clinica, $user] = $this->criarUsuarioComPermissao('admin.view', 'admin.update');

        $this->actingAs($user)
            ->put(route('admin.clinica.update'), [
                'primary_color' => '#123456',
                'secondary_color' => '#654321',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('--sco-accent: #123456', escape: false)
            ->assertSee('--sco-accent-2: #654321', escape: false);
    }

    public function test_cor_invalida_e_recusada(): void
    {
        [$clinica, $user] = $this->criarUsuarioComPermissao('admin.view', 'admin.update');

        $this->actingAs($user)
            ->put(route('admin.clinica.update'), ['primary_color' => 'azul'])
            ->assertSessionHasErrors('primary_color');

        $this->assertNotSame('azul', $clinica->refresh()->primary_color);
    }

    public function test_o_logo_sendado_e_guardado_e_aparece_no_menu(): void
    {
        Storage::fake('public');

        [$clinica, $user] = $this->criarUsuarioComPermissao('admin.view', 'admin.update');

        $this->actingAs($user)
            ->put(route('admin.clinica.update'), [
                'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            ])
            ->assertRedirect();

        $clinica->refresh();

        $this->assertNotNull($clinica->logo_path);
        $this->assertStringStartsWith("clinicas/{$clinica->id}/marca/", $clinica->logo_path);

        Storage::disk('public')->assertExists($clinica->logo_path);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($clinica->logo_path, escape: false);
    }

    public function test_o_logo_anterior_e_apagado_quando_a_clinica_troca(): void
    {
        Storage::fake('public');

        [$clinica, $user] = $this->criarUsuarioComPermissao('admin.view', 'admin.update');

        $this->actingAs($user)->put(route('admin.clinica.update'), [
            'logo' => UploadedFile::fake()->image('antigo.png', 200, 200),
        ]);

        $antigo = $clinica->refresh()->logo_path;

        $this->actingAs($user)->put(route('admin.clinica.update'), [
            'logo' => UploadedFile::fake()->image('novo.png', 200, 200),
        ]);

        $novo = $clinica->refresh()->logo_path;

        $this->assertNotSame($antigo, $novo);
        Storage::disk('public')->assertMissing($antigo);
        Storage::disk('public')->assertExists($novo);
    }

    public function test_remover_o_logo_apaga_o_arquivo(): void
    {
        Storage::fake('public');

        [$clinica, $user] = $this->criarUsuarioComPermissao('admin.view', 'admin.update');

        $this->actingAs($user)->put(route('admin.clinica.update'), [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ]);

        $caminho = $clinica->refresh()->logo_path;

        $this->actingAs($user)->put(route('admin.clinica.update'), [
            'remover_logo' => '1',
        ])->assertRedirect();

        $this->assertNull($clinica->refresh()->logo_path);
        Storage::disk('public')->assertMissing($caminho);
    }

    public function test_quem_nao_e_admin_da_clinica_nao_mexe_na_marca(): void
    {
        [, $user] = $this->criarUsuarioSemPapel();

        $this->actingAs($user)
            ->put(route('admin.clinica.update'), ['primary_color' => '#000000'])
            ->assertForbidden();
    }

    public function test_a_marca_ficar_registrada_na_auditoria_da_clinica(): void
    {
        [$clinica, $user] = $this->criarUsuarioComPermissao('admin.view', 'admin.update');

        $this->actingAs($user)->put(route('admin.clinica.update'), [
            'primary_color' => '#123456',
        ]);

        $this->assertTenantDatabaseHas('audit_logs', [
            'acao' => 'atualizar_marca',
            'tabela' => 'clinic',
        ]);
    }
}
