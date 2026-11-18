# Política de Backup — LJ SmartClinic

**Atualizado em:** 18/11/2026 (Etapa 0 — estabilização)
**Escopo:** produção em `/var/www/ljsmartclinic` (VPS `179.236.238.61`)
**Status:** política documentada; **retenção não foi alterada**; off-site segue pendente

---

## 1. Regra de ouro

Nenhum backup pode ser excluído antes de:

1. existir uma cópia válida em outro local; e
2. a restauração dessa cópia ter sido testada e comparada com a produção.

Enquanto o off-site não existir, **a retenção local não deve ser reduzida**.

---

## 2. O que é copiado

| Item | Origem | Formato |
|---|---|---|
| Banco central | `saas_central` | `central-AAAAMMDD-HHMMSS.sql.gz` |
| Um dump por clínica ativa | `saas_central.tenant_connections` (`is_active = 1`) | `clinic_000001-…sql.gz`, `clinic_000005-…sql.gz` |
| Uploads | `storage/app` | `uploads-AAAAMMDD-HHMMSS.tar.gz` |

A lista de clínicas vem da central — não é fixa no script. Clínica inativa (`clinic_000006`) não entra.

---

## 3. Quando e onde

| Item | Valor |
|---|---|
| Agendamento | `/etc/cron.d/ljsmartclinic-backup` — **03:17, todos os dias** |
| Log | `/var/log/lj-backup.log` (acumulado com `>>`) |
| Diretório local | `/var/backups/ljsmartclinic` (`chmod 700`) |
| Status | `/var/backups/ljsmartclinic/STATUS` (`chmod 600`) |
| Script | `deploy/vps/03-backup.sh` |

O dump usa `~/.my.cnf` (`chmod 600`). Nenhuma senha é escrita no arquivo de dump nem no repositório.

---

## 4. Retenção

- `DIAS_RETENCAO=14` (padrão do script).
- A limpeza é feita por `find "$DIRETORIO_BACKUP" -name '*.sql.gz' -mtime +14 -delete` (e o mesmo para `*.tar.gz`).

### Atenção: a varredura é recursiva

O `find` percorre **subdiretórios**. Qualquer arquivo `.sql.gz` ou `.tar.gz` guardado dentro de `/var/backups/ljsmartclinic` será apagado após 14 dias, inclusive snapshots manuais.

**Por isso, snapshots de longo prazo ficam em:**

```
/var/backups/ljsmartclinic-snapshots/
```

Conteúdo atual:

| Item | O que é |
|---|---|
| `estabilizacao-20261118-091946/` | Snapshot da Etapa 0: código (com `.git` e `.env`), dumps dos 3 bancos, schemas, manifestos SHA-256, contagens de referência |
| `pre-laser-20261117-191422.tar.gz` | Código antes do módulo laser |
| `pre-laser-20261117-191422/` | Cópias dos 6 arquivos alterados antes do laser |
| `quarentena-20261118-092801/` | 49 arquivos de lixo operacional movidos (nunca apagados) |

**Regra:** nunca colocar arquivo que precise durar mais de 14 dias dentro de `/var/backups/ljsmartclinic`.

---

## 5. Verificação automática (o que o script já faz)

O script não confia em `gzip -t` sozinho — um gzip de stream vazio tem 20 bytes e passa. Para cada dump ele confere:

1. `gzip -dc` funciona (não corrompido);
2. o conteúdo descompactado tem **pelo menos 1.000 bytes**;
3. o conteúdo contém a assinatura `dump 10` (é realmente um dump);
4. o arquivo só recebe o nome final depois de aprovado (`*.parcial` → definitivo).

Para os uploads: `tar -tzf` precisa listar o conteúdo.

No fim, o script faz uma segunda passagem em todos os arquivos da rotação e grava o `STATUS`.

### STATUS

```
resultado=ok
rotulo=20261118-031702
em=2026-11-18T03:17:04+00:00
detalhe=backup completo
```

Qualquer falha (inclusive `set -e` no meio do processo) grava `resultado=falha`.

