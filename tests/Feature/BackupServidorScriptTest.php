<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * O script do servidor (`03-backup.sh`) produziu, em produção, 14 noites
 * seguidas de "backup" que não tinha um byte de dado.
 *
 * A causa foi silenciosa e era evitável: o dump era escrito direto no nome
 * final, e quando o `mysqldump` falhava (senha errada no `~/.my.cnf`) o `gzip`
 * recebia um stream vazio e escrevia 20 bytes — que passam no `gzip -t`. O
 * `set -e` matava o script no primeiro comando, então a etapa de verificação
 * nunca rodava, e o log do cron era sobrescrito todo dia. O resultado eram 14
 * arquivos de 20 bytes e nenhuma pista de por quê.
 *
 * Estes testes travam as três correções: o dump vai para arquivo temporário e
 * só recebe o nome final depois de conferido; a conferência olha o CONTEÚDO
 * descomprimido, não só se o gzip abre; e o log é acumulado, não sobrescrito.
 */
class BackupServidorScriptTest extends TestCase
{
    private function caminhoScript(): string
    {
        return base_path('deploy/vps/03-backup.sh');
    }

    private function conteudoScript(): string
    {
        $this->assertFileExists($this->caminhoScript(), 'O script de backup precisa ser versionado junto do app.');

        return (string) file_get_contents($this->caminhoScript());
    }

    private function localizarBash(): ?string
    {
        $candidatos = [
            'C:\Program Files\Git\bin\bash.exe',
            'C:\Program Files\Git\usr\bin\bash.exe',
            '/usr/bin/bash',
            '/bin/bash',
        ];

        foreach ($candidatos as $candidato) {
            if (is_file($candidato)) {
                return $candidato;
            }
        }

        return null;
    }

