<#
.SYNOPSIS
    Backup externo do LJ SmartClinic: traz o backup do servidor para um destino removivel.

.DESCRIPTION
    O backup do servidor (deploy/vps/03-backup.sh) roda na VPS e fica na MESMA
    maquina que ele protege. Se a VPS se perder, for sequestrada, ou o provedor
    sumir, o backup vai junto. Esse e o cenario mais comum de perda real, e e o
    que este script fecha.

    O script puxa a rotacao mais recente do servidor para o destino removivel
    (o pendrive), confere o SHA-256 de cada arquivo contra o hash calculado NO
    SERVIDOR, e so depois poda as rotacoes antigas do destino.

    As regras que o script respeita, e que os testes cobrem:

      1. Nao apaga nada antes de uma copia verificada com sucesso.
      2. Nao apaga nada fora da pasta de destino.
      3. Nao sai do lugar se o destino nao estiver montado: avisa e termina.
      4. Nunca sobrescreve uma copia ja verificada.
      5. Toda linha copiada passa pelo hash; hash diferente aborta a execucao.
      6. Nao copia dump vazio. Um dump de 20 bytes (gzip de stream vazio) tem
         hash valido e passa no gzip -t: e um backup que nao tem um byte de
         dado. Isto aconteceu em producao por 14 noites seguidas.

    Nao inventa nada: o script nao cria conta, nao sobe servico e nao mexe no
    servidor. Ele so le (ssh/scp) e escreve no destino.

    Nota de compatibilidade: este arquivo e ASCII puro de proposito. O Windows
    PowerShell 5.1 le .ps1 como ANSI quando o arquivo nao tem BOM, e um acento
    em UTF-8 vira aspas na hora do parse. Em PowerShell 7 (`pwsh`) nao ha
    esse problema.

.PARAMETER Destino
    Pasta removivel onde a copia externa e gravada.

.PARAMETER Servidor
    Usuario@host da VPS, no formato que o ssh aceita.

.PARAMETER Manter
    Quantas rotacoes antigas continuam no destino. A mais recente nunca e
    removida, entao o numero real de copias fica em Manter + 1.

.PARAMETER HorasMaximas
    Idade maxima aceitavel da rotacao mais recente. Acima disso o script
    avisa que o backup do servidor pode ter parado.

.PARAMETER Simular
    Mostra o que seria feito e nao toca em nada: nem na rede, nem no destino.

.PARAMETER ConferirDump
    Confere um dump .sql.gz que ja esta no destino (ou em qualquer pasta) e
    sai com codigo 0 se ele tiver conteudo, 1 se estiver vazio. Serve para
    auditar uma copia antiga sem precisar do servidor.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File deploy\externo\backup-externo.ps1

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File deploy\externo\backup-externo.ps1 -Simular

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File deploy\externo\backup-externo.ps1 -ConferirDump 'D:\...\central-20261007-031701.sql.gz'

.NOTES
    O destino e FAT32. Dump acima de 4 GB nao caberia num arquivo so: o
    script avisa se um unico arquivo passar de 3,5 GB, porque nesse caso o
    destino precisaria ser reformatado ou a copia passaria a ser em partes.
#>

[CmdletBinding()]
param(
    [string] $Destino = 'D:\ljsmartclinic-backup-externo',
    [string] $Servidor = 'root@179.236.238.61',
    [string] $DiretorioRemoto = '/var/backups/ljsmartclinic',
    [int]    $Manter = 7,
    [int]    $HorasMaximas = 26,
    [switch] $Simular,
    [string] $ConferirDump
)

$ErrorActionPreference = 'Stop'

# --- Conteudo de um dump local -----------------------------------------------

function Get-TamanhoDescomprimido {
    <#
        Quantos bytes o arquivo tem DEPOIS de descomprimido.

        E esta medida que separa um backup de um arquivo vazio: 20 bytes de
        gzip descomprimem para zero byte. Sem este numero, um backup que nao
        tem dado nenhum passa como se fosse o melhor do mundo.
    #>
    param([string] $Caminho)

    Add-Type -AssemblyName System.IO.Compression -ErrorAction SilentlyContinue

    $fluxo = [System.IO.File]::OpenRead($Caminho)

    try {
        $gzip = New-Object System.IO.Compression.GZipStream(
            $fluxo,
            [System.IO.Compression.CompressionMode]::Decompress
        )

        try {
            $buffer = New-Object byte[] 65536
            $total = 0

            while (($lidos = $gzip.Read($buffer, 0, $buffer.Length)) -gt 0) {
                $total += $lidos
            }

            return $total
        }
        finally {
            $gzip.Dispose()
        }
    }
    finally {
        $fluxo.Dispose()
    }
}

