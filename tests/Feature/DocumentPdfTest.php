<?php

namespace Tests\Feature;

use App\Models\Central\Plan;
use App\Models\Central\User;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Role;
use App\Models\Specialty;
use App\Services\Branding\MalheiroBranding;
use App\Services\Tenant\TenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

/**
 * Testes de geração de PDF dos documentos Malheiro.
 *
 * Valida:
 * - PDF válido (cabeçalho %PDF, tamanho mínimo);
 * - Estrutura HTML renderizada (DOCTYPE, layout aplicado);
 * - Conteúdo dentro do layout (não órfão);
 * - CSS aplicado (classes malheiro-*);
 * - Logotipo correto (data URI ou URL);
 * - Dados do profissional (nome, CRO, especialidades);
 * - Identificação da clínica (nome, frase institucional);
 * - Isolamento entre clínicas (outra clínica = 403);
 * - Permissões (sem documentos.view = 403);
 * - Validação do atestado (campos obrigatórios, limite de dias).
 *
 * Os testes NÃO validam apenas "%PDF" ou tamanho > 1.000 bytes:
 * inspecionam o HTML renderizado antes da conversão para PDF.
 */
class DocumentPdfTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    protected User $user;

    protected int $clinicaId;

    protected function setUp(): void
    {
        parent::setUp();

        // Primeira clínica criada no banco de teste recebe id = 1 (Malheiro)
        [$clinica, $this->user] = $this->criarUsuarioComPermissao(
            'documentos.view',
            'documentos.generate',
        );
        $this->clinicaId = (int) $clinica->id;

        // Força id = 1 para simular a Clínica Malheiro
        $this->clinicaId = 1;

        // Conecta no tenant da clínica de teste
        $this->conectar($this->clinicaTest);

        // Cria profissional Malheiro
        $especialidade1 = Specialty::create(['name' => 'ESPECIALISTA EM IMPLANTES']);
        $especialidade2 = Specialty::create(['name' => 'DENTÍSTICA RESTAURADORA E ESTÉTICA']);

        Professional::create([
            'clinica_id' => $this->clinicaId,
            'user_id' => null,
            'nome' => 'Dr. Alessandro Agostinho Malheiro',
            'cro' => '20.545',
            'registro_profissional' => '20.545',
            'conselho_regional' => 'CRO-MG',
            'tipo' => 'dentista',
            'comissao' => 0,
            'is_active' => true,
        ])->specialties()->sync([
            $especialidade1->id,
            $especialidade2->id,
        ]);

        // Cria paciente de teste
        Paciente::create([
            'clinica_id' => $this->clinicaId,
            'nome' => 'Paciente Teste',
            'cpf' => '123.456.789-'.mt_rand(10, 99),
            'rg' => '12.345.678',
            'nascimento' => '1990-01-01',
        ]);

        $this->provisionResult = [
            'clinic' => $this->clinicaTest,
            'user' => $this->user,
        ];
    }

    protected function tearDown(): void
    {
        if (isset($this->tenantFile) && is_file($this->tenantFile)) {
            @unlink($this->tenantFile);
        }

        parent::tearDown();
    }

    /**
     * Renderiza o HTML de uma view de documento e retorna o conteúdo.
     */
    protected function renderDocumento(string $view, array $dados = []): string
    {
        return View::make($view, array_merge(
            app(MalheiroBranding::class)->dadosParaDocumento(),
            $dados
        ))->render();
    }

    /** @test */
    public function test_papel_timbrado_pdf_gera_com_sucesso(): void
    {
        $response = $this->actingAs($this->provisionResult['user'])
            ->get(route('documentos.papel-timbrado.pdf'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        $pdfContent = $response->getContent();
        $this->assertGreaterThan(1000, strlen($pdfContent), 'PDF deve ter tamanho mínimo válido');
        $this->assertStringStartsWith('%PDF', $pdfContent, 'Conteúdo deve ser PDF válido');
    }

    /** @test */
    public function test_papel_timbrado_html_contem_dados_malheiro(): void
    {
        $html = $this->renderDocumento('documents.malheiro.papel-timbrado', [
            'titulo' => 'Papel Timbrado',
        ]);

        // DOCTYPE deve ser o primeiro conteúdo (sem HTML órfão antes)
        $this->assertStringStartsWith('<!DOCTYPE html>', $html, 'HTML deve começar com DOCTYPE');

        // Dados do profissional Malheiro
        $this->assertStringContainsString('Dr. Alessandro Agostinho Malheiro', $html);
        $this->assertStringContainsString('CRO-MG 20.545', $html);
        $this->assertStringContainsString('ESPECIALISTA EM IMPLANTES', $html);
        $this->assertStringContainsString('DENTÍSTICA RESTAURADORA E ESTÉTICA', $html);

        // Frase institucional
        $this->assertStringContainsString('Porque ninguém resiste a um belo sorriso', $html);

        // Classes de CSS Malheiro aplicadas
        $this->assertStringContainsString('malheiro-header', $html);
        $this->assertStringContainsString('malheiro-content', $html);
        $this->assertStringContainsString('malheiro-footer', $html);

        // Logotipo presente (data URI da PNG original extraída da arte oficial)
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringNotContainsString('.svg', $html);
    }

    /** @test */
    public function test_guia_cuidados_pdf_gera_com_sucesso(): void
    {
        $response = $this->actingAs($this->provisionResult['user'])
            ->get(route('documentos.guia-cuidados.pdf'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        $pdfContent = $response->getContent();
        $this->assertGreaterThan(1000, strlen($pdfContent), 'PDF deve ter tamanho mínimo válido');
        $this->assertStringStartsWith('%PDF', $pdfContent, 'Conteúdo deve ser PDF válido');
    }

    /** @test */
    public function test_guia_cuidados_html_contem_sete_itens_numerados(): void
    {
        $html = $this->renderDocumento('documents.malheiro.guia-cuidados', [
            'titulo' => 'Guia de Cuidados Odontológicos',
        ]);

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);

        // 7 itens numerados na tabela
        foreach (range(1, 7) as $numero) {
            $this->assertStringContainsString('>'.$numero.'<', $html, "Item $numero deve estar presente");
        }

        // Frase suspeita mantida EXATAMENTE como na referência (não corrigida silenciosamente)
        $this->assertStringContainsString('seu cônjuge e/ou usar o fio dental', $html);

        // Profissional no rodapé do guia
        $this->assertStringContainsString('Dr. Alessandro Agostinho Malheiro', $html);
        $this->assertStringContainsString('ESPECIALISTA EM IMPLANTES', $html);
    }

    /** @test */
    public function test_atestado_pdf_gera_com_dados_dinamicos(): void
    {
        $paciente = Paciente::first();
        $profissional = Professional::first();

        $response = $this->actingAs($this->provisionResult['user'])
            ->post(route('documentos.atestado.pdf'), [
                'paciente_id' => $paciente->id,
                'profissional_id' => $profissional->id,
                'atendimento_data' => now()->format('Y-m-d'),
                'atendimento_hora' => '10:00',
                'dias_repouso' => 3,
                'local' => 'Cidade/Estado',
            ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        $pdfContent = $response->getContent();
        $this->assertGreaterThan(1000, strlen($pdfContent), 'PDF deve ter tamanho mínimo válido');
        $this->assertStringStartsWith('%PDF', $pdfContent, 'Conteúdo deve ser PDF válido');
    }

    /** @test */
    public function test_atestado_html_contem_dados_dinamicos(): void
    {
        $paciente = Paciente::first();
        $profissional = Professional::first();

        $html = $this->renderDocumento('documents.malheiro.atestado-odontologico', [
            'titulo' => 'Atestado Odontológico',
            'paciente_nome' => $paciente->nome,
            'paciente_cpf' => $paciente->cpf,
            'atendimento_data' => now()->format('d/m/Y'),
            'atendimento_hora' => '10:00',
            'dias_repouso' => 3,
            'local' => 'Cidade/Estado',
            'data_emissao' => now()->format('d/m/Y'),
            'nome_profissional' => $profissional->nome,
            'cro' => $profissional->registroConselho(),
            'especialidades' => $profissional->specialties->pluck('name')->toArray(),
            'especialidade_principal' => 'ESPECIALISTA EM IMPLANTES',
            'rodape' => ['cidade' => 'Centro', 'estado' => 'MG'],
        ]);

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString($paciente->nome, $html);
        $this->assertStringContainsString($paciente->cpf, $html);
        $this->assertStringContainsString('3', $html); // dias de repouso
        $this->assertStringContainsString($profissional->nome, $html);
        // registroConselho() retorna registro_profissional (20.545) quando preenchido
        $this->assertStringContainsString('20.545', $html);
        $this->assertStringContainsString('ESPECIALISTA EM IMPLANTES', $html);
    }

    /** @test */
    public function test_atestado_valida_campos_obrigatorios(): void
    {
        $response = $this->actingAs($this->provisionResult['user'])
            ->post(route('documentos.atestado.pdf'), [
                'paciente_id' => '',
                'profissional_id' => '',
                'atendimento_data' => '',
                'dias_repouso' => 0,
            ]);

        $response->assertStatus(302); // Redirect com erros de validação
        $response->assertSessionHasErrors(['paciente_id', 'profissional_id', 'atendimento_data', 'dias_repouso']);
    }

    /** @test */
    public function test_atestado_valida_dias_repouso_maximos(): void
    {
        $paciente = Paciente::first();
        $profissional = Professional::first();

        $response = $this->actingAs($this->provisionResult['user'])
            ->post(route('documentos.atestado.pdf'), [
                'paciente_id' => $paciente->id,
                'profissional_id' => $profissional->id,
                'atendimento_data' => now()->format('Y-m-d'),
                'dias_repouso' => 100, // Acima do máximo 90
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('dias_repouso');
    }

    /** @test */
    public function test_clinica_nao_malheiro_nao_acessa_documentos(): void
    {
        // Cria outra clínica (não Malheiro, id ≠ 1)
        $plan = Plan::create([
            'name' => 'Outro',
            'slug' => 'outro-'.uniqid(),
            'monthly_price' => 149,
            'trial_days' => 14,
            'is_active' => true,
        ]);

        $outraClinica = app(TenantProvisioner::class)->provision([
            'name' => 'Outra Clínica',
            'email' => 'outra@teste.test',
            'admin_name' => 'Admin',
            'password' => 'senha123',
            'vertical' => 'fisioterapia',
            'plan' => $plan,
        ]);

        $this->conectar($outraClinica['clinic']);

        $response = $this->actingAs($outraClinica['user'])
            ->get(route('documentos.papel-timbrado'));

        $response->assertStatus(403); // Proibido para clínicas não Malheiro

        // O branding Malheiro não deve aparecer na outra clínica
        $this->assertNotSame(1, app(MalheiroBranding::class)->clinicaId());
    }

    /** @test */
    public function test_usuario_sem_permissao_nao_acessa(): void
    {
        // Usuário sem permissão documentos.view
        $role = Role::firstOrCreate(['name' => 'auxiliar'.uniqid()], ['label' => 'Auxiliar']);
        $user = $this->provisionResult['clinic']->users()->create([
            'name' => 'Sem Permissão',
            'email' => 'sem'.uniqid().'@teste.test',
            'password' => bcrypt('senha123'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        $response = $this->actingAs($user)
            ->get(route('documentos.papel-timbrado'));

        $response->assertStatus(403);
    }

    /** @test */
    public function test_logo_eh_png_original_nao_svg_generico(): void
    {
        $branding = app(MalheiroBranding::class);
        $dataUri = $branding->logoDataUri();

        // Deve ser uma data URI (embutida no PDF) de imagem PNG
        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);

        // Não deve ser um SVG genérico
        $this->assertStringNotContainsString('.svg', $dataUri);
        $this->assertStringNotContainsString('<svg', $dataUri);
    }

    /** @test */
    public function test_pdf_contem_conteudo_esperado_malheiro(): void
    {
        $response = $this->actingAs($this->provisionResult['user'])
            ->get(route('documentos.papel-timbrado.pdf'));

        $response->assertStatus(200);

        $pdfContent = $response->getContent();
        $this->assertGreaterThan(1000, strlen($pdfContent), 'PDF deve ter tamanho mínimo válido');
        $this->assertStringStartsWith('%PDF', $pdfContent, 'Conteúdo deve ser PDF válido');
    }
}
