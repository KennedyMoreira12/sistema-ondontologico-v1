<?php

namespace Tests\Feature;

use App\Models\Anamnese;
use App\Models\AuditLog;
use App\Models\Central\User;
use App\Models\Paciente;
use App\Support\AnamnesePreAtendimento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

/**
 * Anamnese de pré-atendimento odontológico.
 *
 * O questionário substitui o texto livre sem trocar de tabela: as respostas
 * continuam no JSON `anamneses.respostas`, compatível com registros antigos.
 */
class AnamnesePreAtendimentoTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    private User $user;

    private Paciente $paciente;

    protected function setUp(): void
    {
        parent::setUp();

        [, $this->user] = $this->criarUsuarioComPermissao(
            'pacientes.view',
            'anamnese.create',
        );

        $this->paciente = Paciente::create(['nome' => 'Marina Alves']);
    }

    public function test_formulario_exibe_as_cinco_perguntas_e_a_ressalva(): void
    {
        $this->actingAs($this->user)
            ->get(route('pacientes.show', $this->paciente))
            ->assertSee('Anamnese — pré-atendimento odontológico')
            ->assertSee('Você possui alguma doença sistêmica?')
            ->assertSee('Hipertensão arterial (pressão alta)')
            ->assertSee('Alterações de coagulação')
            ->assertSee('Faz uso de algum medicamento atualmente?')
            ->assertSee('Costuma sentir dores de cabeça ou tonturas?')
            ->assertSee('Possui alguma alergia a medicamentos ou materiais odontológicos?')
            ->assertSee('Está realizando algum tratamento médico atualmente?')
            ->assertSee('Essas informações serão avaliadas pelo profissional responsável durante a consulta');
    }

    public function test_registra_o_questionario_estruturado_no_json_de_respostas(): void
    {
        $this->actingAs($this->user)
            ->post(route('pacientes.anamneses.store', $this->paciente), $this->payload([
                'doencas' => ['hipertensao', 'outras'],
                'outras_doencas' => 'Asma',
            ]))
            ->assertRedirect();

        $anamnese = Anamnese::firstOrFail();

        $this->assertSame([
            AnamnesePreAtendimento::CHAVE_DOENCAS => [
                'Hipertensão arterial (pressão alta)',
                'Outras: Asma',
            ],
            AnamnesePreAtendimento::CHAVE_MEDICAMENTOS => 'Sim: Losartana 50 mg',
            AnamnesePreAtendimento::CHAVE_DORES => 'Não',
            AnamnesePreAtendimento::CHAVE_ALERGIAS => 'Sim: Penicilina',
            AnamnesePreAtendimento::CHAVE_TRATAMENTO => 'Não',
        ], $anamnese->respostas);

        $this->assertTenantDatabaseHas('anamneses', [
            'id' => $anamnese->id,
            'paciente_id' => $this->paciente->id,
            'titulo' => 'Anamnese inicial',
        ]);
    }

    public function test_exige_especificacao_quando_marca_outras_doencas(): void
    {
        $this->actingAs($this->user)
            ->post(route('pacientes.anamneses.store', $this->paciente), $this->payload([
                'doencas' => ['outras'],
                'outras_doencas' => '',
            ]))
            ->assertSessionHasErrors([
                'respostas.outras_doencas' => 'Especifique quais são as outras doenças.',
            ]);

        $this->assertTenantDatabaseCount('anamneses', 0);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function camposQueExigemDetalhes(): array
    {
        return [
            'medicamentos' => [
                'usa_medicamentos',
                'medicamentos',
                'Informe quais medicamentos você usa.',
            ],
            'dores e tonturas' => [
                'dores_tonturas',
                'dores_tonturas_detalhes',
                'Descreva as dores de cabeça ou tonturas.',
            ],
            'alergias' => [
                'alergias',
                'alergias_detalhes',
                'Descreva as alergias.',
            ],
            'tratamento médico' => [
                'tratamento_medico',
                'tratamento_medico_detalhes',
                'Descreva o tratamento médico atual.',
            ],
        ];
    }

    #[DataProvider('camposQueExigemDetalhes')]
    public function test_exige_detalhes_quando_a_resposta_e_sim(string $campo, string $detalhe, string $mensagem): void
    {
        $payload = $this->payload([
            $campo => AnamnesePreAtendimento::SIM,
            $detalhe => '',
        ]);

        $this->actingAs($this->user)
            ->post(route('pacientes.anamneses.store', $this->paciente), $payload)
            ->assertSessionHasErrors(['respostas.'.$detalhe => $mensagem]);

        $this->assertTenantDatabaseCount('anamneses', 0);
    }

    public function test_exige_resposta_para_as_quatro_perguntas_de_sim_ou_nao(): void
    {
        $payload = $this->payload();

        unset(
            $payload['respostas']['usa_medicamentos'],
            $payload['respostas']['dores_tonturas'],
            $payload['respostas']['alergias'],
            $payload['respostas']['tratamento_medico'],
        );

        $this->actingAs($this->user)
            ->post(route('pacientes.anamneses.store', $this->paciente), $payload)
            ->assertSessionHasErrors([
                'respostas.usa_medicamentos' => 'Informe se faz uso de medicamentos.',
                'respostas.dores_tonturas' => 'Informe se sente dores de cabeça ou tonturas.',
                'respostas.alergias' => 'Informe se possui alergias.',
                'respostas.tratamento_medico' => 'Informe se está em tratamento médico.',
            ]);

        $this->assertTenantDatabaseCount('anamneses', 0);
    }

    public function test_recusa_doenca_fora_do_catalogo(): void
    {
        $this->actingAs($this->user)
            ->post(route('pacientes.anamneses.store', $this->paciente), $this->payload([
                'doencas' => ['doenca_inexistente'],
            ]))
            ->assertSessionHasErrors([
                'respostas.doencas.0' => 'Selecione apenas doenças da lista.',
            ]);

        $this->assertTenantDatabaseCount('anamneses', 0);
    }

    public function test_usuario_sem_permissao_nao_registra_anamnese(): void
    {
        [, $semPermissao] = $this->criarUsuarioSemPapel();

        $this->actingAs($semPermissao)
            ->post(route('pacientes.anamneses.store', $this->paciente), $this->payload())
            ->assertForbidden();

        $this->assertTenantDatabaseCount('anamneses', 0);
    }

    public function test_registra_auditoria_sem_copiar_respostas_sensiveis(): void
    {
        $this->actingAs($this->user)
            ->post(route('pacientes.anamneses.store', $this->paciente), $this->payload());

        $log = AuditLog::firstOrFail();

        $this->assertSame('registrar_anamnese', $log->acao);
        $this->assertSame('anamneses', $log->tabela);
        $this->assertSame('Anamnese inicial', $log->depois['titulo']);
        $this->assertArrayHasKey('anamnese_id', $log->depois);
        $this->assertStringNotContainsString('Losartana', json_encode($log->depois));
    }

    public function test_exibe_respostas_escapadas_na_ficha_do_paciente(): void
    {
        $this->actingAs($this->user)
            ->post(route('pacientes.anamneses.store', $this->paciente), $this->payload([
                'alergias_detalhes' => '<script>alert(1)</script>',
            ]));

        $this->actingAs($this->user)
            ->get(route('pacientes.show', $this->paciente))
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    /**
     * @param  array<string, mixed>  $sobrescreve
     * @return array<string, mixed>
     */
    private function payload(array $sobrescreve = []): array
    {
        $respostas = [
            'doencas' => ['hipertensao'],
            'outras_doencas' => '',
            'usa_medicamentos' => AnamnesePreAtendimento::SIM,
            'medicamentos' => 'Losartana 50 mg',
            'dores_tonturas' => AnamnesePreAtendimento::NAO,
            'dores_tonturas_detalhes' => '',
            'alergias' => AnamnesePreAtendimento::SIM,
            'alergias_detalhes' => 'Penicilina',
            'tratamento_medico' => AnamnesePreAtendimento::NAO,
            'tratamento_medico_detalhes' => '',
        ];

        return [
            'titulo' => 'Anamnese inicial',
            'responsavel' => 'Marina Alves',
            'respostas' => array_replace($respostas, $sobrescreve),
        ];
    }
}
