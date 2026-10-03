<?php

namespace Tests\Feature;

use Dotenv\Parser\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * O .env.production.example e o arquivo que todo deploy copia para .env. Se ele
 * nao parsear, `php artisan` aborta antes de rodar qualquer comando, e o
 * primeiro sinal do problema e um erro de dotenv que nao aponta para o template.
 *
 * Ja aconteceu: marcadores `<<<` colocados depois do valor deixavam linhas como
 * `DB_PASSWORD= <<<`, e o Lexer do dotenv morria com "Encountered unexpected
 * whitespace" — quebrando todo `php artisan` antes de qualquer comando rodar.
 *
 * Atenção: o Parser sozinho NÃO detecta isso. Ele engole `DB_PASSWORD= <<<` como
 * se o valor fosse literalmente `<<<`. Quem levanta a exceção é o Lexer, e a
 * detecção de espaço em branco abaixo é o que cobre esse buraco de verdade.
 */
class EnvProductionExampleTest extends TestCase
{
    /**
     * Le o template direto pelo Parser, sem passar pelo Loadable.
     *
     * Dotenv::createImmutable não serve aqui: o boot do Laravel já populou o
     * $_ENV do processo de teste, e um loader imutável recusa exatamente as
     * chaves que já existem. O teste leria o .env local e passaria verde com o
     * template quebrado — que é o bug que ele existe para pegar.
     */
    private function carregarTemplate(): array
    {
        $caminho = base_path('.env.production.example');

        $this->assertFileExists($caminho, 'O template de producao precisa ser versionado junto do app.');

        try {
            $entradas = (new Parser)->parse(file_get_contents($caminho));
        } catch (\Throwable $e) {
            $this->fail("O .env.production.example nao parseia: {$e->getMessage()}");
        }

        $valores = [];

        foreach ($entradas as $entrada) {
            // Value::getChars() devolve o literal já sem aspas (verificado:
            // APP_NAME="LJ SmartClinic" -> 'LJ SmartClinic'). Chave sem valor
            // (`APP_KEY=`) chega com Option definida e chars vazio.
            $opcao = $entrada->getValue();
            $valores[$entrada->getName()] = $opcao->isDefined()
                ? $opcao->get()->getChars()
                : '';
        }

        return $valores;
    }

    #[Test]
    public function o_template_tem_estrutura_de_dotenv_utilizavel(): void
    {
        $valores = $this->carregarTemplate();

        $this->assertArrayHasKey('APP_ENV', $valores);
        $this->assertNotEmpty($valores);
    }

    #[Test]
    public function nenhuma_linha_tem_espaco_entre_o_igual_e_o_valor(): void
    {
        $linhas = file(base_path('.env.production.example'), FILE_IGNORE_NEW_LINES);

        foreach ($linhas as $indice => $linha) {
            // `CHAVE= valor` é o que quebra. `CHAVE = valor` (espaço antes do
            // igual) também é inválido, mas o erro do Lexer é menos óbvio e
            // essa forma não apareceu em nenhum arquivo deste projeto.
            if (preg_match('/^\s*[A-Z_0-9]+\s*=\s+\S/', $linha) === 1) {
                $this->fail(
                    'Linha '.($indice + 1).": '{$linha}' tem espacos entre o '=' e o valor. ".
                    'O Lexer do dotenv aborta com "Encountered unexpected whitespace" e '.
                    'todo `php artisan` para de funcionar na VPS.'
                );
            }
        }
    }

    #[Test]
    public function nenhuma_linha_tem_espaco_antes_do_igual(): void
    {
        $linhas = file(base_path('.env.production.example'), FILE_IGNORE_NEW_LINES);

        foreach ($linhas as $indice => $linha) {
            if (preg_match('/^\s*[A-Z_0-9]+\s+=/', $linha) === 1) {
                $this->fail('Linha '.($indice + 1).": '{$linha}' tem espacos antes do '='.");
            }
        }
    }

    #[Test]
    public function nenhuma_linha_usa_o_marcador_antigo_que_quebrava_o_parse(): void
    {
        $conteudo = file_get_contents(base_path('.env.production.example'));

        $this->assertStringNotContainsString(
            '<<<',
            $conteudo,
            'O marcador <<< nao e sintaxe dotenv valida e quebra o artisan em producao.'
        );
    }

    #[Test]
    #[DataProvider('chavesObrigatorias')]
    public function a_chave_existe_no_template(string $chave): void
    {
        $this->assertArrayHasKey($chave, $this->carregarTemplate());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function chavesObrigatorias(): array
    {
        $chaves = [
            'APP_KEY', 'APP_URL', 'APP_ENV', 'APP_DEBUG',
            'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE',
            'DB_USERNAME', 'DB_PASSWORD',
            'TENANT_DB_DRIVER', 'TENANT_DB_HOST', 'TENANT_DB_PORT',
            'TENANT_DB_USERNAME', 'TENANT_DB_PASSWORD',
            'TENANT_DB_PREFIX', 'TENANT_DB_PADDING',
            'SESSION_DRIVER', 'SESSION_SECURE_COOKIE',
            'QUEUE_CONNECTION', 'CACHE_STORE',
            'ADMIN_NAME', 'ADMIN_EMAIL', 'ADMIN_PASSWORD',
            'MAIL_MAILER', 'MAIL_HOST', 'MAIL_PORT',
            'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS',
            'WHATSAPP_VERIFY_TOKEN',
        ];

        return array_combine(
            $chaves,
            array_map(fn (string $chave): array => [$chave], $chaves)
        );
    }

    #[Test]
    public function producao_nao_pode_vir_com_debug_ligado(): void
    {
        $env = $this->carregarTemplate();

        $this->assertSame('production', $env['APP_ENV']);
        $this->assertSame('false', $env['APP_DEBUG']);
        $this->assertSame('true', $env['SESSION_SECURE_COOKIE']);
    }

    #[Test]
    public function a_senha_do_banco_central_e_a_das_clinicas_sao_o_mesmo_campo_vazio(): void
    {
        $env = $this->carregarTemplate();

        // Os dois nascem apontando para o mesmo usuário MySQL. Se o template
        // sugerir dois valores diferentes, o provisionador cria o banco da
        // clínica e a primeira conexão dela já falha.
        $this->assertSame($env['DB_USERNAME'], $env['TENANT_DB_USERNAME']);
        $this->assertSame('', $env['DB_PASSWORD']);
        $this->assertSame('', $env['TENANT_DB_PASSWORD']);
    }

    #[Test]
    public function o_prefixo_de_banco_tenant_esta_presente(): void
    {
        $env = $this->carregarTemplate();

        // O nome do banco já registrado em tenant_connections não pode mudar
        // depois da primeira clínica em produção.
        $this->assertSame('clinic_', $env['TENANT_DB_PREFIX']);
        $this->assertSame('6', (string) $env['TENANT_DB_PADDING']);
    }
}
