<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * O backup externo é o script que tira o backup da VPS de dentro da VPS.
 *
 * O `03-backup.sh` roda na própria máquina que ele protege: protege contra
 * banco corrompido e migration errada, e não contra a perda do servidor. O
 * `backup-externo.ps1` fecha essa janela, puxando a rotação para um destino
 * removível e conferindo o SHA-256 contra o hash calculado no servidor.
 *
 * Um script de backup que apaga a cópia boa antes de conferir a nova é pior
 * que não ter script. Estes testes existem para travar as garantias que ele
 * promete — e, antes de tudo, para garantir que ele PARSE: um `.ps1` com
 * acento e sem BOM é lido como ANSI pelo Windows PowerShell 5.1, e um acento
 * em UTF-8 vira aspas no meio do arquivo, derrubando o script inteiro.
 */
class BackupExternoScriptTest extends TestCase
{
    private function caminhoScript(): string
    {
        return base_path('deploy/externo/backup-externo.ps1');
    }

    /**
     * Conteúdo do script, com o BOM removido se houver.
     */
    private function conteudoScript(): string
    {
        $this->assertFileExists($this->caminhoScript(), 'O script de backup externo precisa ser versionado junto do app.');

        return (string) file_get_contents($this->caminhoScript());
    }

    private function localizarPowerShell(): ?string
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return null;
        }

        $candidatos = [
            'C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe',
            'C:\Program Files\PowerShell\7\pwsh.exe',
        ];

        foreach ($candidatos as $candidato) {
            if (is_file($candidato)) {
                return $candidato;
            }
        }

        return null;
    }

    /**
     * Executa um comando nativo sem passar pelo shell, para que caminho com
     * espaço não quebre o argumento.
     *
     * @param  list<string>  $argumentos
     * @return array{codigo: int, saida: string, erro: string}
     */
    private function executar(array $argumentos): array
    {
        $processo = proc_open(
            $argumentos,
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        $this->assertIsResource($processo, 'proc_open precisa estar habilitado para testar o script.');

        $saida = (string) stream_get_contents($pipes[1]);
        $erro = (string) stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $codigo = proc_close($processo);

        return ['codigo' => $codigo, 'saida' => $saida, 'erro' => $erro];
    }

    #[Test]
    public function o_script_e_ascii_puro(): void
    {
        $conteudo = $this->conteudoScript();

        $naoAscii = preg_match('/[^\x00-\x7F]/', $conteudo);

        $this->assertSame(
            0,
            $naoAscii,
            'O Windows PowerShell 5.1 lê .ps1 sem BOM como ANSI. Um acento em UTF-8 vira '
            .'aspas no meio do arquivo e o script inteiro deixa de ser analisado. '
            .'Mantenha o arquivo em ASCII puro.'
        );
    }

    #[Test]
    public function o_script_nao_usa_operador_que_o_powershell_51_nao_entende(): void
    {
        $conteudo = $this->conteudoScript();

        $this->assertStringNotContainsString(
            '&&',
            $conteudo,
            'O operador && só existe no PowerShell 7. No 5.1 ele é erro de análise, '
            .'e o script não roda em nenhuma máquina com o Windows padrão.'
        );
    }

    #[Test]
    public function o_script_e_analisavel_pelo_powershell(): void
    {
        $powershell = $this->localizarPowerShell();

        if ($powershell === null) {
            $this->markTestSkipped('PowerShell nao encontrado nesta maquina.');
        }

        $checker = tempnam(sys_get_temp_dir(), 'lj-parser-').'.ps1';

        file_put_contents($checker, sprintf(
            '$erros = $null'."\r\n"
            .'[System.Management.Automation.Language.Parser]::ParseFile(%s, [ref]$null, [ref]$erros) | Out-Null'."\r\n"
            .'if ($erros -and $erros.Count -gt 0) { $erros | ForEach-Object { $_.Message }; exit 1 }'."\r\n"
            .'exit 0',
            "'".addcslashes($this->caminhoScript(), "'")."'"
        ));

        try {
            $resultado = $this->executar([$powershell, '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $checker]);
        } finally {
            @unlink($checker);
        }

        $this->assertSame(
            0,
            $resultado['codigo'],
            "O script de backup externo nao e analisado pelo PowerShell:\n".$resultado['saida'].$resultado['erro']
        );
    }

    #[Test]
    public function a_execucao_de_simulacao_nao_toca_em_nada(): void
    {
        $powershell = $this->localizarPowerShell();

        if ($powershell === null) {
            $this->markTestSkipped('PowerShell nao encontrado nesta maquina.');
        }

        $destino = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lj-backup-externo-'.uniqid();

        $resultado = $this->executar([
            $powershell,
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-File',
            $this->caminhoScript(),
            '-Simular',
            '-Destino',
            $destino,
        ]);

        $this->assertSame(
            0,
            $resultado['codigo'],
            "A simulacao do backup externo falhou:\n".$resultado['saida'].$resultado['erro']
        );

        $this->assertStringContainsString('MODO SIMULACAO', $resultado['saida']);

        $this->assertDirectoryDoesNotExist(
            $destino,
            'A simulacao criou a pasta de destino. Ela precisa nao tocar em nada, nem no destino.'
        );
    }

    #[Test]
    public function o_script_verifica_o_hash_antes_de_apagar_rotacao_antiga(): void
    {
        $conteudo = $this->conteudoScript();

        $posicaoVerificacao = strpos($conteudo, 'Testar-Arquivo -Caminho $destinoArquivo');
        $posicaoRemocao = strpos($conteudo, 'Remove-Item -LiteralPath $pasta.FullName');

        $this->assertNotFalse($posicaoVerificacao, 'O script precisa conferir o hash do arquivo copiado.');
        $this->assertNotFalse($posicaoRemocao, 'O script precisa ter a poda das rotacoes antigas.');

        $this->assertLessThan(
            $posicaoRemocao,
            $posicaoVerificacao,
            'A remocao de uma rotacao antiga esta antes da verificacao de hash. '
            .'O script apagaria a copia boa antes de conferir a nova.'
        );
    }

    #[Test]
    public function a_poda_so_atinge_pasta_de_rotacao_dentro_do_destino(): void
    {
        $conteudo = $this->conteudoScript();

        $this->assertStringContainsString(
            'StartsWith($Destino, [System.StringComparison]::OrdinalIgnoreCase)',
            $conteudo,
            'A remocao precisa conferir que a pasta esta dentro do destino.'
        );

        $this->assertStringContainsString(
            "^\d{8}-\d{6}$",
            $conteudo,
            'A remocao precisa exigir o nome de rotacao (AAAAMMDD-HHMMSS) na pasta.'
        );
    }

    #[Test]
    public function o_script_nao_pede_senha_interativa(): void
    {
        $conteudo = $this->conteudoScript();

        // Em tarefa agendada nao existe ninguem para digitar a senha: o script
        // precisa falhar rapido, e nao travar esperando por ela.
        $this->assertSame(
            2,
            substr_count($conteudo, 'BatchMode=yes'),
            'ssh e scp precisam de BatchMode=yes para nao travar em tarefa agendada.'
        );
    }

    #[Test]
    public function o_script_recusa_um_dump_vazio(): void
    {
        $powershell = $this->localizarPowerShell();

        if ($powershell === null) {
            $this->markTestSkipped('PowerShell nao encontrado nesta maquina.');
        }

        // O dump de 20 bytes que a producao gerou por 14 noites: gzip de stream
        // vazio. Tem hash valido e passa no gzip -t, e nao tem um byte de dado.
        // gzencode() e nao gzcompress(): o segundo produz zlib, nao gzip.
        $arquivo = tempnam(sys_get_temp_dir(), 'lj-vazio-').'.sql.gz';

        file_put_contents($arquivo, gzencode(''));

        try {
            $resultado = $this->executar([
                $powershell,
                '-NoProfile',
                '-ExecutionPolicy',
                'Bypass',
                '-File',
                $this->caminhoScript(),
                '-ConferirDump',
                $arquivo,
            ]);
        } finally {
            @unlink($arquivo);
        }

        $this->assertSame(
            1,
            $resultado['codigo'],
            "O script aceitou um dump vazio:\n".$resultado['saida'].$resultado['erro']
        );

        $this->assertStringContainsString(
            'VAZIO',
            $resultado['saida'].$resultado['erro'],
            'O script precisa dizer que o dump esta vazio.'
        );
    }

    #[Test]
    public function o_script_aceita_um_dump_com_conteudo(): void
    {
        $powershell = $this->localizarPowerShell();

        if ($powershell === null) {
            $this->markTestSkipped('PowerShell nao encontrado nesta maquina.');
        }

        $arquivo = tempnam(sys_get_temp_dir(), 'lj-dump-').'.sql.gz';

        file_put_contents($arquivo, gzencode("-- MariaDB dump 10.19-11.8.6-MariaDB\n".str_repeat('x', 5000)));

        try {
            $resultado = $this->executar([
                $powershell,
                '-NoProfile',
                '-ExecutionPolicy',
                'Bypass',
                '-File',
                $this->caminhoScript(),
                '-ConferirDump',
                $arquivo,
            ]);
        } finally {
            @unlink($arquivo);
        }

        $this->assertSame(
            0,
            $resultado['codigo'],
            "O script recusou um dump valido:\n".$resultado['saida'].$resultado['erro']
        );

        $this->assertStringContainsString('OK', $resultado['saida']);
    }

    #[Test]
    public function a_conferencia_de_conteudo_roda_antes_da_copia(): void
    {
        $conteudo = $this->conteudoScript();

        $posicaoConferencia = strpos($conteudo, 'Conferindo se os dumps tem conteudo');
        $posicaoCopia = strpos($conteudo, "Executavel 'scp'");

        $this->assertNotFalse($posicaoConferencia, 'O script precisa conferir o conteudo dos dumps.');
        $this->assertNotFalse($posicaoCopia, 'O script precisa copiar os arquivos.');

        $this->assertLessThan(
            $posicaoCopia,
            $posicaoConferencia,
            'A conferencia de conteudo esta depois da copia. O destino receberia um backup vazio antes de ele ser recusado.'
        );
    }

    #[Test]
    public function o_readme_documenta_o_uso_e_a_restaura(): void
    {
        $readme = base_path('deploy/externo/README.md');

        $this->assertFileExists($readme, 'O backup externo precisa de README com o caminho de uso.');

        $conteudo = (string) file_get_contents($readme);

        foreach (['-Simular', 'Register-ScheduledTask', 'gunzip', 'SHA256SUMS.txt'] as $trecho) {
            $this->assertStringContainsString(
                $trecho,
                $conteudo,
                "O README do backup externo precisa cobrir '{$trecho}'."
            );
        }
    }
}