# --- Log ---------------------------------------------------------------------

function Write-Log {
    param(
        [string] $Mensagem,
        [ValidateSet('INFO', 'AVISO', 'ERRO', 'OK')]
        [string] $Nivel = 'INFO'
    )

    $cores = @{
        INFO = ''
        AVISO = [char]27 + '[33m'
        ERRO  = [char]27 + '[31m'
        OK    = [char]27 + '[32m'
    }

    $linha = '{0} {1} {2}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Nivel, $Mensagem

    if ($cores[$Nivel]) {
        Write-Host ($cores[$Nivel] + $linha + [char]27 + '[0m')
        return
    }

    Write-Host $linha
}

# --- Execucao de comando nativo (ssh/scp) -------------------------------------

function Invoke-Nativo {
    <#
        Executa um comando nativo capturando stdout, stderr e codigo de saida.

        O Windows PowerShell 5.1 transforma stderr de comando nativo em
        ErrorRecord quando o redirecionamento e 2>&1, e com
        ErrorActionPreference = 'Stop' isso vira excecao. Aqui a preferencia
        e relaxada durante a chamada e restaurada depois, para que o erro
        saia pelo codigo de saida, que e o que interessa.
    #>
    param(
        [string]   $Executavel,
        [string[]] $Argumentos
    )

    $preferenciaAnterior = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'

    try {
        $saida = & $Executavel @Argumentos 2>&1
        $codigo = $LASTEXITCODE
    }
    finally {
        $ErrorActionPreference = $preferenciaAnterior
    }

    return [PSCustomObject]@{
        Codigo = $codigo
        Saida  = @($saida | ForEach-Object { $_.ToString() })
    }
}

# --- Verificacao de integridade do arquivo copiado ----------------------------

function Testar-Arquivo {
    <#
        Confere o arquivo local contra o hash que o SERVIDOR calculou.

        Comparar o hash do servidor com o hash local e a unica prova de que o
        caminho todo (ssh, scp, pendrive) entregou o mesmo byte. Conferir
        contra o hash do proprio arquivo local nao provaria nada.
    #>
    param(
        [string] $Caminho,
        [string] $HashEsperado
    )

    if (-not (Test-Path -LiteralPath $Caminho)) {
        throw "Arquivo ausente no destino: $Caminho"
    }

    $hashLocal = (Get-FileHash -LiteralPath $Caminho -Algorithm SHA256).Hash

    if ($hashLocal -ne $HashEsperado) {
        throw "Hash divergente em $([System.IO.Path]::GetFileName($Caminho)): servidor $HashEsperado, local $hashLocal"
    }

    return $true
}

# --- SSH ----------------------------------------------------------------------

function Invoke-Ssh {
    <#
        Roda um comando no servidor. BatchMode impede a senha interativa: em
        tarefa agendada nao existe ninguem para digitar a senha, e o script
        precisa falhar rapido em vez de travar.
    #>
    param([string] $Comando)

    $resultado = Invoke-Nativo -Executavel 'ssh' -Argumentos @(
        '-o', 'BatchMode=yes',
        '-o', 'ConnectTimeout=20',
        $Servidor,
        $Comando
    )

    if ($resultado.Codigo -ne 0) {
        throw "Falha no ssh para ${Servidor}: $($resultado.Saida -join ' ')"
    }

    return $resultado.Saida
}

# --- Inicio -------------------------------------------------------------------

Write-Host ''
Write-Host '=== Backup externo - LJ SmartClinic ===' -ForegroundColor Cyan
Write-Log "Servidor: $Servidor"
Write-Log "Diretorio remoto: $DiretorioRemoto"
Write-Log "Destino: $Destino"
Write-Log "Manter: $Manter rotacoes antigas"

# --- Conferencia de um dump local --------------------------------------------
#
# Modo de diagnostico: nao usa a rede, nao escreve nada. Serve para auditar
# uma copia antiga do pendrive e descobrir que ela esta vazia.