**Pendência:** não existe alarme ativo. Hoje é preciso **ler o STATUS** ou o log. Recomenda-se um monitor que dispare quando `resultado != ok` por mais de 24 h.

---

## 6. Off-site (pendente)

| Item | Situação |
|---|---|
| Script | `deploy/externo/backup-externo.ps1` (Windows/PowerShell) |
| Destino padrão | `D:\ljsmartclinic-backup-externo` (pendrive/removível) |
| Simulação | `-Simular` (não toca em nada) |
| Verificação | SHA-256 calculado no servidor e conferido no destino |
| **Execução comprovada** | **Nenhuma** — não há log, hash ou cópia fora da VPS |

Enquanto não houver off-site comprovado:

- a VPS continua sendo o único local dos backups;
- a perda da VPS leva os dados **e** os backups;
- a retenção de 14 dias **não deve ser reduzida**.

**Decisão pendente:** qual destino usar (S3, Backblaze, segundo servidor, pendrive com rotação). Sem essa decisão, esta política não avança.

---

## 7. Teste de restauração (procedimento e evidência)

### Procedimento

Sempre em bancos **separados**, nunca por cima da produção:

```bash
# como root do MySQL (auth_socket)
mysql -u root -e "CREATE DATABASE restore_test_central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -e "CREATE DATABASE restore_test_clinic_000001 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

gzip -dc /var/backups/ljsmartclinic/central-AAAAMMDD-HHMMSS.sql.gz | mysql -u root restore_test_central
gzip -dc /var/backups/ljsmartclinic/clinic_000001-AAAAMMDD-HHMMSS.sql.gz | mysql -u root restore_test_clinic_000001

# comparar contagens produção x restaurado
# ...

mysql -u root -e "DROP DATABASE restore_test_central; DROP DATABASE restore_test_clinic_000001"
```

O usuário `saas` **não** tem privilégio nos bancos `restore_test_*`; por isso o restore é feito com `mysql -u root` (socket). A aplicação continua usando `saas`.

### Evidência do teste de 18/11/2026

Dumps usados: `central-20261118-031702.sql.gz` e `clinic_000001-20261118-031702.sql.gz`.

| Métrica | Produção | Restaurado | Resultado |
|---|---|---|---|
| `saas_central.clinics` | 3 | 3 | OK |
| `clinic_000001.pacientes` | 621 | 621 | OK |
| `clinic_000001.migrations` | 74 | 74 | OK |
| tabelas da Malheiro | 73 | 73 | OK |
| FKs da Malheiro | 75 | 75 | OK |
| `laser_regioes` | 0 | 0 | OK |
| `saas_central.migrations` | 83 | 83 | OK |
| Engine/collation | InnoDB/utf8mb4_unicode_ci ×73 | igual | OK |
| `CHECK TABLE pacientes` / `clinics` | — | OK | OK |

Os bancos `restore_test_*` foram removidos ao final. A produção permaneceu intacta (621 pacientes, 3 clínicas).

### Frequência recomendada

- Após qualquer mudança de schema.
- Trimestral, no mínimo.
- Sempre antes de reduzir retenção ou mudar o destino off-site.

---

## 8. Restauração de um banco de clínica (emergência)

1. **Pare o acesso** à clínica (manutenção) para evitar escrita durante o restore.
2. Faça um dump do estado atual (mesmo que corrompido) antes de sobrescrever.
3. Restaure em um banco novo (`clinic_000001_restore`) e valide contagens.
4. Só então troque o nome/conexão da clínica.
5. Registre data, dump usado, responsável e resultado.

Nunca restaurar direto por cima do banco em uso sem um dump imediatamente anterior.

---

## 9. O que esta etapa **não** fez

- Não alterou `DIAS_RETENCAO` (continua 14).
- Não apagou nenhum backup.
- Não configurou off-site.
- Não configurou alarme.
- Não alterou o cron.

A única mudança de organização foi mover snapshots de longo prazo para `/var/backups/ljsmartclinic-snapshots`, fora da varredura de retenção.
