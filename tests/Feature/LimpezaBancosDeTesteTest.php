<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesTenants;
use Tests\Support\TenantSchema;
use Tests\TestCase;

/**
 * A limpeza dos bancos de teste é a diferença entre 5,8 GB de lixo e nada.
 *
 * O Windows não apaga arquivo com handle aberto: sem fechar a conexão antes
 * do `unlink`, cada teste deixava um `.sqlite` de ~400 KB para trás. E, quando
 * uma classe de teste define o próprio `tearDown`, o PHP descarta o do trait
 * `CreatesTenants` — o arquivo das clínicas ficava para trás, sem aviso.
 *
 * Estes testes travam as duas garantias.
 */
class LimpezaBancosDeTesteTest extends TestCase
{
    public function test_apaga_o_banco_mesmo_com_a_conexao_aberta(): void
    {
        $caminho = $this->criarBancoDeTeste();

        // Abre a conexão no arquivo: é ela que segura o handle no Windows.
        $this->apontarConexaoPara($caminho);

        DB::connection('tenant')->select('select 1');

        $this->apagarBancoDeTeste($caminho);

        $this->assertFileDoesNotExist($caminho);
    }

    public function test_apaga_a_pasta_com_os_bancos_das_clinicas(): void
    {
        $pasta = storage_path('framework'.DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'tenants-'.uniqid());

        mkdir($pasta);

        $arquivo = $pasta.DIRECTORY_SEPARATOR.'clinic_000001.sqlite';

        copy(TenantSchema::templatePath(), $arquivo);

        $this->apontarConexaoPara($arquivo);

        DB::connection('tenant')->select('select 1');

        $this->apagarPastaDeBancosDeTeste($pasta);

        $this->assertDirectoryDoesNotExist($pasta);
    }

    public function test_apaga_todos_os_bancos_registrados(): void
    {
        $primeiro = $this->criarBancoDeTeste();
        $segundo = $this->criarBancoDeTeste();

        $this->bancosDeTeste[] = $primeiro;
        $this->bancosDeTeste[] = $segundo;

        $this->apagarBancosRegistados();

        $this->assertFileDoesNotExist($primeiro);
        $this->assertFileDoesNotExist($segundo);
        $this->assertSame([], $this->bancosDeTeste);
    }

    public function test_o_trait_de_clinicas_nao_tem_teardown_proprio(): void
    {
        // Se o `tearDown` voltar para dentro do trait, uma classe que define o
        // próprio `tearDown` — é o caso de `PlatformClinicTest` — descarta o
        // do trait em silêncio e os bancos das clínicas ficam para trás.
        $this->assertFalse(
            method_exists(CreatesTenants::class, 'tearDown'),
            'A limpeza dos bancos do CreatesTenants precisa ficar no TestCase base.'
        );
    }

    public function test_o_template_do_schema_sempre_esta_inteiro(): void
    {
        // O template é montado num arquivo temporário e só entra no lugar no
        // final. Se ele for entregue pela metade, quem copia recebe um banco
        // sem tabela nenhuma e o erro aparece longe da causa.
        $caminho = TenantSchema::templatePath();

        $this->assertFileExists($caminho);

        $this->apontarConexaoPara($caminho);

        foreach (['roles', 'user_roles', 'clinic_profile', 'professionals'] as $tabela) {
            $this->assertTrue(
                Schema::connection('tenant')->hasTable($tabela),
                "A tabela [{$tabela}] não está no template do schema tenant."
            );
        }
    }

    /**
     * Cria um arquivo de banco de teste a partir do template do schema.
     */
    private function criarBancoDeTeste(): string
    {
        $caminho = storage_path('framework'.DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'tenant_'.uniqid().'.sqlite');

        copy(TenantSchema::templatePath(), $caminho);

        return $caminho;
    }

    /**
     * Aponta a conexão `tenant` para um arquivo, reabrindo a conexão.
     */
    private function apontarConexaoPara(string $caminho): void
    {
        DB::purge('tenant');

        Config::set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $caminho,
            'prefix' => '',
            'prefix_indexes' => true,
            'foreign_key_constraints' => true,
        ]);
    }
}
