# FASE A.2 — ORGANIZAÇÃO DAS 86 ALTERAÇÕES GIT

**Data de execução:** 21/10/2026
**Projeto:** LJ SmartClinic
**Repositório local:** `C:\Users\User\Documents\projetos\sistema ondontologico`
**Status:** concluída (branch criada, 6 commits funcionais, **sem push**)

---

## 1. Resultado em uma frase

As 86 alterações que estavam soltas foram preservadas, verificadas por hash, organizadas
em uma branch de trabalho (`feature/fisioterapia`) e agrupadas em **6 commits separados
por funcionalidade** — sem descartar nada, sem `reset --hard`, sem `clean` e **sem push**.

---

## 2. Estado inicial conferido

Comandos de leitura executados antes de qualquer alteração. O estado **correspondeu ao
relatório anterior**:

| Verificação | Resultado |
|---|---|
| `git branch --show-current` | `main` |
| `git log -1` | `e1c6529 chore: checkpoint antes da IA WhatsApp` |
| `git status --porcelain` | **86** entradas → **27 `M`** + **59 `??`** (números batem com o relatório anterior) |
| `git remote -v` | `origin https://github.com/KennedyMoreira12/sistema-odontontologico-v1.git` |
| Idas e voltas destrutivas | nenhuma executada |

---

## 3. Ponto de restauração — validado de verdade

Os artefatos existiam, mas **presença não é validação**. Foram conferidos por hash e
por teste real de recuperação.

### 3.1. Hashes conferidos

| Artefato | SHA-256 | Confere com o documentado |
|---|---|---|
| `repo_main_e1c6529.bundle` | `a377af16e827d877be635d5c796ac2910a96951826ed815bb22999a1ee6501ab` | **sim** |
| `worktree_modificados.patch` | `1fb16dd05cf785deca5b28d6d76350807a23a9a3c9d6040f3342b6071515cf71` | **sim** |

### 3.2. Defeito encontrado e corrigido

O `worktree_modificados.patch` estava gravado em **UTF-16 LE** (BOM `FF FE`, 249.878 bytes) —
o PowerShell converteu na gravação. O `git` exige UTF-8, e o patch **não aplicava**
(`error: No valid patches in input`).

**Correção:** o patch foi **regenerado em UTF-8 sem BOM** via redirecionamento do `cmd`,
resultando em `worktree_modificados_utf8.patch` (122.281 bytes, sha256
`078fc3b15b7a5f953c6a6a7674f53282bf4ea55a5f27ba6f4a3d23a9a3f0cf8b`), que aplica limpo
(`git apply --check` → exit 0). O arquivo UTF-16 original foi mantido (não removido).

### 3.3. Teste real de recuperação

Executado em diretório separado, **sem sobrescrever o projeto**:

1. `git bundle verify` → *"The bundle records a complete history"* e *"is okay"*.
2. `git clone` do bundle para diretório de teste → funcionou; o clone **não contém `.env`**
   nem `.sqlite` (o bundle não carrega arquivos ignorados — correto).
3. Aplicado o patch UTF-8 → exit 0 (26 arquivos modificados).
4. Copiados os 69 arquivos não rastreados do backup.
5. **Comparação byte a byte:** 27/27 modificados **idênticos**, **0 divergências**.

**Recuperação comprovada de ponta a ponta.**

### 3.4. Defeito de código encontrado no caminho

Durante a revisão, foi detectado **dano real de texto em `routes/platform.php`**: as crases
dos comentários haviam sido destruídas (viraram TAB ou sumiram) e havia um byte de controle
**BEL (0x07)** invisível na linha 27.

- Extensão: **1 arquivo apenas** — uma varredura nos 98 arquivos tocados mostrou que todos
  os outros 97 estão íntegros.
- Gravidade: **cosmética**. Só comentários. Os 357 arquivos PHP passaram no `php -l`,
  as 202 rotas carregam e o arquivo compila.
