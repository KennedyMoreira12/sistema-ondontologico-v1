# FASE A.1 — BACKUP EXTERNO

**Data de execução:** 21/10/2026
**Projeto:** LJ SmartClinic
**Responsável técnico:** Kennedy Moreira (execução assistida)
**Status:** concluída (cópia externa criada, integridade e restauração validadas)

---

## 1. Resultado em uma frase

O backup do LJ SmartClinic passou a existir **fora da VPS**: uma cópia íntegra do banco central,
do banco da clínica existente e dos uploads foi transferida para um pendrive (`D:`, `AL-MO-8`),
teve os hashes conferidos entre origem e destino e foi **restaurada com sucesso em bancos
temporários isolados** — sem tocar em nenhum dado de produção.

---

## 2. Situação encontrada

### 2.1. Destinos externos existentes

Verificação feita **antes** de transferir qualquer arquivo:

| Verificação | Resultado |
|---|---|
| Variáveis `AWS_*` no `.env` de produção | `AWS_ACCESS_KEY_ID` **VAZIO**, `AWS_SECRET_ACCESS_KEY` **VAZIO**, `AWS_BUCKET` **VAZIO**. Só `AWS_DEFAULT_REGION` preenchido (9 chars). São placeholders. |
| Pacote de storage remoto instalado | **Nenhum**. Não há `league/flysystem-aws-s3-v3` nem equivalente em `composer.json`. O disco `s3` existe em `config/filesystems.php` mas está inofensivo (sem credencial e sem driver). |
| Rotina de cópia externa no cron | **Nenhuma**. Não há `rclone`, `rsync`, `scp` nem `aws` agendado em `/etc/cron.d/` nem em `/var/spool/cron/crontabs/`. |
| Ferramentas de nuvem na estação de trabalho | `rclone`, `aws`, `gcloud`, `az`, `gsutil`, `azcopy`: **nenhuma instalada**. |
| Pastas sincronizadas (OneDrive/Google Drive/Dropbox) | **Nenhuma** configurada. |
| Discos de rede mapeados | **Nenhum**. |

**Conclusão:** não existia destino externo configurado. O script `deploy/vps/03-backup.sh`
já alertava isso no próprio rodapé — *"este backup está na mesma máquina que ele protege"*.

### 2.2. O bloqueio e a decisão

Como não havia destino aprovado, a transferência foi **interrompida e o proprietário
consultado** (não se cria conta, não se contrata serviço e não se envia dado a terceiro
sem autorização).

Opções apresentadas: pendrive `D:`, pasta local da estação, Backblaze B2/S3, segundo
servidor por SSH, ou adiar.

**Destino escolhido pelo proprietário: pendrive `D:` (volume `AL-MO-8`, FAT32, 7,4 GB).**

---

## 3. Inventário antes de definir os arquivos

Não foi presumido que existisse apenas um tenant. O inventário foi levantado na origem:

| Item | Resultado |
|---|---|
| Tenants ativos em `saas_central.tenant_connections` | **1** (`clinic_id=1`, `database_name=clinic_000001`, `is_active=1`) |
| Bancos `clinic_%` existentes na instância | **1** (`clinic_000001`) |
| Banco central | `saas_central` |
| Uploads | `storage/app` (≈80 KB, 10 entradas no tar) |

> Observação de schema: a tabela `tenant_connections` **não tem** coluna `name` (tem
> `clinic_id`, `driver`, `database_name`, `database_host`, `database_port`,
> `database_username`, `database_password`, `is_active`). A primeira consulta tentada
> usou `name` e falhou; o inventário foi refeito com as colunas reais.

Os arquivos a copiar foram definidos depois deste inventário, para já refletir uma
segunda clínica caso ela passasse a existir.

---

## 4. Cópia gerada

### 4.1. Origem

Nova execução do script oficial, preservando tudo que já existia:

- Script: `/var/www/ljsmartclinic/deploy/vps/03-backup.sh` — **não modificado**
- Diretório: `/var/backups/ljsmartclinic/`
- Rótulo (data e hora): **`20261021-021741`**
- Nenhum arquivo pré-existente foi apagado ou substituído (a retenção de 14 dias do
  script atua por idade, e os arquivos da noite anterior seguem no lugar).

### 4.2. Arquivos incluídos

| Arquivo | Bytes | Conteúdo |
|---|---:|---|
| `central-20261021-021741.sql.gz` | 43.767 | Banco central (`saas_central`) — 29 tabelas |
| `clinic_000001-20261021-021741.sql.gz` | 9.531 | Banco da clínica (`clinic_000001`) — 48 tabelas |
| `uploads-20261021-021741.tar.gz` | 38.664 | `storage/app` — 10 entradas |

### 4.3. Destino

```
D:\ljsmartclinic-backup-20261021\
├── central-20261021-021741.sql.gz
├── clinic_000001-20261021-021741.sql.gz
├── uploads-20261021-021741.tar.gz
└── SHA256SUMS.txt
```

Canal utilizado: `scp` sobre a conexão SSH já existente (chave, sem senha em linha de
comando nem em arquivo de log). Nenhuma credencial nova foi criada.

---

## 5. Integridade e hashes

### 5.1. Hashes SHA-256 — origem e destino

| Arquivo | SHA-256 | Origem = Destino |
|---|---|---|
| `central-...sql.gz` | `4890ce40fa21801692914f575875795984fdd22f1ce2489cf4bfd7fbfe568fea` | **sim** |
| `clinic_000001-...sql.gz` | `9327550f6467428f7ecd4f8d7f17a3536fa8abe70921e5d93d51fed79f160f39` | **sim** |
| `uploads-...tar.gz` | `0c964097aa56911f32d96624873694c3a321bc9ad8aa67ef006238c78fc123c2` | **sim** |

