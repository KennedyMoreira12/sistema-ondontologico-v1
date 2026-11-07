<?php

namespace Tests\Feature;

use App\Services\Agenda\AgendamentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Isolamento das chaves de cache entre clínicas.
 *
 * O cache do sistema é compartilhado (banco de dados), então qualquer chave
 * que dependa da clínica em atendimento precisa carregar o identificador
 * dela. Sem isso, o lock de agenda de uma clínica trava a agenda de outra e o
 * limite de uma clínica come o orçamento de outra.
 *
 * Estes testes existem porque a chave é uma string montada dentro do código:
 * sem teste, ela volta a ser montada "só com o id do profissional" na
 * próxima refatoração.
 */
class IsolamentoCacheTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_a_chave_de_cache_carrega_a_clinica_em_atendimento(): void
    {
        $clinica = $this->criarClinica('Clínica da Chave');

        $this->conectar($clinica['clinic']);

        $this->assertSame(
            "tenant:{$clinica['clinic']->id}:agenda:prof:7:2026-10-21",
            tenant_cache_key('agenda:prof:7:2026-10-21'),
        );
    }

    public function test_a_chave_do_lock_de_agenda_e_diferente_em_cada_clinica(): void
    {
        $primeira = $this->criarClinica('Primeira Clínica');
        $segunda = $this->criarClinica('Segunda Clínica');

        $servico = app(AgendamentoService::class);

        $this->conectar($primeira['clinic']);
        $chavePrimeira = $servico->chaveDoLock(7, '2026-10-21');

        $this->conectar($segunda['clinic']);
        $chaveSegunda = $servico->chaveDoLock(7, '2026-10-21');

        $this->assertNotSame($chavePrimeira, $chaveSegunda);
        $this->assertStringContainsString((string) $primeira['clinic']->id, $chavePrimeira);
        $this->assertStringContainsString((string) $segunda['clinic']->id, $chaveSegunda);
    }

    public function test_a_chave_do_lock_preserva_o_profissional_e_o_dia(): void
    {
        $clinica = $this->criarClinica('Clínica da Chave do Lock');

        $this->conectar($clinica['clinic']);

        $chave = app(AgendamentoService::class)->chaveDoLock(7, '2026-10-21');

        $this->assertStringEndsWith('agenda:prof:7:2026-10-21', $chave);
    }
}