- **Também está em produção**: md5 de produção e do local são idênticos
  (`c8391dc4234a215593ac64ac994ce46e`) — o dano foi propagado pelo deploy.
- **Correção aplicada:** comentários restaurados com prova de que **nenhuma linha de código
  mudou** (49 rotas antes = 49 rotas depois, idênticas; a única diferença foi uma linha de
  comentário que voltou a ser comentário). Cópia do arquivo antes da correção guardada em
  `faseA_preservacao\platform.php.antes_da_correcao`.

---

## 4. Branch de trabalho

Criada **a partir do estado atual, preservando as alterações** (não foi feito checkout com
descarte):

```
git checkout -b feature/fisioterapia
```

Confirmado depois: branch `feature/fisioterapia`, HEAD ainda em `e1c6529`, **86 alterações
intactas** (27 `M` + 59 `??`).

### 4.1. Identidade dos commits

A estação não tinha identidade git configurada e o `git commit` falhou. Foi usada a
**mesma identidade dos commits existentes**, configurada **apenas neste repositório**
(`--local`, sem `--global`):

```
user.name  = KennedyMoreira12
user.email = 120342021+KennedyMoreira12@users.noreply.github.com
```

---

## 5. Separação por funcionalidade

### 5.1. Método

A separação foi feita na granularidade correta, sem juntar tudo de uma vez:

- Arquivos que pertencem inteiramente a um grupo → `git add` direto.
- Arquivos **compartilhados entre grupos** (`routes/platform.php`,
  `config/services.php`, `resources/views/layouts/platform.blade.php`) → separados por
  **hunk**, e quando o hunk misturava grupos, o arquivo foi **reconstruído** (HEAD + apenas
  os blocos do grupo) e gravado no índice via `git hash-object -w` +
  `git update-index --cacheinfo`.
- Cada versão intermediária foi validada com `php -l` **antes** de virar commit.

### 5.2. Dependências respeitadas

Ordem dos commits segue as dependências reais de código, para que cada commit seja
coerente e executável:

1. **Tema/layout + infra de teste** primeiro (o `CreatesTenants` com `comPlano` é
   pré-requisito dos testes de plano).
2. **Prospecção** antes dos demais painéis — é ela que introduz o middleware
   `EnsureSuperAdmin` e o alias `superadmin`, dos quais o social depende.
3. **Social da plataforma** → **WhatsApp/IA** → **Social da clínica**.
4. **Docs/env/teste** por último.

### 5.3. Um erro detectado e corrigido durante a execução

Numa tentativa de `--amend`, arquivos do grupo social foram arrastados indevidamente para
o commit do WhatsApp (já estavam em staging). Foi detectado pelo `git show --stat` e
**corrigido**: `git reset --mixed` para o commit anterior, refazendo os dois commits na
ordem correta. Registrado aqui por transparência — o histórico final é o correto.

Também foi detectada uma omissão: a versão intermediária do `config/services.php` no commit
do WhatsApp havia perdido o bloco `api_key` (legado) da Evolution. Corrigida por
reconstrução a partir do arquivo final, preservando todo o restante.

---

## 6. Commits criados

| # | Hash | Título | Arquivos |
|---|---|---|---|
| 1 | `7399948` | `feat(ui): adiciona colapso da sidebar e ajusta marca no layout` | 4 |
| 2 | `47c2b86` | `feat(prospeccao): adiciona prospeccao comercial no painel da plataforma` | 41 |
| 3 | `22054a2` | `feat(social): adiciona publicacao nas redes sociais` | 15 |
| 4 | `2a669d5` | `feat(whatsapp): adiciona atendimento com IA e conexao por clinica` | 19 |
| 5 | `49c5d45` | `feat(social-clinica): painel de redes sociais da clinica` | 19 |
| 6 | `9e6d4e5` | `docs: atualiza .env.example e adiciona documentacao` | 4 |

Histórico final, a partir do commit-base:

```
9e6d4e5 docs: atualiza .env.example e adiciona documentacao
49c5d45 feat(social-clinica): painel de redes sociais da clinica
2a669d5 feat(whatsapp): adiciona atendimento com IA e conexao por clinica
22054a2 feat(social): adiciona publicacao nas redes sociais
47c2b86 feat(prospeccao): adiciona prospeccao comercial no painel da plataforma
7399948 feat(ui): adiciona colapso da sidebar e ajusta marca no layout
e1c6529 chore: checkpoint antes da IA WhatsApp   <-- base
```

### 6.1. Detalhe por commit

**1 — `feat(ui)`**
`resources/scss/app.scss`, `resources/views/layouts/app.blade.php`,
`resources/views/layouts/platform.blade.php` (apenas o hunk do toggle responsivo),
`tests/Concerns/CreatesTenants.php`.
Sidebar recolhível com estado em `localStorage`; nome longo de clínica quebra em linhas em
vez de ser cortado; tagline redundante removida. Dependência: nenhuma.

**2 — `feat(prospeccao)`**
38 arquivos novos + `bootstrap/app.php` (alias `superadmin`) + `routes/platform.php` (bloco
de rotas de prospecção) + menu de Prospecção.
ProspectController/Import/WhatsApp, 7 models, 5 services (`ProspectSpreadsheetReader` lê
XLSX sem dependência nova), 7 migrations central, views, comando artisan, 4 arquivos de teste.
Dependência: **introduz o middleware `superadmin`** usado pelo commit 3.

**3 — `feat(social)`**
15 arquivos: controllers, requests, models `PlatformSocialPost`/`PlatformSocialSetting`,
`Services/Platform/Social/SocialPublisher`, 2 migrations central, 4 views,
`config/services.php` (bloco `social_publish`), `routes/platform.php` (bloco `redes-sociais`).
Dependência: commit 2 (alias `superadmin`).

**4 — `feat(whatsapp)`**
19 arquivos: `ClinicWhatsAppController`, `Api/WhatsAppController`, models, `EvolutionService`,
`Services/WhatsApp/{AtendimentoIA,ClienteIA,CotaIA}`, migration tenant de colunas de IA,
`config/services.php` (`admin_key`, `api_key` legado, `atendimento_ia`), `config/tenancy.php`
(estados do bot), `routes/api.php`, teste de isolamento.
Dependência: commit 2 (alias e `EnsureSuperAdmin`).

**5 — `feat(social-clinica)`**
19 arquivos: `SocialPostController`/`SocialSettingController` do tenant, requests, models,
`Services/Social/SocialPublisher`, `SocialImageStorage`, migration tenant,
views `social/*`, `config/services.php` (blocos `social_ai`/`social_publish`), `routes/web.php`,
`SocialPostPanelImageTest`.
Mudança funcional relevante: o post da clínica passou de **geração por IA** para
**composição manual** (`StoreSocialPostDraftRequest` documenta a mudança).

**6 — `docs`**
`.env.example` (chaves do atendimento com IA — apenas valores exemplares),
`docs/atendimento-ia-whatsapp.md`, `docs/fisioterapia/FASE_A_ESTABILIZACAO.md`,
`docs/fisioterapia/AUDITORIA_FUNCIONAL_LJ_SMARTCLINIC.md`, e
`PlatformSubscriptionTest` (`criarClinica(comPlano: false)` no caso de plano inativo).

---

## 7. Segurança antes dos commits

### 7.1. Verificação de conteúdo

| Item | Resultado |
|---|---|
| `.env`, `.env.demo`, `.env.roteiro` | **não commitados** (ignorados) |
| Arquivos `.sqlite` | **não commitados** (ignorados) |
| `.env.example` | versionado — contém **apenas placeholders** (`AI_API_KEY=`, etc.), nenhum segredo real. Conferido linha a linha. |
| Senhas, tokens, chaves privadas | **nenhum** encontrado no que foi commitado |
| Dumps de banco com dados reais | **nenhum** |
| Logs com informação sensível | **nenhum** |
| Documentos de pacientes | **nenhum** |
| `database/prospeccao/*.json` | contêm **telefones e nomes de estabelecimentos comerciais públicos** obtidos de planilhas de prospecção. Não são dados de paciente nem de cliente. Decisão consciente de versionar como insumo da funcionalidade. |