**Divergências: 0.**

### 5.2. Teste de integridade

- Na origem: `gzip -t` aprovado nos três arquivos (etapa do próprio script).
- No destino: descompressão completa com `GZipStream` — os três descomprimem por inteiro.

| Arquivo no pendrive | Descomprimido | Veredito |
|---|---:|---|
| `central-...sql.gz` | 297.643 bytes | válido |
| `clinic_000001-...sql.gz` | 82.182 bytes | válido |
| `uploads-...tar.gz` | 81.920 bytes | válido |

> Nota de método: o `.sql.gz` é **gzip puro** (não `tar.gz`) — o `tar -tzf` do Windows
> falha nele e chegou a sugerir corrupção. A verificação correta é por descompressão
> gzip, método usado acima. Nada estava corrompido.

### 5.3. Conteúdo legível

- Dump central: cabeçalho `MariaDB dump 10.19-11.8.6`, base `saas_central`, **29 `CREATE TABLE`**.
- Dump da clínica: **48 `CREATE TABLE`**.
- Uploads: tar extraível, arquivos legíveis.

---

## 6. Teste de restauração isolada

Executado **no VPS**, em bancos temporários, com o propósito de não tocar em nada existente.

**Credencial:** foi necessário `root` via socket (`/etc/mysql/debian.cnf`). O usuário
`saas` do `/root/.my.cnf` **não tem privilégio** para criar banco (`ERROR 1044 Access
denied`) — ele serve para dump/leitura, que é o papel dele no backup.

**Procedimento e resultado:**

1. Criados `restore_test_central` e `restore_test_clinic` (bancos novos, vazios). ✅
2. Restaurado o dump central a partir do arquivo transferido. ✅ `rc=0`
3. Restaurado o dump da clínica. ✅ `rc=0`
4. **Comparação de contagem por tabela, produção × restaurado:**

| Base | Tabelas produção | Tabelas restauradas | Tabelas divergentes |
|---|---:|---:|---:|
| `saas_central` | 29 | 29 | **0** |
| `clinic_000001` | 48 | 48 | **0** |

   Amostra de dados efetivamente restaurados (apenas contagens):
   `clinics=1`, `prospects=158`, `admin_users=1`, `permissions=64`, `procedures=10`, `roles=6`.

5. Bancos de teste **removidos**. ✅ `0 restantes`
6. Produção conferida depois: `tenant_connections` intacta (`clinic_000001`, ativo) e
   `clinic_000001.pacientes` respondendo normalmente.

**Nada foi restaurado sobre banco existente. Nenhum arquivo de origem foi apagado.**

---

## 7. Itens preservados

- Os backups que já existiam na VPS continuam no lugar: **24 arquivos `.gz`** em
  `/var/backups/ljsmartclinic`, incluindo os 15 arquivos de 20 bytes mantidos de propósito.
- O script `03-backup.sh` não foi alterado.
- Os arquivos de origem **não foram apagados** após a transferência.
- Nenhum dado da clínica Malheiro foi lido, alterado ou removido durante a operação.

---

## 8. Pendências e recomendações de retenção

### 8.1. Pendências

| # | Pendência | Risco | Ação necessária |
|---|---|---|---|
| P1 | O pendrive `D:` é removível e pode ser desconectado/esquecido | Médio | Definir uma rotina (ex.: semanal) de reconexão e nova cópia; manter o manifesto `SHA256SUMS.txt` ao lado dos arquivos. |
| P2 | Um único destino externo, sem redundância | Médio | Avaliar um segundo destino (nuvem ou segundo servidor) quando houver autorização e credenciais. |
| P3 | A cópia externa foi **manual** | Médio | Enquanto não houver destino automatizado, o backup externo depende de alguém executar. Documentado como limitação consciente. |
| P4 | A senha do usuário `saas` em `/root/.my.cnf` pode voltar a ficar desatualizada | Alto | Já houve esse incidente (raiz da falha anterior). Recomenda-se um teste periódico do backup que falhe de forma visível. |

### 8.2. Retenção recomendada

- **Diário na VPS:** 14 dias (já é o comportamento atual do script).
- **Externo (pendrive):** manter **pelo menos** 4 cópias semanais + 1 mensal, nomeadas
  por data. Guardar os arquivos em subpastas por mês para não misturar.
- **Verificação:** a cada nova cópia, gerar novo `SHA256SUMS.txt` e conferir o hash —
  a cópia só vale depois de conferida, não antes.

### 8.3. Lacunas conhecidas da cópia atual

- O backup cobre **1 tenant** porque só existe 1 hoje. Quando surgir a segunda clínica,
  o script já a inclui automaticamente (a lista vem de `tenant_connections`), mas o
  procedimento de cópia externa deve ser refeito com o inventário atualizado.
- Não há cópia do arquivo `.env` de produção no pacote externo (correto: ele contém
  segredos). A recuperação total depende de o `.env` ser guardado em cofre separado.

---

## 9. Confirmações

- Destino externo existe e foi validado por hash e por restauração.
- Integridade aprovada na origem e no destino.
- Restauração isolada aprovada: contagens idênticas em todas as tabelas.
- Nenhum arquivo de origem apagado ou substituído.
- Nenhuma credencial nova criada; nenhum segredo transcrito em arquivo, log ou relatório.
- Produção não foi alterada; nenhum deploy foi feito; nenhuma migration foi executada.
- A clínica Malheiro permanece intacta.
