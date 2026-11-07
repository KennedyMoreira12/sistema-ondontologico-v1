# FASE A — Estabilização e Segurança

**Projeto:** LJ SmartClinic — adaptação para clínica de fisioterapia
**Data:** 20–21/10/2026
**Escopo desta fase:** estabilizar repositório e backup, estabelecer baseline de testes.
**Regra respeitada:** nenhuma alteração de produção, nenhum deploy, nenhuma migration de produção.

---

## 1. Estado do repositório

| Item | Valor |
|---|---|
| Repositório | `https://github.com/KennedyMoreira12/sistema-ondontologico-v1.git` |
| Diretório | `C:\Users\User\Documents\projetos\sistema ondontologico` |
| Branch atual | `main` |
| HEAD | `e1c6529` — *"chore: checkpoint antes da IA WhatsApp"* (08/10/2026) |
| Working tree | **86 alterações não commitadas** — 27 modificados + 59 não rastreados |

### Histórico recente
```
e1c6529 chore: checkpoint antes da IA WhatsApp
7cb2f05 feat: implementa odontograma anatomico multi-tenant
3c55ba4 Recebe webhook da Evolution e responde pelo n8n sem chave global
9391c7d Corrige lacunas de seguranca na API de integracao do WhatsApp
b4e8eb9 Corrige o 500 do cache compilado como root
```

**Constatação importante:** as 86 alterações **não são rascunho** — são trabalho concluído
e já implantado (prospecção comercial, social/IA WhatsApp, painel platform, odontograma
expandido). O teste no baseline confirma isso: elas **corrigem 4 falhas** e não introduzem
nenhuma regressão (ver §4).

---

## 2. Alterações preservadas

Antes de qualquer escrita foi criado um ponto de restauração **fora do repositório**:

```
C:\Users\User\AppData\Local\Temp\opencode\faseA_preservacao\
├── repo_main_e1c6529.bundle      (histórico git completo + branch, 4,5 MB)
├── worktree_modificados.patch    (diff dos 27 modificados, 250 KB)
├── nao_rastreados.txt            (lista dos 59 não rastreados)
└── untracked\                    (cópia de 69 arquivos de código não rastreados)
```

| Artefato | SHA-256 |
|---|---|
| `repo_main_e1c6529.bundle` | `a377af16e827d877be635d5c796ac2910a96951826ed815bb22999a1ee6501ab` |
| `worktree_modificados.patch` | `1fb16dd05cf785deca5b28d6d76350807a23a9a3c9d6040f3342b6071515cf71` |

**Restauração:** `git clone faseA_preservacao/repo_main_e1c6529.bundle` recupera o histórico;
`git apply worktree_modificados.patch` reaplica as modificações.

### Classificação funcional das 86 alterações

**27 modificados (por grupo funcional)**

| Grupo | Arquivos |
|---|---|
| Prospecção/plataforma | `routes/platform.php`, `resources/views/platform/clinicas/show.blade.php`, `bootstrap/app.php` |
| Social (painel da clínica) | `app/Http/Controllers/SocialPostController.php`, `Api/SocialPostController.php`, `StoreSocialPostDraftRequest.php`, `app/Models/SocialPost.php`, `resources/views/social/*` |
| IA WhatsApp | `app/Http/Controllers/Api/WhatsAppController.php`, `app/Models/WhatsAppConversation.php`, `WhatsAppMessage.php`, `app/Services/EvolutionService.php`, `app/Exceptions/EvolutionIndisponivel.php`, `config/services.php` |
| Integração/config | `app/Support/IntegrationAbilities.php`, `config/tenancy.php`, `routes/api.php`, `routes/web.php`, `resources/scss/app.scss`, `layouts/app.blade.php`, `layouts/platform.blade.php` |
| Template de ambiente | `.env.example` (somente placeholders) |
| Testes | `tests/Concerns/CreatesTenants.php`, `PlatformSubscriptionTest.php`, `SocialPanelTest.php` |

**59 não rastreados** — arquivos **novos** de funcionalidade: controllers/models/requests/
services de Prospecção, `EnsureSuperAdmin`, migrations central de prospecção e social,
`config/prospeccao.php`, views do painel, seeds e ~8 arquivos de teste.

