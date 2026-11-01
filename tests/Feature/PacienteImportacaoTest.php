<?php

namespace Tests\Feature;

use App\Models\Paciente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\UsesTestTenant;
use Tests\TestCase;

class PacienteImportacaoTest extends TestCase
{
    use RefreshDatabase, UsesTestTenant;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_usuario_sem_permissao_nao_acessa_a_importacao(): void
    {
        [, $user] = $this->criarUsuarioSemPapel();

        $this->actingAs($user)
            ->get(route('pacientes.importar.create'))
            ->assertForbidden();
    }

    public function test_preview_do_csv_leva_para_a_conferencia_de_colunas(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('pacientes.create');

        $this->actingAs($user)
            ->post(route('pacientes.importar.preview'), [
                'arquivo' => $this->csv("nome;cpf;nascimento\nAna Souza;529.982.247-25;10/05/1985\n"),
            ])
            ->assertRedirect(route('pacientes.importar.mapear'));

        $this->actingAs($user)
            ->get(route('pacientes.importar.mapear'))
            ->assertOk()
            ->assertSee('Ana Souza')
            ->assertSee('Dados pessoais');
    }

    public function test_importa_pacientes_com_campos_normalizados(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('pacientes.create');

        $this->enviarPlanilha($user, "nome;cpf;nascimento;sexo;celular;cidade;uf\n"
            ."Ana Souza;529.982.247-25;10/05/1985;F;(38) 99999-1111;Januária;MG\n"
            ."João Lima;;31/05/1980;M;38988887777;Belo Horizonte;Minas Gerais\n");

        $this->actingAs($user)
            ->post(route('pacientes.importar.processar'), [
                'mapa' => [
                    'nome' => 0,
                    'cpf' => 1,
                    'nascimento' => 2,
                    'sexo' => 3,
                    'celular' => 4,
                    'cidade' => 5,
                    'estado' => 6,
                ],
            ])
            ->assertRedirect(route('pacientes.importar.resultado'));

        $this->assertDatabaseHas('pacientes', [
            'nome' => 'Ana Souza',
            'cpf' => '52998224725',
            'sexo' => 'feminino',
            'celular' => '38999991111',
            'cidade' => 'Januária',
            'estado' => 'MG',
        ], 'tenant');

        $this->assertSame(
            '1985-05-10',
            Paciente::where('nome', 'Ana Souza')->firstOrFail()->nascimento->format('Y-m-d'),
        );

        $this->assertDatabaseHas('pacientes', [
            'nome' => 'João Lima',
            'cpf' => null,
            'celular' => '38988887777',
            'estado' => 'MG',
        ], 'tenant');

        $this->assertSame(2, session('pacientes.importacao.resultado')['criados']);
        Storage::disk('local')->assertDirectoryEmpty('importacoes');
    }

    public function test_paciente_ja_cadastrado_e_ignorado(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('pacientes.create');

        Paciente::create([
            'nome' => 'Ana Souza',
            'cpf' => '52998224725',
        ]);

        $this->enviarPlanilha($user, "nome;cpf\nAna Souza;529.982.247-25\n");

        $this->actingAs($user)
            ->post(route('pacientes.importar.processar'), ['mapa' => ['nome' => 0, 'cpf' => 1]])
            ->assertRedirect(route('pacientes.importar.resultado'));

        $this->assertSame(1, Paciente::count());
        $this->assertSame(0, session('pacientes.importacao.resultado')['criados']);
        $this->assertSame(1, session('pacientes.importacao.resultado')['ignorados_total']);
    }

    public function test_linha_sem_nome_vira_erro_sem_impedir_as_outras(): void
    {
        [, $user] = $this->criarUsuarioComPermissao('pacientes.create');

        $this->enviarPlanilha($user, "nome;cpf\n;529.982.247-25\nJoão Lima;\n");

        $this->actingAs($user)
            ->post(route('pacientes.importar.processar'), ['mapa' => ['nome' => 0, 'cpf' => 1]])
            ->assertRedirect(route('pacientes.importar.resultado'));

        $resultado = session('pacientes.importacao.resultado');

        $this->assertSame(1, $resultado['criados']);
        $this->assertSame(1, $resultado['erros_total']);
        $this->assertDatabaseHas('pacientes', ['nome' => 'João Lima'], 'tenant');
    }

    private function enviarPlanilha($user, string $conteudo): void
    {
        $this->actingAs($user)
            ->post(route('pacientes.importar.preview'), ['arquivo' => $this->csv($conteudo)])
            ->assertRedirect(route('pacientes.importar.mapear'));
    }

    private function csv(string $conteudo): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('pacientes.csv', $conteudo);
    }
}