    #[Test]
    public function o_script_e_sintaticamente_valido(): void
    {
        $bash = $this->localizarBash();

        if ($bash === null) {
            $this->markTestSkipped('bash nao encontrado nesta maquina.');
        }

        $processo = proc_open(
            [$bash, '-n', $this->caminhoScript()],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        $this->assertIsResource($processo);

        $saida = (string) stream_get_contents($pipes[1]);
        $erro = (string) stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $codigo = proc_close($processo);

        $this->assertSame(0, $codigo, "O 03-backup.sh nao parseia:\n".$saida.$erro);
    }

    #[Test]
    public function a_conferencia_de_conteudo_rejeita_o_dump_vazio_de_producao(): void
    {
        $bash = $this->localizarBash();

        if ($bash === null) {
            $this->markTestSkipped('bash nao encontrado nesta maquina.');
        }

        // O harness extrai a função do ARQUIVO REAL e a exercita. Se a função
        // mudar no repo, este teste muda junto — não testa uma cópia dele.
        $harness = <<<'BASH'
            #!/usr/bin/env bash
            set -uo pipefail

            SCRIPT="$1"

            erro() { printf 'erro: %s\n' "$*" >&2; exit 1; }
            log()  { printf '==> %s\n' "$*"; }

            FUNCAO="$(mktemp)"
            awk '/^verificar_dump\(\)/,/^}/' "$SCRIPT" > "$FUNCAO"

            if [[ ! -s "$FUNCAO" ]]; then
                echo "FUNCAO_NAO_ENCONTRADA"
                exit 1
            fi

            # shellcheck disable=SC1090
            source "$FUNCAO"

            TMP="$(mktemp -d)"
            cd "$TMP" || exit 1

            # O caso de producao: gzip de stream vazio.
            : > vazio.sql
            gzip -c vazio.sql > vazio.sql.gz

            # Um dump de verdade.
            {
                echo "-- MariaDB dump 10.19-11.8.6-MariaDB, for debian-linux-gnu (x86_64)"
                head -c 5000 /dev/zero | tr '\0' 'x'
            } > bom.sql
            gzip -c bom.sql > bom.sql.gz

            # Conteudo grande, mas sem assinatura de dump.
            head -c 5000 /dev/zero | tr '\0' 'x' > sem-assinatura.sql
            gzip -c sem-assinatura.sql > sem-assinatura.sql.gz

            # Gzip corrompido.
            head -c 200 /dev/urandom > lixo.sql.gz

            # Tar de uploads: nao e dump, e nao pode exigir assinatura de dump.
            mkdir -p conteudo
            head -c 5000 /dev/zero > conteudo/arquivo.bin
            tar -czf uploads.tar.gz conteudo

            if (verificar_dump vazio.sql.gz "vazio") >/dev/null 2>&1; then echo "VAZIO_ACEITO"; else echo "VAZIO_REJEITADO"; fi
            if (verificar_dump bom.sql.gz "bom") >/dev/null 2>&1; then echo "BOM_ACEITO"; else echo "BOM_REJEITADO"; fi
            if (verificar_dump sem-assinatura.sql.gz "sem-assinatura") >/dev/null 2>&1; then echo "SEM_ASSINATURA_ACEITO"; else echo "SEM_ASSINATURA_REJEITADO"; fi
            if (verificar_dump lixo.sql.gz "lixo") >/dev/null 2>&1; then echo "LIXO_ACEITO"; else echo "LIXO_REJEITADO"; fi
            if (verificar_dump uploads.tar.gz "uploads") >/dev/null 2>&1; then echo "TAR_ACEITO"; else echo "TAR_REJEITADO"; fi

            cd /tmp || exit 1
            rm -rf "$TMP" "$FUNCAO"
            BASH;

        $arquivo = tempnam(sys_get_temp_dir(), 'lj-harness-').'.sh';

        file_put_contents($arquivo, $harness);

        $processo = proc_open(
            [$bash, $arquivo, $this->caminhoScript()],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        $this->assertIsResource($processo);

        $saida = (string) stream_get_contents($pipes[1]);
        $erro = (string) stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $codigo = proc_close($processo);

        @unlink($arquivo);

        $this->assertSame(0, $codigo, "O harness falhou:\n".$saida.$erro);

        $this->assertStringContainsString('VAZIO_REJEITADO', $saida, 'O dump vazio de producao tem que ser rejeitado.');
        $this->assertStringContainsString('BOM_ACEITO', $saida, 'Um dump de verdade tem que ser aceito.');
        $this->assertStringContainsString('SEM_ASSINATURA_REJEITADO', $saida, 'Arquivo grande sem assinatura de dump tem que ser rejeitado.');
        $this->assertStringContainsString('LIXO_REJEITADO', $saida, 'Gzip corrompido tem que ser rejeitado.');
        $this->assertStringContainsString('TAR_ACEITO', $saida, 'O tar de uploads nao pode ser tratado como dump.');
    }

    #[Test]
    public function o_dump_so_recebe_o_nome_final_depois_de_conferido(): void
    {
        $conteudo = $this->conteudoScript();

        $this->assertStringContainsString(
            '.parcial',
            $conteudo,
            'O dump tem que ir para um arquivo temporario e so receber o nome final depois de conferido.'
        );

        $this->assertStringContainsString(
            'DUMP VAZIO',
            $conteudo,
            'A conferencia precisa rejeitar o dump sem conteudo.'
        );

        $this->assertStringContainsString(
            'dump 10',
            $conteudo,
            'A conferencia precisa exigir a assinatura de dump do MariaDB.'
        );
    }

    #[Test]
    public function o_log_do_cron_e_acumulado_e_nao_sobrescrito(): void
    {
        $conteudo = $this->conteudoScript();

        $this->assertStringContainsString(
            'lj-backup.log 2>&1',
            $conteudo,
            'A linha do cron precisa existir.'
        );

        $this->assertStringContainsString(
            '>> /var/log/lj-backup.log',
            $conteudo,
            'O log do cron tem que ser ACUMULADO (>>). Com >, cada noite apaga a prova da noite anterior.'
        );

        // O `>>` contem o `>` como substring: a checagem precisa do contexto,
        // senao ela acusa o proprio `>>` de sobrescrita.
        $this->assertDoesNotMatchRegularExpression(
            '/[^>]> \/var\/log\/lj-backup\.log/',
            $conteudo,
            'O log do cron nao pode ser sobrescrito (>) — foi assim que 14 falhas sumiram.'
        );
    }

    #[Test]
    public function o_script_deixa_um_status_da_ultima_execucao(): void
    {
        $conteudo = $this->conteudoScript();

        $this->assertStringContainsString(
            'registrar_status',
            $conteudo,
            'O script precisa gravar o resultado da execucao.'
        );

        $this->assertStringContainsString(
            'resultado=',
            $conteudo,
            'O arquivo de status precisa dizer qual foi o resultado.'
        );

        $this->assertStringContainsString(
            "trap 'registrar_status falha",
            $conteudo,
            'Qualquer saida inesperada tem que deixar o status como falha.'
        );
    }
}