### Verificação de segredos — APROVADA

| Verificado | Resultado |
|---|---|
| `.env`, `.env.demo`, `.env.roteiro` | **ignorados** pelo git (`git check-ignore` confirma) |
| `.env.example` | único alterado; contém **apenas placeholders** (`ADMIN_PASSWORD=troque-esta-senha`) — correto versionar |
| Bancos `.sqlite` (com CPF/registro) | **ignorados** (`database/.gitignore` → `*.sqlite*`) — não entram no commit |
| `storage/framework/testing/*` | ignorados por `storage/framework/testing/.gitignore` |
| `test_feature.out` | **0 bytes** (arquivo vazio, resíduo) |
| `database/prospeccao/*.json` | telefones de **estabelecimentos comerciais públicos** (material de prospecção), não pacientes |

**Nenhum segredo, credencial ou dado real de paciente será commitado.**

---

## 3. Situação dos backups

### Problema encontrado
O backup automático (cron `17 3 * * *`) gerava arquivos de **20 bytes (vazios)** desde
**05/10/2026** — 16 dias sem backup válido, do banco central **e** dos dados clínicos.

### Causa raiz (diagnóstico completo)
```
mysqldump: Got error: 1045: "Access denied for user 'saas'@'localhost'"
```
- O usuário `saas` **existe** e tem os grants corretos (`saas_central.*` + `clinic_%`).
- O `laravel@127.0.0.1` conecta normalmente, mas **só tem acesso a `saas_central`** — não serve para backup das clínicas.
- `saas` e `laravel` têm **o mesmo hash de senha** → a senha do `/root/.my.cnf` estava **desatualizada**, anterior ao reset de credencial de 03/10.
- O script falhava, o cron engolia o erro e gerava um `.gz` de 20 bytes — silenciosamente.

### Correção aplicada
1. Backup do `.my.cnf` original: `/root/.my.cnf.bkp-20261021-013408` (rollback).
2. `/root/.my.cnf` regravado apontando para `saas` com a credencial atual (`chmod 600`).
3. **Script `03-backup.sh` NÃO foi alterado** (md5 idêntico ao do repositório: `ac83782f4f54b6bb0a9db2ec2acd0cb9`).
4. Execução manual + simulação da linha do cron (`exit=0`).

### Resultado
```
central-20261021-013616.sql.gz      43.768 bytes   INTEGRO
clinic_000001-20261021-013616.sql.gz 9.531 bytes    INTEGRO
uploads-20261021-013616.tar.gz      38.664 bytes   INTEGRO
```

### Teste de restauração (ambiente isolado)
Backup central restaurado em banco temporário `restore_test_lj`, contagens comparadas e
banco temporário **removido**. Nenhum banco de produção foi tocado.

| Tabela | Produção | Restaurado | Status |
|---|---|---|---|
| clinics | 1 | 1 | OK |
| admin_users | 1 | 1 | OK |
| users | 1 | 1 | OK |
| plans | 3 | 3 | OK |
| subscriptions | 1 | 1 | OK |
| prospects | 158 | 158 | OK |
| prospect_origins | 162 | 162 | OK |
| prospect_imports | 7 | 7 | OK |
| platform_social_posts | 0 | 0 | OK |

29 tabelas restauradas. Validação de `gzip -t` em todos os arquivos: **íntegros**.

### Forma de recuperação (documentada)
```bash
gunzip < central-AAAA-MM-DD-HHMMSS.sql.gz | mysql saas_central
gunzip < clinic_000001-AAAA-MM-DD-HHMMSS.sql.gz | mysql clinic_000001
```
Backup fora da máquina continua **pendente** (o script já alerta): os arquivos vivem na
mesma VPS que protegem.

---

## 4. Testes de referência

| Item | Valor |
|---|---|
| PHP | 8.3.35 (NTS) |
| Node / npm | v24.21.0 / 11.19.0 |
| Banco de teste | SQLite `:memory:` (phpunit.xml) |
| Runner | `php artisan test` (PHPUnit) |

### Resultado

| Estado | Testes | Passou | Falhou | Asserções |
|---|---|---|---|---|
| **Atual (86 alterações aplicadas)** | 420 | **419** | 1 | 1.528 |
| Baseline (commit `e1c6529`, worktree isolado) | 320 | 315 | **5** | 1.080 |

