<?php

namespace Tests\Feature;

use App\Models\AccountsPayable;
use App\Models\AccountsReceivable;
use App\Models\AppointmentStatus;
use App\Models\Central\Clinic;
use App\Models\Central\User;
use App\Models\Contract;
use App\Models\Notification;
use App\Models\NotificationRead;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Role;
use App\Services\Documentos\AssinaturaService;
use App\Services\Financeiro\RecebimentoService;
use App\Services\Notificacoes\GeradorNotificacoes;
use App\Services\Tenant\TenantDatabaseManager;
use App\Services\Tenant\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesTestTenant;
use Tests\Support\TenantSchema;
use Tests\TestCase;

class NotificacaoTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->clinica, $this->usuario] = $this->criarUsuarioComPermissao(
            'financeiro.view',
            'estoque.view',
            'agenda.view',
            'pacientes.view',
            'contratos.view',
        );
    }

    protected function gerador(): GeradorNotificacoes
    {
        return app(GeradorNotificacoes::class);
    }

    public function test_gerador_cria_alertas_de_financeiro_e_estoque(): void
    {
        $fornecedor = Supplier::create(['nome' => 'Fornecedor X']);
        AccountsPayable::create([
            'supplier_id' => $fornecedor->id,
            'nome_conta' => 'Aluguel',
            'valor' => 1000,
            'data_vencimento' => today()->subDays(5),
            'status' => 'pendente',
            'tipo' => 'aluguel',
        ]);

        Product::create([
            'nome' => 'Luva',
            'estoque' => 0,
            'estoque_minimo' => 5,
            'controlar_estoque' => true,
            'is_active' => true,
        ]);

        $resultado = $this->gerador()->gerar();

        $this->assertArrayHasKey('financeiro', $resultado);
        $this->assertArrayHasKey('estoque', $resultado);

        $this->assertTenantDatabaseHas('notifications', [
            'categoria' => 'financeiro',
            'tipo' => 'conta_pagar_vencida',
            'dedupe_key' => 'financeiro.pagar.vencida:1',
        ]);
        $this->assertTenantDatabaseHas('notifications', [
            'categoria' => 'estoque',
            'tipo' => 'estoque_zerado',
            'dedupe_key' => 'estoque.zerado:1',
        ]);
    }

    public function test_varredura_nao_duplica_alerta_mesma_chave(): void
    {
        AccountsPayable::create([
            'nome_conta' => 'Energia',
            'valor' => 200,
            'data_vencimento' => today()->subDays(2),
            'status' => 'pendente',
            'tipo' => 'energia',
        ]);

        $this->gerador()->gerar();
        $this->gerador()->gerar();
        $this->gerador()->gerar();

        $this->assertTenantDatabaseCount('notifications', 1);
        $this->assertTenantDatabaseHas('notifications', [
            'tipo' => 'conta_pagar_vencida',
            'dedupe_key' => 'financeiro.pagar.vencida:1',
        ]);
    }

    public function test_conta_paga_resolve_alerta_vencida_na_varredura(): void
    {
        $fornecedor = Supplier::create(['nome' => 'Fornecedor Y']);
        $conta = AccountsPayable::create([
            'supplier_id' => $fornecedor->id,
            'nome_conta' => 'Água',
            'valor' => 150,
            'data_vencimento' => today()->subDays(2),
            'status' => 'pendente',
            'tipo' => 'agua',
        ]);

        $this->gerador()->gerar();

        $this->assertTenantDatabaseHas('notifications', [
            'dedupe_key' => 'financeiro.pagar.vencida:'.$conta->id,
            'resolved_at' => null,
        ]);

        $conta->forceFill(['status' => AccountsPayable::PAGO])->save();
        $this->gerador()->gerar();

        $alerta = Notification::query()->where('dedupe_key', 'financeiro.pagar.vencida:'.$conta->id)->first();
        $this->assertNotNull($alerta);
        $this->assertNotNull($alerta->resolved_at);
    }

    public function test_usuario_sem_permissao_financeiro_nao_ve_alerta_financeiro(): void
    {
        // criarUsuarioSemPapel() evita o unique de permissions.name (a regra do
        // projeto proíbe criarUsuarioComPermissao duas vezes no mesmo teste).
        [, $semFinanceiro] = $this->criarUsuarioSemPapel();

        AccountsPayable::create([
            'nome_conta' => 'Energia',
            'valor' => 100,
            'data_vencimento' => today()->subDays(2),
            'status' => 'pendente',
            'tipo' => 'energia',
        ]);

        $this->gerador()->gerar();

        $this->reconectarTenantParaAssercao();

        $this->actingAs($semFinanceiro)
            ->get(route('notificacoes.nao-lidas'))
            ->assertOk()
            ->assertJsonPath('nao_lidas', 0);
    }

    public function test_isolamento_entre_clinicas(): void
    {
        // Clínica B com banco próprio (mesmo padrão de CreatesTenants).
        $clinicaB = Clinic::create([
            'name' => 'Clínica B',
            'email' => 'b@'.uniqid().'.test',
            'status' => 'active',
            'is_active' => true,
        ]);
        $caminhoB = TenantSchema::freshDatabasePath();
        $this->bancosDeTeste[] = $caminhoB;
        $clinicaB->tenantConnection()->create([
            'driver' => 'sqlite',
            'database_name' => $caminhoB,
            'is_active' => true,
            'last_migrated_at' => now(),
        ]);
        $usuarioB = User::factory()->forClinic($clinicaB->id)->create();
        $usuarioB->assignRole(Role::firstOrCreate(['name' => 'papel_'.uniqid()]));

        // Alerta na clínica A (contexto atual do teste).
        AccountsPayable::create([
            'nome_conta' => 'Conta A',
            'valor' => 100,
            'data_vencimento' => today()->subDays(2),
            'status' => 'pendente',
            'tipo' => 'energia',
        ]);
        $this->gerador()->gerar();

        $this->assertTenantDatabaseCount('notifications', 1);
        $this->assertTenantDatabaseHas('notifications', ['tipo' => 'conta_pagar_vencida']);

        // Muda para a clínica B: não deve ver o alerta da clínica A.
        app(TenantManager::class)->set($clinicaB);
        app(TenantDatabaseManager::class)->connectFor($clinicaB->refresh());

        $this->assertTenantDatabaseCount('notifications', 0);

        // Restaura o tenant do teste para as asserções finais.
        $this->conectar($this->clinica);
    }

    public function test_marca_notificacao_como_lida(): void
    {
        $fornecedor = Supplier::create(['nome' => 'Fornecedor Z']);
        AccountsPayable::create([
            'supplier_id' => $fornecedor->id,
            'nome_conta' => 'Telefone',
            'valor' => 80,
            'data_vencimento' => today()->subDays(2),
            'status' => 'pendente',
            'tipo' => 'servico',
        ]);

        $this->gerador()->gerar();

        $alerta = Notification::query()->first();
        $this->assertNotNull($alerta);

        $this->reconectarTenantParaAssercao();

        $this->actingAs($this->usuario)
            ->post(route('notificacoes.ler', $alerta))
            ->assertRedirect();

        $this->assertTenantDatabaseHas('notification_reads', [
            'notification_id' => $alerta->id,
            'user_id' => $this->usuario->id,
        ]);
    }

    public function test_marca_todas_como_lidas(): void
    {
        $this->gerador()->gerar();

        $this->reconectarTenantParaAssercao();

        $this->actingAs($this->usuario)
            ->post(route('notificacoes.ler-todas'))
            ->assertRedirect();

        $total = Notification::query()->count();
        $lidas = NotificationRead::query()->where('user_id', $this->usuario->id)->count();

        $this->assertSame($total, $lidas);
    }

    public function test_salva_preferencias_de_som(): void
    {
        $this->reconectarTenantParaAssercao();

        $this->actingAs($this->usuario)
            ->post(route('notificacoes.preferencias.save'), [
                'sons_ativos' => false,
                'volume' => 30,
                'categorias' => ['financeiro' => true, 'estoque' => false],
                'navegador_ativo' => true,
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $preferencia = \App\Models\Central\NotificationPreference::query()
            ->where('user_id', $this->usuario->id)
            ->first();

        $this->assertNotNull($preferencia);
        $this->assertFalse($preferencia->sons_ativos);
        $this->assertSame(30, $preferencia->volume);
        $this->assertFalse($preferencia->categorias['estoque'] ?? true);
    }

    public function test_salva_preferencia_de_alerta_central(): void
    {
        $this->reconectarTenantParaAssercao();

        $this->actingAs($this->usuario)
            ->post(route('notificacoes.preferencias.save'), [
                'sons_ativos' => true,
                'volume' => 60,
                'alerta_central' => false,
            ])
            ->assertOk()
            ->assertJsonPath('preferencias.alerta_central', false);

        $preferencia = \App\Models\Central\NotificationPreference::query()
            ->where('user_id', $this->usuario->id)
            ->first();

        $this->assertNotNull($preferencia);
        $this->assertFalse($preferencia->alerta_central);
    }

    public function test_evento_pagamento_recebido_cria_alerta(): void
    {
        \App\Models\Paciente::create([
            'id' => 1,
            'nome' => 'Paciente Pagador',
            'status' => 'ativo',
        ]);

        $metodo = PaymentMethod::create(['name' => 'Pix', 'label' => 'Pix']);
        $conta = AccountsReceivable::create([
            'paciente_id' => 1,
            'descricao' => 'Consulta',
            'vencimento' => today(),
            'valor' => 200,
            'valor_pago' => 0,
            'status' => 'aberta',
        ]);

        app(RecebimentoService::class)->receber($conta, [
            'valor' => 200,
            'payment_method_id' => $metodo->id,
            'user_id' => $this->usuario->id,
        ]);

        $this->assertTenantDatabaseHas('notifications', [
            'tipo' => 'financeiro.pagamento_recebido',
            'referencia_type' => 'accounts_receivable',
            'referencia_id' => $conta->id,
        ]);
    }

    public function test_assinatura_resolve_alerta_contrato_pendente(): void
    {
        $paciente = \App\Models\Paciente::create([
            'nome' => 'Paciente Teste',
            'cpf' => '12345678901',
            'status' => 'ativo',
        ]);

        $contrato = Contract::create([
            'paciente_id' => $paciente->id,
            'user_id' => $this->usuario->id,
            'tipo' => 'responsabilidade',
            'conteudo' => 'Termo',
            'status' => 'pendente',
            'versao' => 1,
            'conteudo_hash' => hash('sha256', '1'."\n".'Termo'),
        ]);

        $this->gerador()->gerar();

        $this->assertTenantDatabaseHas('notifications', [
            'tipo' => 'contrato_pendente',
            'dedupe_key' => 'assinaturas.contrato:'.$contrato->id,
        ]);

        app(AssinaturaService::class)->assinarContrato(
            $contrato,
            'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            $this->usuario,
        );

        $alerta = Notification::query()->where('dedupe_key', 'assinaturas.contrato:'.$contrato->id)->first();
        $this->assertNotNull($alerta);
        $this->assertNotNull($alerta->resolved_at);
    }

    public function test_pagina_de_notificacoes_renderiza(): void
    {
        // Com pelo menos um alerta a página exercita o eager load de `leituras`,
        // que era onde a tela quebrava com dados reais.
        $fornecedor = Supplier::create(['nome' => 'Fornecedor Historico']);
        AccountsPayable::create([
            'supplier_id' => $fornecedor->id,
            'nome_conta' => 'Luz',
            'valor' => 90,
            'data_vencimento' => today()->subDays(4),
            'status' => 'pendente',
            'tipo' => 'servico',
        ]);

        $this->gerador()->gerar();

        $this->reconectarTenantParaAssercao();

        $this->actingAs($this->usuario)
            ->get(route('notificacoes.index'))
            ->assertOk()
            ->assertSee('Central de notifica', false)
            ->assertSee('Luz')
            ->assertSee('scoSino', false)
            ->assertSee('scoPreferenciasNotificacoes', false)
            ->assertSee('scoAlertaCentralOverlay', false)
            ->assertSee('Abrir registro')
            ->assertSee('Mostrar alerta no centro da tela');
    }

    public function test_sino_devolve_json_de_nao_lidas(): void
    {
        $fornecedor = Supplier::create(['nome' => 'Fornecedor Sino']);
        AccountsPayable::create([
            'supplier_id' => $fornecedor->id,
            'nome_conta' => 'Internet',
            'valor' => 120,
            'data_vencimento' => today()->subDays(1),
            'status' => 'pendente',
            'tipo' => 'servico',
        ]);

        $this->gerador()->gerar();

        $this->reconectarTenantParaAssercao();

        $this->actingAs($this->usuario)
            ->getJson(route('notificacoes.nao-lidas'))
            ->assertOk()
            ->assertJsonStructure([
                'nao_lidas',
                'itens' => [['id', 'categoria', 'prioridade', 'titulo', 'lida']],
                'preferencias' => ['sons_ativos', 'volume', 'categorias', 'navegador_ativo', 'alerta_central'],
            ])
            ->assertJsonPath('nao_lidas', 1);
    }

    public function test_marca_como_lida_via_json(): void
    {
        $fornecedor = Supplier::create(['nome' => 'Fornecedor JSON']);
        AccountsPayable::create([
            'supplier_id' => $fornecedor->id,
            'nome_conta' => 'Agua',
            'valor' => 60,
            'data_vencimento' => today()->subDays(3),
            'status' => 'pendente',
            'tipo' => 'servico',
        ]);

        $this->gerador()->gerar();

        $alerta = Notification::query()->first();
        $this->assertNotNull($alerta);

        $this->reconectarTenantParaAssercao();

        $this->actingAs($this->usuario)
            ->postJson(route('notificacoes.ler', $alerta))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertTenantDatabaseHas('notification_reads', [
            'notification_id' => $alerta->id,
            'user_id' => $this->usuario->id,
        ]);
    }

    public function test_preferencias_get_devolve_padroes_sem_gravar(): void
    {
        $this->reconectarTenantParaAssercao();

        $this->actingAs($this->usuario)
            ->getJson(route('notificacoes.preferencias'))
            ->assertOk()
            ->assertJsonPath('preferencias.sons_ativos', true)
            ->assertJsonPath('preferencias.volume', 60)
            ->assertJsonPath('preferencias.alerta_central', true);

        $this->assertDatabaseCount('notification_preferences', 0);
    }
}
