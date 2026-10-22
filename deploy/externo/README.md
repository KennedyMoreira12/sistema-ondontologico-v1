# Backup externo — LJ SmartClinic

O backup do servidor (`deploy/vps/03-backup.sh`) roda na VPS, todo dia às 3:17,
e deixa o dump em `/var/backups/ljsmartclinic`. Ele protege contra banco
corrompido, migration errada e `DROP DATABASE` acidental.

Ele **não** protege contra a perda da VPS: o dump está na mesma máquina que ele
protege. Se o provedor sumir, o servidor for sequestrado ou o disco do host
morrer, o backup vai junto. O próprio `03-backup.sh` avisa isso no fim da
execução.

Este diretório fecha essa lacuna: um script que traz a rotação mais recente
para um **destino removível** (o pendrive), confere o SHA-256 de cada arquivo
contra o hash calculado **no servidor**, e só depois poda as cópias antigas.

## Como rodar

Na máquina onde o pendrive está conectado:

```powershell
powershell -ExecutionPolicy Bypass -File deploy\externo\backup-externo.ps1
```

Com `-Simular`, o script mostra o que faria e não toca em nada — nem na rede,
nem no destino:

```powershell
powershell -ExecutionPolicy Bypass -File deploy\externo\backup-externo.ps1 -Simular
```

Parâmetros que mudam o comportamento:

| Parâmetro | Padrão | O que faz |
|---|---|---|
| `-Destino` | `D:\ljsmartclinic-backup-externo` | pasta removível onde a cópia é gravada |
| `-Servidor` | `root@179.236.238.61` | destino ssh, no formato `usuario@host` |
| `-DiretorioRemoto` | `/var/backups/ljsmartclinic` | pasta do backup no servidor |
| `-Manter` | `7` | quantas rotações antigas ficam no destino (a mais recente nunca é removida) |
| `-HorasMaximas` | `26` | idade máxima da rotação mais recente antes de avisar que o backup do servidor parou |
| `-Simular` | — | mostra o que faria, sem tocar em nada |
| `-ConferirDump` | — | confere um `.sql.gz` que já está no destino e sai com código 0 (tem conteúdo) ou 1 (está vazio) |

## O que ele garante

1. **Não apaga nada antes de uma cópia verificada com sucesso.** A poda é a
   última etapa, e ela só roda depois que todos os hashes conferiram.
2. **Não apaga nada fora da pasta de destino.** A remoção só acontece em
   subdiretório cujo nome tem o formato de uma rotação (`AAAAMMDD-HHMMSS`), e
   a checagem é feita pelo caminho absoluto.
3. **Não sai do lugar se o pendrive não estiver conectado.** Ele avisa e termina
   com sucesso — em tarefa agendada, isso é o que evita erro todo dia.
4. **Nunca sobrescreve uma cópia já verificada.** Se a rotação já está no
   destino com o marcador `VERIFICACAO.txt`, o script diz isso e sai. Se a pasta
   está lá **sem** o marcador, a cópia anterior foi interrompida: ele descarta
   e refaz.
5. **Hash diferente aborta.** O SHA-256 é calculado no servidor e conferido
   contra o arquivo local. É a única prova de que o caminho todo (ssh, scp,
   pendrive) entregou o mesmo byte.
6. **Não copia backup vazio.** Ver abaixo — é a garantia mais importante.

## A garantia que importa: hash não é conteúdo

O hash prova que o arquivo chegou **inteiro**. Não prova que ele tem **dado**.

Em produção isso não foi teoria: entre 07/10 e 20/10 o script do servidor
produziu 14 noites seguidas de `central-AAAAMMDD-031701.sql.gz` com **20 bytes**.
Gzip de stream vazio. O `gzip -t` passava, o hash conferia, e o arquivo era
copiado para o pendrive e guardado como se fosse o backup do dia. Não havia um
byte de dado dentro.

Por isso, antes de copiar, este script pergunta ao servidor quantos bytes cada
dump tem **descomprimido** e recusa a cópia se o número não fizer sentido. E
lê o arquivo `STATUS` que o script do servidor grava, para saber se a última
execução de lá deu certo.

Para auditar uma cópia que já está no pendrive, sem usar a rede:

```powershell
powershell -ExecutionPolicy Bypass -File deploy\externo\backup-externo.ps1 `
    -ConferirDump 'D:\ljsmartclinic-backup-externo\20261020-031701\central-20261020-031701.sql.gz'