if ($ConferirDump) {
    if (-not (Test-Path -LiteralPath $ConferirDump)) {
        Write-Log "Arquivo nao encontrado: $ConferirDump" -ERRO
        exit 1
    }

    $bytes = Get-TamanhoDescomprimido $ConferirDump

    Write-Host ''
    Write-Log "Arquivo: $ConferirDump"
    Write-Log "Descomprimido: $bytes byte(s)"

    if ($bytes -lt 1000) {
        Write-Log 'VAZIO: este dump nao tem dado. Nao e um backup.' -ERRO
        exit 1
    }

    Write-Log 'OK: o dump tem conteudo.' -OK
    Write-Host ''
    exit 0
}

if ($Simular) {
    Write-Host ''
    Write-Log 'MODO SIMULACAO: nada sera copiado, verificado ou apagado.' -AVISO
    Write-Host ''
    exit 0
}

# --- Destino tem que estar montado --------------------------------------------
#
# "Destino ausente" tem duas causas bem diferentes: o pendrive nao esta
# conectado, ou a pasta de destino ainda nao existe. A segunda e um caso
# normal de primeira execucao; a primeira e o caso em que o script tem de
# parar sem fazer nada.

$raizDestino = [System.IO.Path]::GetPathRoot($Destino)

if ($raizDestino -and -not (Test-Path -LiteralPath $raizDestino)) {
    Write-Host ''
    Write-Log "Destino nao montado: $raizDestino nao existe." -AVISO
    Write-Log 'Se o destino for um pendrive, ele precisa estar conectado.' -AVISO
    Write-Log 'Nada foi feito e nada foi apagado.' -AVISO
    Write-Host ''
    exit 0
}

if (-not (Test-Path -LiteralPath $Destino)) {
    Write-Log "Criando a pasta de destino $Destino"
    New-Item -ItemType Directory -Path $Destino -Force | Out-Null
}

# --- Rotacao mais recente no servidor ------------------------------------------

Write-Host ''
Write-Log 'Listando as rotacoes no servidor...'

$arquivos = Invoke-Ssh "ls -1 $DiretorioRemoto"

# O nome do arquivo carrega o rotulo da rotacao: central-AAAAMMDD-HHMMSS.sql.gz
$rotacoes = $arquivos | ForEach-Object {
    if ($_ -match '(\d{8}-\d{6})') {
        [PSCustomObject]@{
            Rotulo  = $Matches[1]
            Arquivo = $_.Trim()
        }
    }
} | Group-Object Rotulo | Sort-Object Name -Descending

if (-not $rotacoes) {
    throw "Nenhuma rotacao de backup encontrada em ${Servidor}:${DiretorioRemoto}"
}

$rotacaoAtual = $rotacoes[0].Name
$arquivosDaRotacao = @($rotacoes[0].Group.Arquivo)

Write-Log "Rotacao mais recente: $rotacaoAtual ($($arquivosDaRotacao.Count) arquivo(s))"

# Se a rotacao mais recente for velha demais, o backup do servidor pode ter
# parado. O script avisa em vez de ficar quieto: um backup de dois dias atras
# parece um backup ate o dia em que faz falta.
$idade = [int]((Get-Date) - [datetime]::ParseExact(
    $rotacaoAtual,
    'yyyyMMdd-HHmmss',
    [System.Globalization.CultureInfo]::InvariantCulture
)).TotalHours

if ($idade -gt $HorasMaximas) {
    Write-Log "A rotacao mais recente tem ${idade}h - o backup do servidor pode ter parado." -AVISO
    Write-Log "Confira o cron em ${Servidor}: /etc/cron.d/ljsmartclinic-backup" -AVISO
}

# --- Hash no servidor ----------------------------------------------------------

Write-Log 'Calculando os hashes no servidor...'

$comandoHash = "cd $DiretorioRemoto; sha256sum $($arquivosDaRotacao -join ' ')"
$linhasHash = Invoke-Ssh $comandoHash

$hashes = @{}

foreach ($linha in $linhasHash) {
    if ($linha -match '^([a-fA-F0-9]{64})\s+(.+)$') {
        $hashes[$Matches[2].Trim()] = $Matches[1].ToUpper()
    }
}

if ($hashes.Count -ne $arquivosDaRotacao.Count) {
    throw "O servidor devolveu $($hashes.Count) hash para $($arquivosDaRotacao.Count) arquivo(s)."
}

