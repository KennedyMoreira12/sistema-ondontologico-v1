# FASE A.1-B — BACKUP EXTERNO AUTOMATIZADO E CORREÇÃO DO BACKUP EM BRANCO

**Data de execução:** 21/10/2026
**Projeto:** LJ SmartClinic
**Responsável técnico:** Kennedy Moreira (execução assistida)
**Status:** concluída em desenvolvimento local, **sem push e sem deploy**

---

## 1. Resultado em uma frase

O backup externo deixou de depender de alguém lembrar de copiar, e a busca pelo
arquivo que a Fase A.1 deixou na pendência de limpeza **revelou 14 noites
seguidas de "backup" com zero byte de dado** — corrigido no código, com teste
que reproduz o caso exato de produção.

---

## 2. O que foi encontrado

### 2.1. Os 14 arquivos de 20 bytes

A Fase A.1 registrou, no seu item 7, "24 arquivos `.gz`" na VPS, "incluindo os
15 arquivos de 20 bytes mantidos de propósito". A contagem exata é **14**, e o
que eles eram não era ruído:

| Arquivo | Bytes no disco | Bytes descomprimidos | Assinatura de dump |
|---|---:|---:|---|
| `central-20261007-031701.sql.gz` | 20 | **0** | **ausente** |
| `central-20261008-031701.sql.gz` | 20 | **0** | **ausente** |
| … (14 noites, de 07/10 a 20/10) | 20 | **0** | **ausente** |
| `central-20261021-031701.sql.gz` | 43.766 | 297.643 | `MariaDB dump 10.19` |
| `clinic_000001-20261021-031701.sql.gz` | 9.531 | 82.182 | `MariaDB dump 10.19` |

**20 bytes é o tamanho de um gzip de stream vazio.** Descomprimido, cada um
deles tem **0 bytes**.

Duas coisas importantes ao mesmo tempo:

1. **O dump central saiu vazio por 14 noites seguidas.**
2. **Não existe dump de clínica nem de uploads dessas noites.** O diretório tem
   26 arquivos `.gz`, e apenas 12 deles são de 21/10 — as quatro rotações
   daquele dia. Entre 07/10 e 20/10 existe **só** `central-*`, e todos vazios.

### 2.2. A consequência

Entre 07/10 e 20/10, **não existia backup de nada**: nem do banco central, nem
do banco da clínica, nem dos uploads. E ninguém percebeu, porque:

- o script escrevia o arquivo **direto no nome final**, então o nome dizia
  "backup de 07/10" e o conteúdo eram 20 bytes;
- o `gzip -t` (a verificação do próprio script) **passa** num gzip de stream
  vazio — o formato é válido;
- o `set -e` do script matava o processo no **primeiro** comando que falhava, ou
  seja, a etapa de verificação **nunca era alcançada**;
- o cron redirecionava o log com `>`, sobrescrevendo a cada noite — a prova da
  falha sumia junto com o log.

O efeito disso é que o backup **parecia** estar funcionando: o nome do arquivo
tinha a data certa, o arquivo existia, tinha 20 bytes (não-zero) e passava no
`gzip -t`. O código de saída do script era diferente de zero — mas ninguém
monitora código de saída de cron, e o log que mostraria o erro foi sobrescrito
na noite seguinte.

### 2.3. A causa

O arquivo de credencial `/root/.my.cnf` **não existia** até 21/10 às 01:35 —
o mesmo minuto do primeiro dump válido:

```
-rw------- 1 root root 42 Oct 21 01:35 /root/.my.cnf
```

O script cria esse arquivo quando ele não existe, com a senha de exemplo
`TROQUE-A-SENHA-DO-BANCO-AQUI`. Com a senha errada, o `mysqldump` falhava
com "Access denied", o `gzip` recebia um stream vazio e escrevia os 20 bytes.
A credencial foi corrigida em 21/10 (Fase A.1), e a partir daí os dumps
saíram certos.

Não há como saber, a partir do log (sobrescrito), **quais** dados existiam
naquela janela. A clínica estava em operação normal ou não é assunto do
proprietário — o registro técnico é: não havia backup.

---

## 3. As três correções no `03-backup.sh`

Todas no arquivo do repositório. **Nada foi alterado na VPS** — a correção
entra em vigor no próximo deploy, que não está autorizado.

### 3.1. O dump vai para arquivo temporário

Antes:

```bash
mysqldump ... "$BANCO_CENTRAL" | gzip > "$DIRETORIO_BACKUP/central-$ROTULO.sql.gz"
```

Agora o dump vai para `central-$ROTULO.sql.gz.parcial`, tem o status de saída
do `mysqldump` conferido, passa pela verificação de conteúdo e **só então**
recebe o nome final. Um dump que falha não deixa arquivo com cara de backup.

### 3.2. A verificação olha o conteúdo, não só o gzip

A função `verificar_dump` faz três perguntas:

| Pergunta | Motivo |
|---|---|
| o gzip abre? | pega arquivo truncado ou corrompido |
| o conteúdo descomprimido tem mais de 1000 bytes? | **pega o backup vazio** |
| o conteúdo tem a assinatura de dump do MariaDB? | pega arquivo que tem bytes mas não é dump |

A segunda pergunta é a que teria pegado as 14 noites.

### 3.3. O log é acumulado e o resultado fica gravado

- O cron passou de `>` para `>>`: o log de uma noite não apaga mais o da noite
  anterior. Com o `>` atual, a prova de qualquer falha desaparece em 24 horas.
- O script grava `/var/backups/ljsmartclinic/STATUS` com `resultado=`,
  `rotulo=`, `em=` e `detalhe=`. Qualquer saída inesperada — inclusive o
  `set -e` disparando no meio de um dump — deixa `resultado=falha`. É o que
  sobrevive ao log sobrescrito.