```

Saída `VAZIO` e código 1 significa que aquele arquivo não é backup.

## O que ele não faz

- **Não cria conta, não sobe serviço, não mexe no servidor.** Só lê (ssh/scp) e
  escreve no destino. Nenhuma migration, nenhum restart.
- **Não roda o backup do servidor.** Ele puxa a rotação que o cron já produziu.
  Se a rotação mais recente estiver velha demais, ele avisa — e a decisão de
  rodar `03-backup.sh` na hora é de quem opera.
- **Não substitui o backup do servidor.** Os dois juntos é o que fecha a
  janela: o do servidor protege do dia a dia, este protege da perda da máquina.
- **Não é automático por si só.** Sem tarefa agendada, ele só roda quando
  alguém rodar. Veja abaixo.

## O `STATUS` do servidor

O `03-backup.sh` grava o resultado da última execução em
`/var/backups/ljsmartclinic/STATUS`:

```
resultado=ok
rotulo=20261021-031701
em=2026-10-21T03:17:05-03:00
detalhe=backup completo
```

O log do cron é sobrescrito a cada noite, então o `STATUS` é o que sobrevive.
Este script o lê e aborta se a última execução do servidor falhou. Para ver
numa máquina:

```bash
ssh root@179.236.238.61 "cat /var/backups/ljsmartclinic/STATUS"
```

Um `resultado=falha` significa que a última noite não produziu backup — e que
esperar pela próxima cópia seria esperar por uma cópia de hoje à noite.

## Agendar no Windows

O Agendador de Tarefas do Windows roda o script todo dia. O horário precisa ser
**depois** do backup do servidor (3:17) — 4:30 dá folga:

```powershell
$acao = New-ScheduledTaskAction `
    -Execute 'powershell.exe' `
    -Argument '-NoProfile -ExecutionPolicy Bypass -File "C:\caminho\do\projeto\deploy\externo\backup-externo.ps1"' `
    -WorkingDirectory 'C:\caminho\do\projeto'

$disparo = New-ScheduledTaskTrigger -Daily -At 4:30am

Register-ScheduledTask `
    -TaskName 'LJ SmartClinic - backup externo' `
    -Action $acao `
    -Trigger $disparo `
    -Description 'Puxa o backup da VPS para o destino removivel e confere o SHA-256.'
```

A tarefa precisa rodar **com a sua conta de usuário**, não como SYSTEM: a chave
ssh em `%USERPROFILE%\.ssh` é dela. Se a chave estiver protegido por senha, o
`BatchMode=yes` do script faz a tarefa falhar rápido em vez de travar esperando
a senha.

Para conferir o que a tarefa fez depois:

```powershell
Get-ScheduledTaskInfo -TaskName 'LJ SmartClinic - backup externo'
Get-Content 'D:\ljsmartclinic-backup-externo\registro.log' -Tail 10
```

## O que fica no destino

```
D:\ljsmartclinic-backup-externo\
  20261021-031701\
    central-20261021-031701.sql.gz        dump do banco central
    clinic_000001-20261021-031701.sql.gz   um dump por clínica
    uploads-20261021-031701.tar.gz          exames, fotos, anexos
    SHA256SUMS.txt                          hash de cada arquivo
    VERIFICACAO.txt                         data, origem e resultado da conferência
  registro.log                              histórico das execuções
```

O nome da pasta é o rótulo da rotação: `AAAAMMDD-HHMMSS`. A pasta do backup
manual da Fase A (`D:\ljsmartclinic-backup-20261021`) fica separada de propósito
— este script nunca toca nela.

## Conferir uma cópia depois de qualquer tempo

```powershell
Get-FileHash -Algorithm SHA256 'D:\ljsmartclinic-backup-externo\20261021-031701\central-20261021-031701.sql.gz'
```

Compare com a linha correspondente do `SHA256SUMS.txt`. Os dois têm que ser
iguais. E, para saber que o dump abre de verdade:

```bash
gunzip -t central-20261021-031701.sql.gz && echo "dump íntegro"
```

## Restaurar a partir da cópia externa

O caminho é o mesmo do `03-backup.sh`, com a fonte trocada. Copie os arquivos do
pendrive para a VPS e restaure:

```bash
scp -r 'D:\ljsmartclinic-backup-externo\20261021-031701' root@179.236.238.61:/tmp/

gunzip < /tmp/20261021-031701/central-20261021-031701.sql.gz | mysql saas_central
gunzip < /tmp/20261021-031701/clinic_000001-20261021-031701.sql.gz | mysql clinic_000001

# os uploads voltam para o storage
tar -xzf /tmp/20261021-031701/uploads-20261021-031701.tar.gz -C /var/www/ljsmartclinic
```

Restaure **um** banco por vez e confira o log de cada `mysql` — restaurar um
dump por cima de um banco que já tem dados deixa as duas versões misturadas.

Um teste de restauração que nunca foi feito é um backup que só existe na
teoria. O teste da Fase A restaurou 29/29 e 48/48 tabelas a partir do dump do
servidor; vale repetir o mesmo teste uma vez a partir desta cópia externa,
porque ela percorre um caminho diferente (ssh, scp, pendrive, FAT32) e é o
único que ainda não foi exercitado de ponta a ponta.

## Limites conhecidos

- **O destino é FAT32.** Um arquivo acima de 4 GB não cabe. O script avisa
  acima de 3,5 GB. Se o banco crescer para esse tamanho, o destino precisa ser
  reformatado (exFAT ou NTFS) ou a cópia passa a ser feita em partes.
- **A cópia é manual até a tarefa agendada existir.** Nada agenda o script
  sozinho.
- **O script depende de `ssh` e `scp` no PATH.** Em Windows eles entram com o
  OpenSSH instalado; sem isso, a tarefa agendada falha.
- **Só a rotação mais recente é copiada.** As anteriores continuam no servidor,
  por 14 dias, e são podadas lá por retenção.
