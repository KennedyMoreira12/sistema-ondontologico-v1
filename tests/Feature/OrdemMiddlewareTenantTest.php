<?php

namespace Tests\Feature;

use App\Models\Paciente;
use App\Services\Tenant\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

/**
 * `ResolveTenant` precisa rodar antes de `SubstituteBindings`.
 *
 * `SubstituteBindings` é middleware do grupo `web` e é ele que resolve o route
 * model binding implícito de `{paciente}`, `{consulta}`, `{orcamento}` — ou seja,
 * ele dispara uma query no banco da clínica. Se `ResolveTenant` entrar como
 * middleware de rota (`['auth', 'tenant']`), ele só roda depois do grupo inteiro
 * e a query do binding sai antes de a conexão `tenant` apontar para algum banco.
 *
 * Os outros testes não pegam isso porque o `TestCase` já conecta o tenant no
 * `setUp`, mascarando a ordem. Por isso os testes aqui limpam a conexão antes de
 * responder, reproduzindo o estado real de uma requisição: banco ainda não
 * escolhido, e a escolha depende do middleware.
 */
class OrdemMiddlewareTenantTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    /**
     * Reproduz o estado de uma requisição nova: nada de clínica resolvida e a
     * conexão `tenant` sem `database`, exatamente como o `TenantServiceProvider`
     * deixa no fim de cada requisição.
     */
    private function simularRequisicaoNova(): void
    {
        app(TenantManager::class)->forget();

        Config::set('database.connections.tenant.database', null);

        DB::purge('tenant');
    }

    public function test_rota_com_model_binding_abre_com_conexao_tenant_limpa(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('pacientes.view');

        $paciente = $this->criarPaciente();

        $this->simularRequisicaoNova();

        $this->actingAs($user)
            ->get(route('pacientes.show', $paciente))
            ->assertOk();
    }

    public function test_binding_aninhado_abre_com_conexao_tenant_limpa(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('orcamentos.view', 'prontuario.view');

        $paciente = $this->criarPaciente();

        $this->simularRequisicaoNova();

        $this->actingAs($user)
            ->get(route('pacientes.orcamentos.index', $paciente))
            ->assertOk();

        $this->simularRequisicaoNova();

        $this->actingAs($user)
            ->get(route('pacientes.prescricoes.index', $paciente))
            ->assertOk();
    }

    public function test_rota_sem_model_binding_continua_funcionando(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('pacientes.view');

        $this->simularRequisicaoNova();

        $this->actingAs($user)
            ->get(route('pacientes.index'))
            ->assertOk();
    }

    private function criarPaciente(): Paciente
    {
        $this->reconectarTenantParaAssercao();

        return Paciente::create(['nome' => 'Ana Clara Santos', 'status' => 'ativo']);
    }
}
