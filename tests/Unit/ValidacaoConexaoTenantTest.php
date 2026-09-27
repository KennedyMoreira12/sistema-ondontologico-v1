<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Rede de segurança para a divisão central/tenant do banco.
 *
 * Regras `exists:` e `unique:` sem o prefixo `tenant.` são consultadas na conexão
 * default, que é o banco CENTRAL. Como as tabelas operacionais vivem no banco da
 * clínica, isso não dá "false" silencioso: estoura `no such table` e quebra a tela
 * com 500. Já aconteceu em cinco regras durante a migração, então o invariant
 * fica verificado por teste em vez de por revisão manual.
 *
 * A lista de tabelas do tenant é lida das migrations, então o teste acompanha o
 * schema sem manutenção. As tabelas centrais são uma allowlist: `users`,
 * `clinics` e companhia continuam válidas sem prefixo.
 */
class ValidacaoConexaoTenantTest extends TestCase
{
    /**
     * Tabelas do banco central, que são validadas sem prefixo.
     *
     * @var list<string>
     */
    private const TABELAS_CENTRAIS = [
        'users',
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'personal_access_tokens',
        'clinics',
        'plans',
        'plan_features',
        'subscriptions',
        'subscription_history',
        'tenant_connections',
        'integration_tokens',
        'platform_audit_logs',
        'usage_metrics',
    ];

    public function test_nenhuma_regra_de_validacao_aponta_para_o_banco_central(): void
    {
        $infracoes = [];

        foreach ($this->arquivosPhpEm($this->raizDoProjeto().DIRECTORY_SEPARATOR.'app') as $arquivo) {
            $conteudo = (string) file_get_contents($arquivo->getPathname());

            foreach ($this->tabelasCitadas($conteudo) as $citada) {
                $tabela = explode('.', $citada)[0];

                if (! in_array($tabela, $this->tabelasTenant(), true)) {
                    continue;
                }

                if (str_starts_with($citada, 'tenant.')) {
                    continue;
                }

                $infracoes[] = sprintf(
                    '%s: "%s" deveria ser "tenant.%s"',
                    $this->caminhoRelativo($arquivo),
                    $citada,
                    $tabela,
                );
            }
        }

        $this->assertSame(
            [],
            $infracoes,
            "Regras de validação apontando para o banco central:\n".implode("\n", $infracoes),
        );
    }

    public function test_o_teste_encontra_uma_regra_sem_prefixo(): void
    {
        // Se o detector não enxergar mais nada, o teste acima vira um falso verde.
        $this->assertContains('products', $this->tabelasCitadas("'unique:products,codigo'"));
        $this->assertContains('tenant.products', $this->tabelasCitadas("'unique:tenant.products,codigo'"));
    }

    public function test_tabelas_tenant_sao_lidas_das_migrations(): void
    {
        $tabelas = $this->tabelasTenant();

        $this->assertContains('products', $tabelas);
        $this->assertContains('procedures', $tabelas);
        $this->assertNotContains('users', $tabelas);
    }

    /**
     * Nomes de tabela citados em regras `exists:`/`unique:`, na forma de string
     * e na forma `Rule::`.
     *
     * @return list<string>
     */
    private function tabelasCitadas(string $conteudo): array
    {
        preg_match_all("/'(?:exists|unique):([a-z0-9_.]+)/", $conteudo, $string);
        preg_match_all("/Rule::(?:exists|unique)\(\s*'([a-z0-9_.]+)'/", $conteudo, $objeto);

        return array_merge($string[1], $objeto[1]);
    }

    /**
     * @return list<string>
     */
    private function tabelasTenant(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $tabelas = [];

        foreach (glob($this->raizDoProjeto().DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.'tenant'.DIRECTORY_SEPARATOR.'*.php') ?: [] as $migration) {
            preg_match_all("/Schema::create\(\s*'([a-z0-9_]+)'/", (string) file_get_contents($migration), $criadas);

            foreach ($criadas[1] as $tabela) {
                $tabelas[$tabela] = true;
            }
        }

        $tabelas = array_values(array_diff(array_keys($tabelas), self::TABELAS_CENTRAIS));
        sort($tabelas);

        return $cache = $tabelas;
    }

    /**
     * @return list<SplFileInfo>
     */
    private function arquivosPhpEm(string $diretorio): array
    {
        if (! is_dir($diretorio)) {
            return [];
        }

        $arquivos = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($diretorio)) as $arquivo) {
            if ($arquivo instanceof SplFileInfo && $arquivo->getExtension() === 'php') {
                $arquivos[] = $arquivo;
            }
        }

        return $arquivos;
    }

    private function caminhoRelativo(SplFileInfo $arquivo): string
    {
        return str_replace($this->raizDoProjeto().DIRECTORY_SEPARATOR, '', $arquivo->getPathname());
    }

    /**
     * `tests/Unit` -> raiz do projeto. Este teste não sobe a aplicação, então
     * não pode usar os helpers `app_path()`/`base_path()`.
     */
    private function raizDoProjeto(): string
    {
        return dirname(__DIR__, 2);
    }
}
