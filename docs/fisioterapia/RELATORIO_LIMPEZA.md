# Relatório de arquivos pendentes de limpeza

**Nada foi apagado.** Este documento lista o que está sobrando, quanto ocupa,
por que está lá e o que fazer com cada item. A decisão de apagar é de quem é
dono do arquivo — e alguns destes **não podem** ser apagados ainda.

Levantamento feito em 21/10/2026, na estação de trabalho e na VPS.

---

## 1. Resumo

| Onde | O quê | Tamanho | Situação |
|---|---|---|---|
| `storage/framework/testing/` (local) | 14.413 `.sqlite` + 771 pastas | **5,79 GB** | vazamento **corrigido**; apagar quando der |
| `storage/logs/laravel.log` (local) | log de uma semana | 4,9 MB | truncar |
| `storage/framework/views/` (local) | 313 views compiladas | ~1 MB | opcional |
| raiz do projeto | `test_feature.out` (0 bytes) | 0 | apagar ou ignorar |
| `docs/fisioterapia/` | `AUDITORIA_IA_HISTORICO_FOLLOWUP…md` | 22 KB | **manter** — trabalho em andamento |
| `app/`, `resources/` | `ProspectController.php` e `prospeccao/index.blade.php` modificados | — | **manter** — trabalho em andamento |
| VPS `/var/www/ljsmartclinic/` | 34 arquivos de backup no código | 449 KB | remover **depois** do deploy |
| VPS `/var/www/ljsmartclinic/` | 3 cópias do `.env` | 22 KB | remover **depois** do deploy |
| VPS `/var/www/ljsmartclinic/` | `test.env`, `test.txt`, `backup_antes_remocao/`, `docs/` | 556 KB | remover **depois** do deploy |
| VPS `/var/backups/ljsmartclinic/` | 14 dumps vazios de 20 bytes | 280 B | **manter** até ~03/11 (evidência) |
| pendrive `D:\` | `Backups\LJ-SmartClinic-antes-recuperacao` | 50 MB | do dono |

---

## 2. A descoberta: 5,79 GB de lixo de teste

### O sintoma

`storage/framework/testing/` ocupava **5,79 GB** com **14.413 arquivos
`.sqlite`** e **771 pastas** `tenants-*`. O mais antigo era de 25/08 e o mais
novo de 21/10: a pasta crescia a cada rodada de testes, sem que ninguém
soubesse de onde vinham.

Cada arquivo tem ~360 KB (o schema completo de tenant) e cada pasta `tenants-*`
tem um `clinic_000001.sqlite` dentro.

### A causa — eram duas, e as duas são de teste

**1. O Windows não apaga arquivo com handle aberto.**

O `tearDown` de cada teste fazia `@unlink($this->tenantFile)` **enquanto a
conexão SQLite ainda estava aberta**. No Windows o `unlink` falha, o `@`
engolia o erro em silêncio, e o arquivo ficava. No Linux o mesmo código
funcionaria — por isso o vazamento nunca apareceu em ninguém else's máquina.

**2. O PHP descarta o `tearDown` do trait quando a classe define o próprio.**

`CreatesTenants` tinha o `tearDown` que apagava os bancos das clínicas. Mas
`PlatformClinicTest`, `PlatformClinicWhatsAppTest`, `DemoSeederTest` e
`ProvisionamentoClinicaTest` definem o próprio `tearDown` — e aí o PHP dá
**precedência ao método da classe e descarta o do trait em silêncio**. O
arquivo das clínicas ficava para trás, e ninguém recebia aviso.

### A correção

- `DB::purge('tenant')` **antes** do `unlink`: a conexão fecha, o handle
  solta, o arquivo apaga.
- O registro dos bancos a apagar foi para o `TestCase` base, e o `tearDown`
  do trait foi **removido**. A limpeza agora mora num lugar só, que não
  pode ser descartado pelo PHP.
- `apagarPastaDeBancosDeTeste()` faz o mesmo pelas pastas `tenants-*`.

### A prova

| Medida | Antes | Depois |
|---|---|---|
| Arquivos deixados por 34 testes | 34 | **0** |
| Arquivos deixados por 17 testes do `PlatformClinicTest` | 11 | **0** |
| Arquivos deixados pela suíte inteira (515 testes) | ~510 | **0** |
| Pastas `tenants-*` deixadas pela suíte | 771 | **0** |

E o teste novo (`tests/Feature/LimpezaBancosDeTesteTest.php`) falha se a
correção for desfeita — foi verificado tirando o `purge` e vendo o teste
ficar vermelho.

### O que fazer com os 5,79 GB

Eles são lixo puro: banco de teste sem dono, gerado por uma suíte que já
terminou. **Podem ser apagados inteiros** — o template do schema
(`tenant_template.sqlite`) é reconstruído sozinho no próximo teste.

Quando apagar, apagar **a pasta inteira** (`storage/framework/testing/`), não
os arquivos soltos: as 771 pastas `tenants-*` são o grosso do espaço.

```powershell
# a pasta inteira pode ser apagada; ela é recriada na hora
Remove-Item -Recurse -Force storage\framework\testing
```

> O disco C: tem 60,6 GB livres, então não há urgência. O que urge é
> **parar de crescer**, e isso já está resolvido.

---

## 3. Os arquivos um a um

### 3.1 `storage/logs/laravel.log` — 4,9 MB

Log de uma semana de suíte. Nada de interessante; some a cada deploy no
servidor. Pode ser apagado ou truncado.

### 3.2 `storage/framework/views/` — 313 arquivos compilados

Cache do Blade. O Laravel recompila sozinho quando o arquivo muda. Pode ser
apagado; a única consequência é o primeiro acesso a cada tela ficar mais
lento.

### 3.3 `test_feature.out` — 0 bytes, na raiz

Arquivo vazio, sem dono conhecido, na raiz do repositório. Três saídas
possíveis:

- **apagar** — é o mais limpo;
- **adicionar ao `.gitignore`** — se algum comando sempre o cria;
- **manter** — se for gerado por algo em uso.

A decisão é do dono. Não foi apagado nem ignorado.

### 3.4 `docs/fisioterapia/AUDITORIA_IA_HISTORICO_FOLLOWUP_LJ_SMARTCLINIC.md` — 22 KB

Documento de auditoria sobre o atendimento com IA, **não rastreado** e
**não versionado**. Não é deste trabalho. Foi mantido como está.

### 3.5 `app/Http/Controllers/Platform/ProspectController.php` e `resources/views/platform/prospeccao/index.blade.php` — modificados

Trabalho em andamento, **fora do escopo deste trabalho**. Não foram
commitados, alterados nem revertidos.

---

## 4. Na VPS

### 4.1 Arquivos de backup dentro do código (34 arquivos, 449 KB)

| Grupo | Quantidade |
|---|---|
| `ProspectController.php.backup*` / `.bak*` / `.broken` / `.fixedbackup` | 11 |
| `backup_antes_remocao/20261012_124032/prospeccao_views/*` | 10 |
| `resources/views/layouts/platform.blade.php.*` | 4 |
| `resources/scss/app.scss.*` | 3 |
| `routes/platform.php.*` | 3 |
| `.env.backup`, `.env.bak-20261003-064033`, `.env.bak.20261004-153740` | 3 |

A pasta `backup_antes_remocao/` tem **25 arquivos** ao todo (556 KB), dos
quais 10 são as cópias com nome de backup listadas acima.

Nada disso está em nenhum repositório. São cópias de trabalho do dono, feitas
com o editor, jogadas no meio do código.

**Por que não foram apagadas agora:** o passo 2 do
[checklist de publicação](CHECKLIST_PUBLICACAO.md) guarda exatamente esses
arquivos (`git diff` e um `tar` dos não rastreados) como snapshot do estado
atual de produção. Apagar antes de guardar o snapshot seria trocar o
backup por uma cópia de segurança — e o backup é o que faz sentido.

**Quando apagar:** depois do deploy, com o snapshot conferido.

### 4.2 Três cópias do `.env` na raiz da aplicação

`.env.backup`, `.env.bak-20261003-064033` e `.env.bak.20261004-153740` —
**69, 62 e 68 chaves**, ou seja, as credenciais de produção (APP_KEY, senha
do banco, do SMTP, token da Evolution API).

**O risco está contido:** o nginx tem `location ~ /\.(?!well-known) { deny
all; }` e a requisição real de `.env` e de `.env.bak.20261004-153740` volta
**404**. Não são servidas.

O que resta é: (a) segredo de produção em arquivo desnecessário no disco;
(b) `.env.bak.20261004-153740` com permissão `640 root:www-data`, legível
pelo usuário do PHP-FPM; (c) um `.env` velho pode ser restaurado por engano
depois de uma mudança de senha, ressuscitando credencial que já foi trocada.

**Prioridade: média.** Remover junto com o resto, depois do deploy.

### 4.3 Outros soltos

- `test.env` (29 bytes) e `test.txt` (5 bytes) na raiz.
- `backup_antes_remocao/` — 556 KB, de 12/10.
- `docs/` na VPS — 16 KB, uma cópia de `atendimento-ia-whatsapp.md`.

### 4.4 Os 14 dumps vazios de 20 bytes

Em `/var/backups/ljsmartclinic/`, 14 arquivos `.gz` de 20 bytes, de 07/10 a
20/10. São backups **vazios**: o `mysqldump` falhava, o gzip do stream vazio
era gravado com o nome final e o script morria antes da verificação.

**Não devem ser apagados agora.** Eles são a **evidência** do incidente, e a
retenção de 14 dias do `03-backup.sh` já os apaga sozinha por volta de
**03/11**. A causa foi diagnosticada e corrigida no repositório
(`deploy/vps/03-backup.sh`), e a correção entra em vigor com o próximo
deploy — ver a trava 2 do
[checklist de publicação](CHECKLIST_PUBLICACAO.md).

---

## 5. No pendrive `D:`

| Item | Tamanho | Observação |
|---|---|---|
| `D:\ljsmartclinic-backup-20261021\` | 90 KB | backup A.1, validado (SHA-256 + restauração) |
| `D:\ljsmartclinic-backup-externo\20261021-031701\` | 90 KB | backup A.1-B, verificado (`SHA256SUMS.txt` + `VERIFICACAO.txt`) |
| `D:\ljsmartclinic-backup-externo\registro.log` | — | histórico: `resultado=OK` em 21:19 e 21:35 |
| `D:\Backups\LJ-SmartClinic-antes-recuperacao\` | 50 MB | do dono (cópia do projeto com `node_modules`) |
| `D:\_assets\`, `D:\Robin_nano35.bin` | — | de outra finalidade (impressora 3D) |

O pendrive tem **7,2 GB livres** e é FAT32, com o limite de 4 GB por arquivo.
O `backup-externo.ps1` avisa quando o arquivo passa de 3.500 MB — é o limite
prático antes de o FAT32 recusar a escrita.

---

## 6. O que **não** foi apagado, e por quê

| Item | Motivo |
|---|---|
| Os 5,79 GB de `.sqlite` | o pedido foi **relatar**, não apagar |
| `test_feature.out` | não se sabe de onde vem |
| `AUDITORIA_IA_HISTORICO_FOLLOWUP…md` | trabalho do dono, não deste escopo |
| `ProspectController.php`, `prospeccao/index.blade.php` | trabalho do dono, em andamento |
| Os 34 arquivos de backup da VPS | são o estado real de produção; o snapshot do passo 2 ainda não foi guardado |
| As 3 cópias do `.env` | idem, e envolver credencial de produção |
| Os 14 dumps vazios | evidência do incidente; a retenção apaga sozinha até ~03/11 |
| `D:\Backups\...` | do dono |

---

## Documentos relacionados

- [Checklist de publicação](CHECKLIST_PUBLICACAO.md)
- [Fase A.1-B — Backup externo automatizado](FASE_A1B_BACKUP_EXTERNO_AUTOMATIZADO.md) — diagnóstico do backup vazio
- [Fase A.1 — Backup externo](FASE_A1_BACKUP_EXTERNO.md)
- [Backup externo — uso e agendamento](../../deploy/externo/README.md)