# --- O dump tem conteudo? -----------------------------------------------------
#
# O hash confere que o arquivo chegou inteiro. NAO diz que ele tem dado.
#
# Em producao houve 14 noites seguidas com o dump central de 20 bytes: gzip de
# stream vazio, que passa no `gzip -t` e tem hash perfeito. Copiar aquilo para
# o pendrive e chamar de backup e trocar um backup falso por outro.
#
# Antes de copiar, o script pergunta ao servidor quantos bytes cada dump tem
# DESCOMPRIMIDOS e recusa a copia se o numero nao fizer sentido. E le o STATUS
# que o script do servidor escreve, para saber se a ultima execucao de la deu
# certo.

Write-Log 'Conferindo se os dumps tem conteudo...'

$arquivosDump = @($arquivosDaRotacao | Where-Object { $_ -match '\.sql\.gz$' })

$tamanhosDescomprimidos = @{}

if ($arquivosDump.Count -gt 0) {
    $comandoTamanho = 'cd {0}; for f in {1}; do echo "$(gzip -dc "$f" | wc -c) $f"; done' -f `
        $DiretorioRemoto, ($arquivosDump -join ' ')

    foreach ($linha in (Invoke-Ssh $comandoTamanho)) {
        if ($linha -match '^(\d+)\s+(\S+)$') {
            $tamanhosDescomprimidos[$Matches[2]] = [int64]$Matches[1]
        }
    }

    if ($tamanhosDescomprimidos.Count -ne $arquivosDump.Count) {
        throw "O servidor nao informou o conteudo de $($arquivosDump.Count) dump(s)."
    }

    foreach ($arquivo in $arquivosDump) {
        $tamanho = $tamanhosDescomprimidos[$arquivo]

        if ($tamanho -lt 1000) {
            throw ("O dump $arquivo esta VAZIO no servidor ($tamanho bytes descomprimidos). " +
                'Um backup sem dado nao e backup: a copia abortada antes de estragar o destino.')
        }
    }

    Write-Log "Todos os dumps tem conteudo (menor: $(($tamanhosDescomprimidos.Values | Measure-Object -Minimum).Minimum) bytes descomprimidos)" -OK
}

# O STATUS e o que o script do servidor grava sobre a ultima execucao dele.
# Ele nao existe em instalacao antiga: nesse caso o script segue, porque a
# conferencia de conteudo acima ja foi feita.
$linhasStatus = @(Invoke-Ssh "cat $DiretorioRemoto/STATUS 2>/dev/null || true")

$resultadoStatus = ($linhasStatus | Where-Object { $_ -match '^resultado=' } | Select-Object -First 1) -replace '^resultado=', ''

if ($resultadoStatus -eq 'falha') {
    $detalhe = ($linhasStatus | Where-Object { $_ -match '^detalhe=' } | Select-Object -First 1) -replace '^detalhe=', ''

    throw "O ultimo backup do servidor falhou ($detalhe). A copia externa foi abortada."
}

if ($resultadoStatus) {
    Write-Log "Status do servidor: $resultadoStatus"
}

# --- Copia ---------------------------------------------------------------------

$pastaRotacao = Join-Path $Destino $rotacaoAtual
$marcador = Join-Path $pastaRotacao 'VERIFICACAO.txt'

# A pasta existe E tem o marcador de verificacao: esta copia ja foi conferida.
# A pasta existe sem o marcador: a copia anterior foi interrompida no meio.
# Confundir as duas faz o script nunca mais copiar aquela rotacao.
if ((Test-Path -LiteralPath $pastaRotacao) -and (Test-Path -LiteralPath $marcador)) {
    Write-Log "A rotacao $rotacaoAtual ja existe no destino e ja foi verificada. Nada a fazer." -OK
    exit 0
}

if (Test-Path -LiteralPath $pastaRotacao) {
    Write-Log "A rotacao $rotacaoAtual esta incompleta no destino (copia interrompida). Refazendo." -AVISO
    Remove-Item -LiteralPath $pastaRotacao -Recurse -Force
}

New-Item -ItemType Directory -Path $pastaRotacao -Force | Out-Null

Write-Log "Copiando para $pastaRotacao"

foreach ($arquivo in $arquivosDaRotacao) {
    Write-Log "  $arquivo"

    $origem = "${Servidor}:${DiretorioRemoto}/$arquivo"
    $destinoArquivo = Join-Path $pastaRotacao $arquivo

    $copia = Invoke-Nativo -Executavel 'scp' -Argumentos @(
        '-o', 'BatchMode=yes',
        '-o', 'ConnectTimeout=20',
        $origem,
        $destinoArquivo
    )

    if ($copia.Codigo -ne 0) {
        throw "Falha ao copiar $arquivo do servidor: $($copia.Saida -join ' ')"
    }

    $tamanho = [math]::Round((Get-Item -LiteralPath $destinoArquivo).Length / 1MB, 2)

    if ($tamanho -gt 3500) {
        Write-Log "ATENCAO: $arquivo tem $tamanho MB. Em FAT32 (limite de 4 GB por arquivo) isso ja aperta." -AVISO
    }

    Testar-Arquivo -Caminho $destinoArquivo -HashEsperado $hashes[$arquivo] | Out-Null

    Write-Log "    ok ($tamanho MB, hash confere com o servidor)" -OK
}

# --- Manifesto -----------------------------------------------------------------

$manifesto = Join-Path $pastaRotacao 'SHA256SUMS.txt'

$linhasManifesto = $arquivosDaRotacao | ForEach-Object {
    $hashLocal = (Get-FileHash -LiteralPath (Join-Path $pastaRotacao $_) -Algorithm SHA256).Hash.ToLower()
    "$hashLocal  $_"
}

[System.IO.File]::WriteAllLines($manifesto, $linhasManifesto)

$verificacao = Join-Path $pastaRotacao 'VERIFICACAO.txt'

@(
    "Rotacao: $rotacaoAtual",
    "Origem: ${Servidor}:${DiretorioRemoto}",
    "Copiado em: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')",
    "Arquivos: $($arquivosDaRotacao.Count)",
    "SHA-256: conferido arquivo a arquivo contra o hash calculado no servidor",
    'Conteudo: conferido no servidor antes de copiar (bytes descomprimidos)',
    "Resultado: OK",
    '',
    'Tamanho descomprimido de cada dump:'
) | ForEach-Object {
    [System.IO.File]::AppendAllText($verificacao, $_ + "`r`n")
}

foreach ($arquivo in $arquivosDump) {
    $linha = "  {0}  {1} bytes" -f $arquivo, $tamanhosDescomprimidos[$arquivo]
    [System.IO.File]::AppendAllText($verificacao, $linha + "`r`n")
}

[System.IO.File]::AppendAllText($verificacao, "`r`n")

@(
    'Para conferir de novo, a qualquer momento:',
    '  Get-FileHash -Algorithm SHA256 [arquivo]',
    'e compare com a linha correspondente do SHA256SUMS.txt'
) | ForEach-Object {
    [System.IO.File]::AppendAllText($verificacao, $_ + "`r`n")
}

Write-Log "Manifesito escrito em $manifesto" -OK

# --- Poda das rotacoes antigas --------------------------------------------------
#
# A poda so acontece DEPOIS de uma copia verificada. Se a copia falhou, o
# script chegou aqui por outra via: nunca. E ela nunca toca na rotacao mais
# recente, nem em nada fora da pasta de destino.

Write-Host ''
Write-Log 'Podando as rotacoes antigas do destino...'

$pastas = Get-ChildItem -LiteralPath $Destino -Directory -ErrorAction SilentlyContinue |
    Sort-Object Name -Descending

$mantidas = 0
$removidas = 0

foreach ($pasta in $pastas) {
    $mantidas++

    if ($mantidas -le ($Manter + 1)) {
        continue
    }

    # Defesa em profundidade: a remocao so acontece dentro do destino, e so
    # em pasta que tenha o nome de uma rotacao (data + hora).
    $dentroDoDestino = $pasta.FullName.StartsWith($Destino, [System.StringComparison]::OrdinalIgnoreCase)
    $nomeDeRotacao = $pasta.Name -match '^\d{8}-\d{6}$'

    if (-not $dentroDoDestino -or -not $nomeDeRotacao) {
        Write-Log "Ignorando pasta inesperada: $($pasta.FullName)" -AVISO
        continue
    }

    Remove-Item -LiteralPath $pasta.FullName -Recurse -Force
    $removidas++

    Write-Log "  removida $($pasta.Name)" -AVISO
}

Write-Log "Rotacoes no destino: $mantidas (removidas nesta execucao: $removidas)"

# --- Registro ------------------------------------------------------------------

$registro = Join-Path $Destino 'registro.log'

$linhaRegistro = '{0} rotacao={1} arquivos={2} pods={3} resultado=OK' -f `
    (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $rotacaoAtual, $arquivosDaRotacao.Count, $removidas

[System.IO.File]::AppendAllText($registro, $linhaRegistro + "`r`n")

Write-Host ''
Write-Log "Backup externo concluido em $pastaRotacao" -OK
Write-Host ''