Nenhum arquivo foi considerado seguro só por estar no `.gitignore` — o conteúdo foi lido.

### 7.2. Arquivo mantido **fora** dos commits

| Arquivo | Motivo |
|---|---|
| `test_feature.out` | **Resíduo vazio (0 bytes)**, provavelmente saída de execução de teste. Não é código nem documentação. Deixado fora, no diretório de trabalho. |

---

## 8. Testes executados

Suíte completa, após os commits:

| Métrica | Referência anterior | Após a organização |
|---|---:|---:|
| Testes | 420 | **420** |
| Passou | 419 | **419** |
| Falhou | 1 | **1** |
| Asserções | 1528 | **1528** |
| Risky (aviso, não falha) | 2 | **2** |

**Resultado idêntico à referência.** Nenhuma regressão introduzida pela organização.

Falha conhecida, **não corrigida** (fora do escopo e não autorizada):
`Tests\Feature\SocialPostPanelImageTest::test_criacao_com_arquivo_envia_url_armazenada_ao_webhook`
(linha 294) — falha por `validation.required` (`titulo` não enviado pelo teste).
Registrada como conhecida. O teste **não foi modificado, excluído nem desabilitado**.

Além da suíte: os 357 arquivos PHP passaram no `php -l` e as 202 rotas carregam com
`php artisan route:list --except-vendor`.

---

## 9. Verificação de integridade final

| Verificação | Resultado |
|---|---|
| `git status --porcelain` | apenas `?? test_feature.out` (resíduo vazio intencional) |
| Não rastreados originais commitados | **59 de 59** — **0 ausentes** |
| Não rastreados idênticos aos originais (backup) | **69 arquivos conferidos, 0 divergências** |
| Modificados idênticos aos originais | 26/27 byte a byte; o 27º (`routes/platform.php`) diverge **apenas em comentários**, por correção deliberada e documentada |
| Branch ativa | `feature/fisioterapia` |
| Commits à frente de `origin/main` | 6 (todos locais) |
| `git ls-remote origin HEAD` | `7cb2f054...` — **remoto intacto, no ponto de partida** |
| **Push realizado** | **NÃO** |

---

## 10. Confirmações finais

- As 86 alterações originais foram **integralmente preservadas** — nada descartado.
- Pontos de restauração **validados** por hash e por clone real do bundle.
- Branch `feature/fisioterapia` criada **sem perda de alterações**.
- Commits **separados por funcionalidade**, com dependências respeitadas.
- **Nenhum push** foi feito. O GitHub permanece no estado anterior.
- **Nenhuma alteração em produção.** Nenhum deploy. Nenhuma migration executada na VPS.
- A clínica Malheiro permanece intacta.
- O teste `SocialPostPanelImageTest` **não foi corrigido** (e permanece visível como falha conhecida).

---

## 11. Pendências

| # | Pendência | Observação |
|---|---|---|
| G1 | `routes/platform.php` em **produção** ainda tem o dano cosmético (crases/BEL) | Corrigido localmente. Não foi enviado para produção (não autorizado). O arquivo compila e funciona; a correção acompanhará o próximo deploy autorizado. |
| G2 | `test_feature.out` (0 bytes) continua no diretório de trabalho | Resíduo. Pode ser apagado quando o proprietário autorizar. |
| G3 | Blocos de `config/services.php` aparecem no commit 3 **e** no commit 5 | Consequência de o arquivo ter sido editado para dois grupos. O estado final do arquivo é **correto** e nenhuma linha do HEAD se perdeu (verificado). Se for importante ter um histórico mais limpo, o histórico pode ser reorganizado antes do push. |
| G4 | Branch ainda não publicada | Aguarda decisão do proprietário. |
