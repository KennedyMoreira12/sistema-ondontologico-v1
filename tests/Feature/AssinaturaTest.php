<?php

namespace Tests\Feature;

use App\Models\Central\User;
use App\Models\Contract;
use App\Models\DocumentSignature;
use App\Models\EvolucaoSessao;
use App\Models\Paciente;
use App\Services\Tenant\VerticalManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use RuntimeException;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

/**
 * Assinatura manuscrita eletrônica no prontuário.
 *
 * O que estes testes protegem:
 * - a assinatura é um PNG de verdade e fica guardada com hash do desenho e do
 *   conteúdo assinado (evidência de integridade);
 * - uma assinatura ativa nunca é trocada por baixo dos panos: se o termo muda,
 *   a anterior é marcada como substituída e continua no histórico;
 * - o PDF do termo e o PDF da ficha carregam o desenho e o aviso de que não é
 *   assinatura digital qualificada ICP-Brasil;
 * - clínica não enxerga assinatura de outra clínica.
 */
class AssinaturaTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    /**
     * PNG 1x1 válido (67 bytes): serve para exercitar a validação de verdade,
     * inclusive o cabeçalho da imagem.
     */
    private const PNG_DATA_URI = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        [$clinica, $this->user] = $this->criarUsuarioComPermissao(
            'pacientes.view',
            'pacientes.create',
            'prontuario.view',
            'prontuario.create',
            'contratos.view',
            'contratos.create',
            'contratos.update',
            'evolucao.view',
            'evolucao.create',
            'evolucao.update',
        );

        $this->conectar($clinica);

        /**
         * As rotas de presença de sessão pertencem à vertical de fisioterapia.
         * A assinatura do termo não depende de vertical, então a mesma clínica
         * de teste cobre os dois fluxos.
         */
        $clinica->update(['vertical' => VerticalManager::FISIOTERAPIA]);
    }

    private function criarPaciente(string $nome = 'Maria Silva'): Paciente
    {
        return Paciente::create([
            'nome' => $nome,
            'status' => 'ativo',
        ]);
    }

    private function criarTermo(Paciente $paciente, string $conteudo = 'Termo de responsabilidade da clínica.'): Contract
    {
        return Contract::create([
            'paciente_id' => $paciente->id,
            'tipo' => Contract::TIPO_TERMO_RESPONSABILIDADE,
            'conteudo' => $conteudo,
            'status' => 'pendente',
        ]);
    }

    private function assinarTermo(Paciente $paciente)
    {
        return $this->actingAs($this->user)->post(route('pacientes.assinaturas.termo', $paciente), [
            'assinatura' => self::PNG_DATA_URI,
        ]);
    }

    /**
     * Depois de uma requisição a conexão tenant é limpa; reconecta antes de
     * consultar qualquer tabela da clínica.
     */
    private function totalDeAssinaturas(): int
    {
        $this->reconectarTenantParaAssercao();

        return DocumentSignature::query()->count();
    }

    private function assinaturaUnica(): DocumentSignature
    {
        $this->reconectarTenantParaAssercao();

        return DocumentSignature::query()->sole();
    }

    public function test_assinatura_do_termo_grava_evidencias_e_vincula_ao_paciente(): void
    {
        $paciente = $this->criarPaciente();
        $termo = $this->criarTermo($paciente);

        $this->assinarTermo($paciente)
            ->assertRedirect(route('pacientes.prontuario', $paciente))
            ->assertSessionHas('success');

        $assinatura = $this->assinaturaUnica();

        $this->assertSame($paciente->id, $assinatura->paciente_id);
        $this->assertSame($this->user->id, $assinatura->user_id);
        $this->assertSame(DocumentSignature::TIPO_TERMO, $assinatura->document_type);
        $this->assertSame($termo->getMorphClass(), $assinatura->signable_type);
        $this->assertSame($termo->id, $assinatura->signable_id);
        $this->assertSame(1, $assinatura->term_version);
        $this->assertSame($termo->conteudo, $assinatura->conteudo_assinado);
        $this->assertNotNull($assinatura->signed_at);
        $this->assertNotNull($assinatura->ip);
        $this->assertNull($assinatura->superseded_at);

        $this->assertSame(
            hash('sha256', $assinatura->binario()),
            $assinatura->signature_hash,
            'O hash guardado deve ser o do PNG recebido.',
        );
        $this->assertSame(
            DocumentSignature::hashDoConteudo(1, $termo->conteudo),
            $assinatura->content_hash,
            'O hash guardado deve ser o do texto assinado.',
        );
        $this->assertTrue($assinatura->integra());

        $termo->refresh();
        $this->assertSame('assinado', $termo->status);
        $this->assertNotNull($termo->assinado_em);

        $this->assertTenantDatabaseHas('audit_logs', [
            'acao' => 'assinar_documento',
            'tabela' => 'document_signatures',
            'registro_id' => $assinatura->id,
        ]);
    }

    public function test_primeira_assinatura_cria_o_termo_padrao_quando_a_clinica_nao_cadastrou(): void
    {
        $paciente = $this->criarPaciente('Joana Ribeiro');

        $this->assinarTermo($paciente)->assertSessionHasNoErrors();

        $this->reconectarTenantParaAssercao();

        $termo = Contract::query()
            ->where('paciente_id', $paciente->id)
            ->where('tipo', Contract::TIPO_TERMO_RESPONSABILIDADE)
            ->sole();

        $this->assertStringContainsString('Joana Ribeiro', $termo->conteudo);
        $this->assertSame(1, $termo->versao);
        $this->assertSame('assinado', $termo->status);

        $assinatura = DocumentSignature::query()->sole();
        $this->assertSame($termo->conteudo, $assinatura->conteudo_assinado);
        $this->assertTrue($assinatura->integra());
    }

    public function test_assinatura_vazia_e_rejeitada(): void
    {
        $paciente = $this->criarPaciente();
        $this->criarTermo($paciente);

        $this->actingAs($this->user)
            ->post(route('pacientes.assinaturas.termo', $paciente), [
                'assinatura' => 'data:image/png;base64,',
            ])
            ->assertSessionHasErrors('assinatura');

        $this->assertSame(0, $this->totalDeAssinaturas());
    }

    public function test_conteudo_que_nao_e_png_e_rejeitado(): void
    {
        $paciente = $this->criarPaciente();
        $this->criarTermo($paciente);

        $this->actingAs($this->user)
            ->post(route('pacientes.assinaturas.termo', $paciente), [
                'assinatura' => 'data:image/png;base64,'.base64_encode('isto nao e uma imagem png de verdade'),
            ])
            ->assertSessionHasErrors('assinatura');

        $this->assertSame(0, $this->totalDeAssinaturas());
    }

    public function test_assinatura_sem_o_campo_obrigatorio_e_rejeitada(): void
    {
        $paciente = $this->criarPaciente();
        $this->criarTermo($paciente);

        $this->actingAs($this->user)
            ->post(route('pacientes.assinaturas.termo', $paciente), [])
            ->assertSessionHasErrors('assinatura');

        $this->assertSame(0, $this->totalDeAssinaturas());
    }

    public function test_segunda_assinatura_do_mesmo_termo_e_bloqueada(): void
    {
        $paciente = $this->criarPaciente();
        $this->criarTermo($paciente);

        $this->assinarTermo($paciente)->assertSessionHasNoErrors();

        $this->assinarTermo($paciente)->assertSessionHasErrors('assinatura');

        $this->assertSame(1, $this->totalDeAssinaturas());
    }

    public function test_termo_alterado_exige_nova_assinatura_e_preserva_a_anterior(): void
    {
        $paciente = $this->criarPaciente();
        $termo = $this->criarTermo($paciente, 'Texto original do termo.');

        $this->assinarTermo($paciente)->assertSessionHasNoErrors();

        $anterior = $this->assinaturaUnica();

        $this->actingAs($this->user)
            ->put(route('contratos.update', $termo), [
                'paciente_id' => $paciente->id,
                'tipo' => Contract::TIPO_TERMO_RESPONSABILIDADE,
                'conteudo' => 'Texto novo do termo, com uma cláusula a mais.',
                'status' => 'assinado',
            ])
            ->assertRedirect(route('contratos.index'));

        $this->reconectarTenantParaAssercao();

        $termo->refresh();
        $anterior->refresh();

        $this->assertSame(2, $termo->versao);
        $this->assertSame('pendente', $termo->status);
        $this->assertNull($termo->assinado_em);
        $this->assertNotNull($anterior->superseded_at, 'A assinatura antiga deve ser marcada como substituída.');
        $this->assertFalse($anterior->estaAtiva());
        $this->assertSame(1, DocumentSignature::query()->count(), 'A assinatura antiga não pode ser apagada.');

        $this->assinarTermo($paciente)->assertSessionHasNoErrors();

        $this->assertSame(2, $this->totalDeAssinaturas());

        $nova = DocumentSignature::query()->whereNull('superseded_at')->sole();
        $this->assertSame(2, $nova->term_version);
        $this->assertStringContainsString('cláusula a mais', $nova->conteudo_assinado);
        $this->assertTrue($nova->integra());

        $this->actingAs($this->user)
            ->get(route('pacientes.assinaturas.imagem', [$paciente, $anterior]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_editar_termo_nao_assinado_nao_cria_nova_versao(): void
    {
        $paciente = $this->criarPaciente();
        $termo = $this->criarTermo($paciente, 'Texto inicial.');

        $this->actingAs($this->user)
            ->put(route('contratos.update', $termo), [
                'paciente_id' => $paciente->id,
                'tipo' => Contract::TIPO_TERMO_RESPONSABILIDADE,
                'conteudo' => 'Texto ajustado antes de assinar.',
                'status' => 'pendente',
            ])
            ->assertRedirect(route('contratos.index'));

        $this->reconectarTenantParaAssercao();

        $termo->refresh();

        $this->assertSame(1, $termo->versao);
        $this->assertSame(
            DocumentSignature::hashDoConteudo(1, 'Texto ajustado antes de assinar.'),
            $termo->conteudo_hash,
        );
    }

    public function test_presenca_de_sessao_e_confirmada_por_assinatura(): void
    {
        $paciente = $this->criarPaciente();

        $sessao = EvolucaoSessao::create([
            'paciente_id' => $paciente->id,
            'data' => '2026-11-10',
            'conteudo' => 'Sessão de fisioterapia com mobilização e exercícios.',
            'status' => EvolucaoSessao::RASCUNHO,
        ]);

        $this->actingAs($this->user)
            ->post(route('pacientes.evolucoes-sessao.assinar-presenca', [$paciente, $sessao]), [
                'assinatura' => self::PNG_DATA_URI,
            ])
            ->assertRedirect(route('pacientes.evolucoes-sessao.index', $paciente))
            ->assertSessionHas('success');

        $assinatura = $this->assinaturaUnica();

        $this->assertSame(DocumentSignature::TIPO_PRESENCA, $assinatura->document_type);
        $this->assertSame($sessao->getMorphClass(), $assinatura->signable_type);
        $this->assertSame($sessao->id, $assinatura->signable_id);
        $this->assertSame($paciente->id, $assinatura->paciente_id);
        $this->assertStringContainsString('Confirmação de presença', $assinatura->document_title);
        $this->assertNotNull($assinatura->signed_at);
        $this->assertTrue($assinatura->integra());

        $this->assertTrue($sessao->fresh()->assinaturaAtiva()->exists());
    }

    public function test_segunda_confirmacao_de_presenca_da_mesma_sessao_e_bloqueada(): void
    {
        $paciente = $this->criarPaciente();

        $sessao = EvolucaoSessao::create([
            'paciente_id' => $paciente->id,
            'data' => '2026-11-10',
            'conteudo' => 'Sessão única.',
            'status' => EvolucaoSessao::RASCUNHO,
        ]);

        $url = route('pacientes.evolucoes-sessao.assinar-presenca', [$paciente, $sessao]);

        $this->actingAs($this->user)
            ->post($url, ['assinatura' => self::PNG_DATA_URI])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->user)
            ->post($url, ['assinatura' => self::PNG_DATA_URI])
            ->assertSessionHasErrors('assinatura');

        $this->assertSame(1, $this->totalDeAssinaturas());
    }

    public function test_presenca_de_sessao_de_outro_paciente_nao_e_assinada(): void
    {
        $paciente = $this->criarPaciente();
        $outro = $this->criarPaciente('Outro Paciente');

        $sessao = EvolucaoSessao::create([
            'paciente_id' => $outro->id,
            'data' => '2026-11-10',
            'conteudo' => 'Sessão de outro paciente.',
            'status' => EvolucaoSessao::RASCUNHO,
        ]);

        $this->actingAs($this->user)
            ->post(route('pacientes.evolucoes-sessao.assinar-presenca', [$paciente, $sessao]), [
                'assinatura' => self::PNG_DATA_URI,
            ])
            ->assertNotFound();

        $this->assertSame(0, $this->totalDeAssinaturas());
    }

    public function test_assinatura_nao_pode_ser_alterada(): void
    {
        $paciente = $this->criarPaciente();
        $this->criarTermo($paciente);

        $this->assinarTermo($paciente)->assertSessionHasNoErrors();

        $assinatura = $this->assinaturaUnica();

        try {
            $assinatura->update(['document_title' => 'Título trocado']);
            $this->fail('A assinatura deveria ser imutável.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('imutável', $e->getMessage());
        }

        $this->assertNotSame('Título trocado', $assinatura->fresh()->document_title);
    }

    public function test_assinatura_nao_pode_ser_excluida(): void
    {
        $paciente = $this->criarPaciente();
        $this->criarTermo($paciente);

        $this->assinarTermo($paciente)->assertSessionHasNoErrors();

        $assinatura = $this->assinaturaUnica();

        $this->expectException(RuntimeException::class);

        $assinatura->delete();
    }

    public function test_historico_mostra_assinaturas_ativas_e_substituidas(): void
    {
        $paciente = $this->criarPaciente();
        $termo = $this->criarTermo($paciente, 'Texto original.');

        $this->assinarTermo($paciente)->assertSessionHasNoErrors();

        $this->actingAs($this->user)->put(route('contratos.update', $termo), [
            'paciente_id' => $paciente->id,
            'tipo' => Contract::TIPO_TERMO_RESPONSABILIDADE,
            'conteudo' => 'Texto revisado.',
            'status' => 'pendente',
        ]);

        $this->assinarTermo($paciente)->assertSessionHasNoErrors();

        $this->actingAs($this->user)
            ->get(route('pacientes.assinaturas.index', $paciente))
            ->assertOk()
            ->assertSee('Histórico de assinaturas')
            ->assertSee('Ativa')
            ->assertSee('Substituída')
            ->assertSee('ICP-Brasil')
            ->assertSee('v2');
    }

    public function test_pdf_do_termo_embute_a_assinatura_e_o_aviso_de_nao_icp_brasil(): void
    {
        $paciente = $this->criarPaciente();
        $termo = $this->criarTermo($paciente);

        $this->assinarTermo($paciente)->assertSessionHasNoErrors();

        $response = $this->actingAs($this->user)->get(route('contratos.pdf', $termo));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString(
            '/Subtype /Image',
            $response->getContent(),
            'O PDF deve embutir a imagem da assinatura, não apenas o texto do termo.',
        );

        $this->reconectarTenantParaAssercao();

        $contrato = Contract::with(['paciente', 'assinaturaAtiva.user'])->findOrFail($termo->id);

        $html = View::make('assinaturas.pdf-termo', [
            'contrato' => $contrato,
            'assinatura' => $contrato->assinaturaAtiva,
            'clinica' => tenant(),
        ])->render();

        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('Evidências da assinatura', $html);
        $this->assertStringContainsString('Não é uma assinatura digital qualificada ICP-Brasil', $html);
        $this->assertStringContainsString($paciente->nome, $html);
    }

    public function test_pdf_da_ficha_mostra_termo_e_presencas_assinadas(): void
    {
        $paciente = $this->criarPaciente();
        $this->criarTermo($paciente);

        $this->assinarTermo($paciente)->assertSessionHasNoErrors();

        $this->reconectarTenantParaAssercao();

        $sessao = EvolucaoSessao::create([
            'paciente_id' => $paciente->id,
            'data' => '2026-11-10',
            'conteudo' => 'Sessão com presença confirmada.',
            'status' => EvolucaoSessao::RASCUNHO,
        ]);

        $this->actingAs($this->user)
            ->post(route('pacientes.evolucoes-sessao.assinar-presenca', [$paciente, $sessao]), [
                'assinatura' => self::PNG_DATA_URI,
            ])
            ->assertSessionHasNoErrors();

        $response = $this->actingAs($this->user)->get(route('pacientes.ficha.pdf', $paciente));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $this->reconectarTenantParaAssercao();

        $pacientePdf = Paciente::with([
            'evolucoes.profissional',
            'evolucoesSessao.profissional',
            'evolucoesSessao.assinaturaAtiva.user',
        ])->findOrFail($paciente->id);

        $termo = Contract::with('assinaturaAtiva.user')
            ->where('paciente_id', $paciente->id)
            ->where('tipo', Contract::TIPO_TERMO_RESPONSABILIDADE)
            ->latest('id')
            ->first();

        $html = View::make('assinaturas.pdf-ficha', [
            'paciente' => $pacientePdf,
            'termo' => $termo,
            'assinaturaTermo' => $termo?->assinaturaAtiva,
            'clinica' => tenant(),
        ])->render();

        $this->assertSame(2, substr_count($html, 'data:image/png;base64,'));
        $this->assertStringContainsString('Sessões e confirmações de presença', $html);
        $this->assertStringContainsString('Presença confirmada por assinatura', $html);
        $this->assertStringContainsString('Não são assinaturas digitais qualificadas ICP-Brasil', $html);
    }

    public function test_prontuario_mostra_area_de_assinatura_com_touch_action_none(): void
    {
        $paciente = $this->criarPaciente();

        $this->actingAs($this->user)
            ->get(route('pacientes.prontuario', $paciente))
            ->assertOk()
            ->assertSee('Termo de responsabilidade')
            ->assertSee('Assinar documento')
            ->assertSee('data-assinatura-canvas', false)
            ->assertSee('touch-action: none', false)
            ->assertSee('pointerdown', false)
            ->assertSee('ICP-Brasil');
    }

    public function test_prontuario_nao_mostra_botao_de_assinar_depois_de_assinado(): void
    {
        $paciente = $this->criarPaciente();
        $this->criarTermo($paciente);

        $this->assinarTermo($paciente)->assertSessionHasNoErrors();

        $this->actingAs($this->user)
            ->get(route('pacientes.prontuario', $paciente))
            ->assertOk()
            ->assertSee('Ver termo assinado')
            ->assertDontSee('Assinar documento');
    }

    public function test_usuario_sem_permissao_nao_assina_nem_ve_historico(): void
    {
        $paciente = $this->criarPaciente();
        $this->criarTermo($paciente);

        [, $semPermissao] = $this->criarUsuarioSemPapel();

        $this->actingAs($semPermissao)
            ->post(route('pacientes.assinaturas.termo', $paciente), [
                'assinatura' => self::PNG_DATA_URI,
            ])
            ->assertForbidden();

        $this->actingAs($semPermissao)
            ->get(route('pacientes.assinaturas.index', $paciente))
            ->assertForbidden();

        $this->assertSame(0, $this->totalDeAssinaturas());
    }
}
