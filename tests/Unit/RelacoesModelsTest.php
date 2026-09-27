<?php

namespace Tests\Unit;

use App\Models\Central\Clinic as CentralClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use SplFileInfo;
use Tests\TestCase;

/**
 * Rede de segurança para o destino das relações dos models.
 *
 * `belongsTo(Clinica::class)` não resolve a classe em tempo de compilação: o
 * PHP grava apenas a string `Clinica::class`. Se o import some — ou nunca
 * existiu — a referência passa a resolver para `App\Models\Clinica`, que não
 * existe, e o erro só aparece quando alguém chama a relação, muito longe de
 * quem renomeou a classe. Durante a migração para `App\Models\Central\Clinica`
 * isso deixou duas relações quebradas sem quebrar nenhum teste.
 *
 * Aqui cada relação é realmente instanciada e o model do outro lado é resolvido,
 * então uma classe faltando aparece como falha de teste.
 */
class RelacoesModelsTest extends TestCase
{
    public function test_todas_as_relacoes_apontam_para_uma_classe_existente(): void
    {
        $quebradas = [];

        foreach ($this->classesDeModel() as $classe) {
            foreach ($this->metodosDeRelacao($classe) as $metodo) {
                try {
                    // `new` puro: construir a relação não a executa nem a
                    // anexa ao model, então não é preciso instance sem relações.
                    $relacao = (new $classe)->{$metodo}();

                    if (! $relacao instanceof Relation) {
                        continue;
                    }

                    // A classe do outro lado só é carregada aqui.
                    $relacao->getRelated();
                } catch (\Throwable $e) {
                    if (! $this->eClasseAusente($e)) {
                        // Método que exige argumento, coluna faltando no schema
                        // de teste, etc. Não é o problema que este teste caça.
                        continue;
                    }

                    $quebradas[] = sprintf('%s::%s() -> %s', $classe, $metodo, $this->resumir($e));
                }
            }
        }

        $this->assertSame(
            [],
            $quebradas,
            "Relações apontando para classes inexistentes:\n".implode("\n", $quebradas),
        );
    }

    public function test_o_teste_percebe_uma_relacao_quebrada(): void
    {
        // A clínica foi para o namespace central; o nome antigo não existe mais.
        // `class_exists` com autoload ligado dispararia o include do Composer e
        // o PHPUnit transformaria o warning em erro, então o teste olha o disco.
        $this->assertTrue(class_exists(CentralClinic::class));
        $this->assertFileDoesNotExist(
            $this->raizDoProjeto().DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Models'.DIRECTORY_SEPARATOR.'Clinica.php',
        );
    }

    /**
     * @return list<class-string<Model>>
     */
    private function classesDeModel(): array
    {
        $classes = [];

        foreach ($this->arquivosDeModel() as $arquivo) {
            $classe = $this->classeDoArquivo($arquivo);

            if ($classe === null) {
                continue;
            }

            if (is_subclass_of($classe, Model::class)) {
                $classes[] = $classe;
            }
        }

        sort($classes);

        return $classes;
    }

    /**
     * @param  class-string<Model>  $classe
     * @return list<string>
     */
    private function metodosDeRelacao(string $classe): array
    {
        $metodos = [];

        foreach ((new ReflectionClass($classe))->getMethods(ReflectionMethod::IS_PUBLIC) as $metodo) {
            if (str_starts_with($metodo->getName(), '__')) {
                continue;
            }

            $retorno = $metodo->getReturnType();

            if ($retorno instanceof ReflectionNamedType && is_subclass_of($retorno->getName(), Relation::class)) {
                $metodos[] = $metodo->getName();
            }
        }

        return $metodos;
    }

    /**
     * A classe do outro lado pode faltar de duas formas, e o PHP reporta as duas
     * de jeitos diferentes: `Class "X" not found` quando o autoloader nem tenta
     * abrir nada, e um warning de "Failed to open stream" quando ele tenta abrir
     * um arquivo que não existe. As duas são o mesmo defeito.
     */
    private function eClasseAusente(\Throwable $e): bool
    {
        $mensagem = $e->getMessage();

        return str_contains($mensagem, 'Class "') && str_contains($mensagem, 'not found')
            || str_contains($mensagem, 'Failed to open stream');
    }

    /**
     * A mensagem do autoloader vem com o caminho absoluto do vendor, que só
     * polui a saída da falha.
     */
    private function resumir(\Throwable $e): string
    {
        $mensagem = $e->getMessage();

        if (preg_match('#include\((.+?)\):#', $mensagem, $include)) {
            $caminho = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $include[1]);
            $caminho = str_replace($this->raizDoProjeto().DIRECTORY_SEPARATOR, '', $caminho);

            return 'classe ausente (esperava o arquivo '.$caminho.')';
        }

        return $mensagem;
    }

    /**
     * @return list<string>
     */
    private function arquivosDeModel(): array
    {
        $arquivos = [];

        foreach ($this->iterarPhp($this->raizDoProjeto().DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Models') as $arquivo) {
            $arquivos[] = $arquivo->getPathname();
        }

        return $arquivos;
    }

    /**
     * @return list<SplFileInfo>
     */
    private function iterarPhp(string $diretorio): array
    {
        if (! is_dir($diretorio)) {
            return [];
        }

        $arquivos = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($diretorio)) as $arquivo) {
            if ($arquivo instanceof SplFileInfo && $arquivo->getExtension() === 'php') {
                $arquivos[] = $arquivo;
            }
        }

        return $arquivos;
    }

    private function raizDoProjeto(): string
    {
        return dirname(__DIR__, 2);
    }

    private function classeDoArquivo(string $caminho): ?string
    {
        $codigo = (string) file_get_contents($caminho);

        if (! preg_match('/^namespace\s+([^;]+);/m', $codigo, $namespace)) {
            return null;
        }

        if (! preg_match('/^(?:final\s+|abstract\s+)?class\s+(\w+)/m', $codigo, $classe)) {
            return null;
        }

        $fqcn = trim($namespace[1]).'\\'.$classe[1];

        return class_exists($fqcn) ? $fqcn : null;
    }
}