---

## 4. O backup externo automatizado

Novo diretório `deploy/externo/`, com `backup-externo.ps1` e `README.md`.

O script roda na máquina do pendrive e:

1. lê a lista de rotações no servidor e escolhe a mais recente;
2. calcula o SHA-256 de cada arquivo **no servidor**;
3. confere quantos bytes cada dump tem **descomprimido** e recusa backup
   vazio;
4. lê o `STATUS` do servidor e aborta se a última execução de lá falhou;
5. copia por `scp` e confere o hash de cada arquivo contra o servidor;
6. escreve `SHA256SUMS.txt` e `VERIFICACAO.txt` (com os tamanhos
   descomprimidos) na rotação;
7. **só então** poda as rotações antigas do destino.

Regras garantidas pelo código e cobertas por teste:

- não apaga nada antes de uma cópia verificada;
- não apaga nada fora da pasta de destino;
- não apaga nada que não tenha nome de rotação (`AAAAMMDD-HHMMSS`);
- não sobrescreve cópia já verificada (usa o marcador `VERIFICACAO.txt`);
- recopia quando a pasta existe **sem** o marcador — cópia interrompida;
- não sai do lugar se o pendrive não estiver montado;
- `BatchMode=yes` no `ssh`/`scp`: em tarefa agendada não há ninguém para
  digitar a senha, e o script precisa falhar rápido em vez de travar.

### 4.1. O que foi provado

| Prova | Resultado |
|---|---|
| Execução real ponta a ponta contra a VPS | 3 arquivos copiados, hashes conferidos com o servidor |
| Independência do hash conferida fora do script (`Get-FileHash` contra o manifesto) | 3/3 conferem |
| Fluxo gzip lido até o fim, em PHP, sem depender do script | 3/3 íntegros |
| Assinatura de dump conferida no conteúdo descomprimido | `saas_central` e `clinic_000001` identificados |
| `-ConferirDump` com dump vazio | sai com código 1 e mensagem `VAZIO` |
| `-ConferirDump` com dump válido | sai com código 0 |
| Reexecução com rotação já verificada | pula, sem recopiar |
| Reexecução com pasta sem marcador | descarta e refaz |
| Suíte PHPUnit | 16 testes novos, 16 passam |

---

## 5. Os arquivos de 20 bytes: recomendação

Eles **não foram apagados**. A retenção de 14 dias do próprio script os remove
por volta de 03/11, sem intervenção.

Recomenda-se **deixá-los até lá**: são 280 bytes no total e são a evidência
do incidente. Apagar agora destrói a prova de que o problema existiu — e o
primeiro item do checklist de produção ("testar a restauração") só faz
sentido quando existe algo para testar.

Se o proprietário preferir o diretório limpo, a remoção é de um comando e não
toca em nenhum dado:

```bash
find /var/backups/ljsmartclinic -name '*.sql.gz' -size -100c -delete
```

---

## 6. O que NÃO foi feito

- **Nenhuma alteração na VPS.** As correções do `03-backup.sh` estão no
  repositório e só entram em vigor no próximo deploy, que não está
  autorizado. **Até lá, o backup da VPS continua podendo gerar um arquivo
  vazio** — o que o `backup-externo.ps1` agora recusa de copiar.
- **Nenhum arquivo de backup apagado**, nem na VPS nem no pendrive.
- **Nenhuma credencial criada, alterada ou transcrita.** A senha do
  `/root/.my.cnf` não aparece neste documento nem nos logs.
- **Nenhuma tarefa agendada criada na máquina do proprietário.** O README
  traz o comando pronto, mas o registro fica com quem opera.
- **Nenhum dado da clínica Malheiro lido, alterado ou removido.**
- **Nenhum push, deploy ou migration.**

---

## 7. Pendências

| # | Pendência | Risco | Ação |
|---|---|---|---|
| P1 | O `03-backup.sh` corrigido está no repositório, mas a VPS roda a versão antiga | **Alto** | Publicar e rodar `03-backup.sh instalar` (ou ajustar o cron à mão) no próximo deploy autorizado |
| P2 | O backup externo ainda é manual: o script existe, mas nada o agenda | Médio | Registrar a tarefa no Agendador de Tarefas (comando no `deploy/externo/README.md`) |
| P3 | O log do cron ainda sobrescreve na VPS, porque o cron de lá é o antigo | Médio | Corrigido pelo próprio script ao ser reinstalado (`03-backup.sh instalar`) |
| P4 | Não há teste de restauração a partir da cópia **externa** | Médio | Repetir o teste da Fase A.1 (29/29 e 48/48 tabelas) a partir de `D:\ljsmartclinic-backup-externo` |
| P5 | Um único destino externo, sem redundância | Médio | Avaliar um segundo destino (nuvem ou segundo servidor) quando houver autorização e credenciais |
| P6 | O destino é FAT32: um dump acima de 4 GB não cabe | Baixo | O script avisa acima de 3,5 GB; reformatar quando o banco crescer |
| P7 | A retenção de 14 dias apaga os arquivos de 20 bytes por volta de 03/11 | Baixo | Deixar: são a evidência do incidente e somam 280 bytes |

---

## 8. Confirmações

- O backup externo foi automatizado, testado contra a VPS e deixou de ser
  manual.
- O backup em branco de 14 noites foi diagnosticado, com causa, evidência e
  data.
- As três correções estão no repositório, com teste que reproduz o caso de
  produção.
- Nenhum arquivo de backup foi apagado.
- Nenhuma alteração foi feita na VPS, no banco ou no pendrive existente.
- Nenhuma credencial foi criada, alterada ou transcrita.
- Nenhum push, deploy ou migration foi feito.