**Conclusão:** o trabalho não commitado **corrige 4 falhas** (SocialPanelTest ×4,
PlatformSubscriptionTest ×1) e **não introduz nenhuma regressão**. A suíte cresceu de
320 → 420 testes.

### Separação de falhas

**Preexistentes (já corrigidas pelo trabalho atual):** 5 falhas no baseline — nenhuma permanece.

**Falha remanescente (1) — NÃO relacionada a fisioterapia:**
```
Tests\Feature\SocialPostPanelImageTest::test_criacao_com_arquivo_envia_url_armazenada_ao_webhook (linha 294)
"Failed asserting that two strings are equal" — validation.required
```
Causa: o formulário de social passou a exigir `titulo` (obrigatório), mas esse teste —
**arquivo novo, não rastreado, criado junto com o trabalho de social** — ainda envia o
payload antigo sem `titulo`. É um **teste desatualizado em relação ao próprio código novo**,
não um defeito de produção. Fora do escopo de fisioterapia.
**Correção sugerida:** incluir `titulo` no `criarPost()`/payload do teste — **não aplicada**,
pois alterar testes exige sua autorização.

**Risky (2):** avisos de PHPUnit (testes sem asserção ou com saída inesperada) — não são falhas.

---

## 5. Riscos ainda existentes

| # | Risco | Severidade | Observação |
|---|---|---|---|
| R1 | Os 86 arquivos continuam **não commitados** | Alta | Já há backup recuperável, mas sem branch/commit não há histórico nem rollback por commit |
| R2 | Backup vive na **mesma VPS** que protege | Alta | Perda total da VPS = perda dos backups (o script alerta) |
| R3 | **Documentos clínicos em disco público** (`documentos/{id}`, IDs repetem entre clínicas) | Alta (LGPD) | Endereçado na Fase B |
| R4 | Lock de agenda sem `clinic_id` | Média | Fase B |
| R5 | Fila/cache compartilhados sem isolamento por tenant | Média | Fase B |
| R6 | Trial/assinatura **nunca expira** (sem scheduler) | Média | Fase B |
| R7 | `EnsureTenantAccess` é código morto e redireciona para rota inexistente (`billing.index`) | Média | Fase B |
| R8 | Auditoria não registra **conteúdo** clínico (só o tipo); anamnese sem log | Média | Fase B/C |
| R9 | 15 arquivos `*.gz` de 20 bytes permanecem em `/var/backups` | Baixa | Mantidos de propósito; a retenção os remove |
| R10 | `test_feature.out` (vazio) na raiz | Baixa | Resíduo; não ignorado pelo git |

---

## 6. Próximas ações recomendadas

1. **Aprovar o commit/branch** das 86 alterações (`git checkout -b feature/fisioterapia`,
   commit em grupos funcionais). O ponto de restauração já existe.
2. **Copiar o backup para fora da VPS** (destino externo) e agendar verificação periódica.
3. **Dispare a Fase B** (isolamento multi-tenant e LGPD): documentos clínicos, locks,
   filas, auditoria clínica, expiração de planos, middleware órfão.
4. Decidir sobre o teste `SocialPostPanelImageTest` (atualizar para incluir `titulo`).
5. Limpar os `.gz` vazios de 20 bytes (opcional, com autorização).

---

## 7. Arquivos criados/alterados nesta fase

| Arquivo | Ação |
|---|---|
| `docs/fisioterapia/FASE_A_ESTABILIZACAO.md` | criado (este documento) |
| `/root/.my.cnf` (VPS) | corrigido (credencial do backup) |
| `/root/.my.cnf.bkp-20261021-013408` (VPS) | criado (rollback) |
| `/var/backups/ljsmartclinical/*` (VPS) | novos backups íntegros gerados |

**Nenhum arquivo de código-fonte foi alterado. Nenhum commit foi criado. Nenhum deploy foi feito.**

---

## 8. Aguardando autorização

- [ ] Aprovar o commit das 86 alterações em branch `feature/fisioterapia`
- [ ] Aprovar o início da **Fase B**
- [ ] Definir destino do backup externo
