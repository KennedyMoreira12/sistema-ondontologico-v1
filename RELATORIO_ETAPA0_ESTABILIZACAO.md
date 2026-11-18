# Relatório da Etapa 0 — Estabilização

**Projeto:** LJ SmartClinic → Clínica Malheiro
**Data de execução:** 18/11/2026
**Ambiente:** produção — `/var/www/ljsmartclinic` na VPS `179.236.238.61` (`srv2029972`)
**Escopo autorizado:** Etapa 0 completa (E0.1 a E0.9), sem ativar nenhum módulo novo
**Resultado:** **concluída**, com 5 pendências que dependem de decisão do responsável

---

## 1. Resumo executivo

A Etapa 0 colocou a produção sob controle de versão confiável, provou que o backup
restaura e organizou o que estava solto — **sem alterar regra de negócio, credenciais
ou infraestrutura**.

O que mudou de fato:

| Antes | Depois |
|---|---|
| Produção em `release/daiane-vps-20261107` (`8ec6e7f`), sem o commit do laser | `release/malheiro-20261117` (`13c28c37`), com laser e Pilates versionados |
| Módulo `turmas_pilates` removido do código por edição local não commitada | Restaurado; oculto **só** na Malheiro via `modulos_desativados` |
| 13 arquivos do laser como não rastreados, fora do Git | Rastreados no commit; branch e tags publicadas no GitHub |
| 49 arquivos de lixo operacional na raiz do projeto | Movidos para quarentena com manifesto SHA-256 |
| Backup sem teste de restauração documentado | Restore testado e comparado com a produção |
| Nenhum procedimento de deploy/rollback escrito | `DEPLOY_E_ROLLBACK.md` e `POLITICA_DE_BACKUP.md` no repositório |

Nada foi apagado. Nenhum backup foi excluído. A retenção de 14 dias **não** foi
alterada. Nenhuma credencial foi trocada. Nenhum módulo novo foi liberado na Malheiro.

---

## 2. Escopo: o que foi e o que não foi feito

### Executado

- E0.1 — Snapshot de segurança e manifesto de integridade
- E0.2 — Branch de release
- E0.3 — Reversão da remoção global do Pilates
- E0.4 — Tags anotadas e publicação no GitHub
- E0.5 — Política de backup documentada
- E0.6 — Teste de restauração
- E0.7 — Verificação de integridade (código, migrations, build, banco)
- E0.8 — Quarentena do lixo operacional
- E0.9 — Procedimento de deploy e rollback

### Não executado (por não estar autorizado)

- Rotação de credenciais (só levantamento)
- Provisionamento de homologação
- Configuração de off-site
- Alteração de `.env`, Evolution API, n8n, DNS, SSL ou VPS além do previsto
- Ativação de qualquer módulo novo na Malheiro
- Redução da retenção de backup

---

## 3. Evidências por entrega

### E0.1 — Snapshot de segurança

Diretório: `/var/backups/ljsmartclinic-snapshots/estabilizacao-20261118-091946/`

| Arquivo | Conteúdo | Tamanho |
|---|---|---|
| `codigo-*.tar.gz` | Código completo, incluindo `.git` e `.env`; sem `vendor`, `node_modules`, caches, logs e uploads | 32 MB |
| `saas_central-*.sql.gz` | Dump do banco central | 52 KB |
| `clinic_000001-*.sql.gz` | Dump da Malheiro | 62 KB |
| `clinic_000005-*.sql.gz` | Dump da Daiane | 179 KB |
| `schema-*.sql.gz` (3) | Somente estrutura | 7,7 / 8,2 / 8,2 KB |
| `manifesto-rastreados.sha256` | SHA-256 dos arquivos versionados | 905 arquivos |
| `manifesto-nao-rastreados.sha256` | SHA-256 do que estava fora do Git | 62 arquivos |
| `estado-git.txt` | Branch, HEAD, refs de origem e status | — |
| `contagens-referencia.txt` | Base de comparação do restore test | — |
| `SHA256SUMS.final` | Hash de todos os artefatos acima | 11 entradas |

Integridade conferida **duas vezes**: na criação e depois de mover o diretório para
fora da varredura de retenção. Resultado: **11/11 OK**.

Contagens de referência gravadas no snapshot:

```
saas_central.clinics = 3
clinic_000001.pacientes = 621
clinic_000001.migrations = 74
clinic_000001.tabelas = 73
clinic_000001.fks = 75
laser_regioes / laser_pacotes / laser_aplicacoes = 0 / 0 / 0
```

Schemas: **79 tabelas** na central, **73** em cada clínica.

---

### E0.2 — Branch de release

| Item | Valor |
|---|---|
| Branch | `release/malheiro-20261117` |
| Base | `13c28c37` — `feat(laser): módulo de regiões, pacotes-modelo e histórico de aplicações` |
| Commit atual da branch | `5cba5bd` (documentação da Etapa 0) |
| Branch anterior | `release/daiane-vps-20261107` — **não foi movida** |

O commit `13c28c37` **não existia no repositório da VPS**: o módulo do laser havia
entrado por pacote manual, não por Git. Foi necessário `git fetch` antes de qualquer
troca de branch.

Situação encontrada e tratada:

- 6 arquivos rastreados modificados no working tree;
- 35 arquivos não rastreados;
- 13 deles eram exatamente o código do laser, idêntico byte a byte ao commit
  (verificado com `git hash-object` × `git rev-parse 13c28c37:<arquivo>`, **0 divergências**).

Procedimento aplicado:

1. Tag de segurança no estado corrente (`pre-estabilizacao-20261118` = `8ec6e7f`).
2. Backup extra dos 13 arquivos (tar + cópia com caminhos preservados no snapshot).
3. `git checkout 13c28c37 -- routes/web.php app/Support/Modulos/CatalogoModulos.php`.
4. Remoção dos 13 do working tree (cópias já preservadas).
5. `git checkout -B release/malheiro-20261117 13c28c37`.

O primeiro checkout foi recusado porque 4 arquivos tinham conteúdo igual a `13c28c37`
mas o Git os via como modificados em relação ao HEAD. Foram preparados no índice e a
troca foi refeita sem perda.

**Resultado:** branch limpa, 0 modificados, 0 não rastreados (os 25 restantes foram
tratados no E0.8).

---

### E0.3 — Pilates: reverter a remoção global

**Problema:** uma edição local não commitada removeu o módulo `turmas_pilates` e as
rotas `pilates/turmas` de todo o sistema. A intenção do responsável era esconder o
Pilates **apenas na Malheiro**.

**Diagnóstico:** o código de `13c28c37` tinha dois módulos distintos:

| Chave | Vertical | Descrição |
|---|---|---|
| `turmas` | comum a todas | Turmas com grade semanal, calendário e presença |
| `turmas_pilates` | Pilates | Módulo planejado de turmas de Pilates |

A Malheiro **já ocultava** `turmas` por `modulos_desativados`. Portanto reverter a
remoção global não expõe nada na Malheiro: a vertical dela é `odontologia`, e
`turmas_pilates` só é liberado para a vertical `pilates`.

**Ação:** `routes/web.php` e `app/Support/Modulos/CatalogoModulos.php` restaurados
para `13c28c37`; `turmas_pilates` presente novamente nos dois arquivos.

**Ajuste no banco central** (`saas_central.clinics`, id 1):

```
antes:  ["turmas"]
depois: ["turmas", "turmas_pilates"]
```

**Validação do menu** (`VerticalManager::modulosVisiveis`):

| Clínica | `turmas` | `turmas_pilates` | `regioes_laser` |
|---|---|---|---|
| Malheiro (id 1, odontologia) | NÃO | NÃO | NÃO |
| Daiane (id 5, fisio + pilates + laser) | SIM | SIM | SIM |

**Testes** (`LaserTest` + `MultiVerticalTest`) em worktree separado, SQLite em memória,
detached em `13c28c37`:

```
OK (29 tests, 87 assertions)
```

O `MultiVerticalTest.php` de produção estava na versão antiga — o pacote manual do
laser não o incluiu. O checkout para `13c28c37` corrigiu o arquivo (7 inserções,
5 remoções, relativas ao teste do laser).

---

### E0.4 — Tags e publicação

Tags **anotadas**, criadas no repositório local (a deploy key da VPS é somente leitura):

| Tag | Aponta para | Significado |
|---|---|---|
| `release-malheiro-20261117` | `13c28c37` | Marco do código validado nesta etapa |
| `pre-estabilizacao-20261118` | `8ec6e7f` | Estado imediatamente antes da Etapa 0 |

Publicadas no GitHub e confirmadas por `git ls-remote`:

```
13c28c37…  refs/heads/release/malheiro-20261117
13c28c37…  refs/tags/release-malheiro-20261117^{}
8ec6e7f…   refs/tags/pre-estabilizacao-20261118^{}
```

O push saiu da máquina de desenvolvimento via HTTPS. A `main` local permaneceu em
`e281a0f7`, intocada.

---

### E0.5 — Política de backup

Documento: `POLITICA_DE_BACKUP.md` (commitado em `5cba5bd`).

Fatos verificados no servidor, não presumidos:

| Item | Situação real |
|---|---|
| Agendamento | `/etc/cron.d/ljsmartclinic-backup` — 03:17 diário |
| Último backup | `rotulo=20261118-031702`, `resultado=ok`, `detalhe=backup completo` |
| Escopo | Banco central + 1 dump por clínica ativa + uploads |
| Retenção | `DIAS_RETENCAO=14` — **inalterada** |
| Backups apagados nesta etapa | **nenhum** |
| Off-site | **inexistente** — nenhuma cópia fora da VPS |
| Alarme de falha | **não existe** — é preciso ler `STATUS` ou o log |

**Achado que exigiu ação:** a limpeza de retenção usa `find` **recursivo** sobre
`*.sql.gz` e `*.tar.gz`. Qualquer snapshot guardado dentro de
`/var/backups/ljsmartclinic` seria apagado após 14 dias. Por isso os artefatos de
longo prazo foram movidos para um diretório irmão, fora da varredura:

```
/var/backups/ljsmartclinic-snapshots/
├── estabilizacao-20261118-091946/
├── pre-laser-20261117-191422/
├── pre-laser-20261117-191422.tar.gz
└── quarentena-20261118-092801/
```

O script também foi lido por inteiro: ele não confia em `gzip -t` sozinho — exige
conteúdo mínimo de 1.000 bytes, assinatura `dump 10`, e só renomeia o arquivo para o
nome definitivo após aprovação (`*.parcial` → definitivo).

---

### E0.6 — Teste de restauração

Dumps usados: `central-20261118-031702.sql.gz` e `clinic_000001-20261118-031702.sql.gz`.

Restaurados em bancos descartáveis (`restore_test_central`, `restore_test_clinic_000001`),
criados e removidos com `mysql -u root` via socket — o usuário da aplicação (`saas`)
não tem privilégio nesses bancos.

| Métrica | Produção | Restaurado | Resultado |
|---|---|---|---|
| `saas_central.clinics` | 3 | 3 | OK |
| `clinic_000001.pacientes` | 621 | 621 | OK |
| `clinic_000001.migrations` | 74 | 74 | OK |
| tabelas da Malheiro | 73 | 73 | OK |
| FKs da Malheiro | 75 | 75 | OK |
| `laser_regioes` | 0 | 0 | OK |
| `saas_central.migrations` | 83 | 83 | OK |
| Engine / collation | InnoDB / utf8mb4_unicode_ci ×73 | igual | OK |
| `CHECK TABLE pacientes` / `clinics` | — | OK | OK |

Os bancos de teste foram dropados ao final. Produção confirmada intacta depois do
teste (621 pacientes, 3 clínicas).

**Conclusão:** o backup de 18/11 restaura de fato. Não é apenas um arquivo que abre.

---

### E0.7 — Integridade de código e banco

| Verificação | Resultado |
|---|---|
| `php -l` nos 14 arquivos-chave | 0 erros |
| `composer validate --no-check-publish` | `./composer.json is valid` |
| Migrations Malheiro × Daiane | 74 × 74, **0 diferenças** |
| Migrations pendentes (tenant) | **nenhuma** |
| `public/build/manifest.json` | 16/11 10:55 — nenhum fonte mais novo |
| Arquivos em `public/build` | 7 |
| Paridade com `13c28c37` (4 arquivos críticos) | 0 divergências |

**Achado registrado, não corrigido:** a central (`saas_central`) contém 5 tabelas que
pertencem ao tenant, possivelmente resquício de instalação anterior:

```
appointments, pacientes, payments, whatsapp_conversations, whatsapp_messages
```

Não há evidência de uso pela aplicação, mas **não foram removidas**: remover tabela
não faz parte da Etapa 0 e exige análise. Fica como item para a Etapa 1.

---

### E0.8 — Quarentena do lixo operacional

- 49 arquivos (25 entradas de `git status`, incluindo diretórios) movidos para
  `/var/backups/ljsmartclinic-snapshots/quarentena-20261118-092801/`.
- Conteúdo: `ProspectController.php.backup*` (9 variações), `platform.php.bak`,
  `app.scss.backup`, views `.backup`, `backup_antes_remocao/`, entre outros.
- **Nada foi apagado** — os arquivos foram movidos, com estrutura de diretórios preservada.
- Verificação prévia: nenhuma referência a esses nomes em `app`, `config`, `routes`,
  `resources`, `database` ou `tests`.
- Manifesto SHA-256 gerado: 49 arquivos, hash do manifesto
  `9766f5317ac4d37639c891a55bf456a111b8f90372fd57675b41ac2bb19432a8`.
- Diretório vazio remanescente (`backup_antes_remocao/`) removido após confirmar 0 arquivos.

**Resultado:** `git status` com **0 entradas**; 347 rotas preservadas.

---

### E0.9 — Deploy e rollback

Documento: `DEPLOY_E_ROLLBACK.md` (commitado em `5cba5bd`).

Cobre: pré-requisitos, backup obrigatório antes do deploy, passo a passo (código,
dependências, migrations, front-end, cache, verificação, smoke test), rollback por
tipo de problema (código, migration, banco, feature flag, conteúdo) e a lista do que
nunca fazer.

Registra duas restrições reais do ambiente:

- a deploy key da VPS é **somente leitura** — `git fetch` funciona, `git push` não;
- produção roda `composer install --no-dev`, portanto **não tem PHPUnit**. Testes
  rodam em worktree separado com dependências de desenvolvimento.

---

## 4. Verificação final da produção

Executada ao encerrar a etapa:

| Item | Estado |
|---|---|
| Branch / commit | `release/malheiro-20261117` @ `5cba5bd` |
| Árvore de trabalho | limpa (0 entradas) |
| Tags locais | `release-malheiro-20261117`, `pre-estabilizacao-20261118` |
| Rotas | 347 total — 11 de laser, 9 de turmas |
| Arquivos com "laser" rastreados | 13 |
| Backup | `STATUS` = `resultado=ok`, `rotulo=20261118-031702` |
| Snapshot protegido | 11/11 arquivos com hash OK |
| Quarentena | 49 arquivos |
| Malheiro | `turmas` NÃO, `turmas_pilates` NÃO, `regioes_laser` NÃO |
| Daiane | `turmas` SIM, `turmas_pilates` SIM, `regioes_laser` SIM |
| Disco | 79 GB livres (19% usado) |

---

## 5. Pendências

### 5.1 Bloqueiam a próxima etapa

| # | Pendência | Por que importa | Precisa de |
|---|---|---|---|
| 1 | **Destino off-site indefinido** | Hoje a VPS guarda os dados **e** os backups. Perder a VPS perde tudo. | Decisão: S3, Backblaze, segundo servidor ou pendrive com rotação |
| 2 | **Homologação não provisionada** | Sem ambiente separado, cada teste é feito em produção ou em worktree improvisado. | Autorização para criar `hml_central` / `hml_clinic_*` na mesma VPS |

### 5.2 Herdadas da auditoria, aguardando decisão

| # | Item | Risco |
|---|---|---|
| 3 | `N8N_WEBHOOK_TOKEN` vazio | Webhook sem autenticação |
| 4 | `.env` com permissão `644` | Legível por outros usuários do servidor |
| 5 | Token `*` na clínica 6 (inativa) | Credencial curinga |
| 6 | Rota `admin/test/evolution/qr` exposta | Endpoint de teste acessível |
| 7 | Rotação de credenciais | Só o plano foi levantado; execução exige autorização específica |

### 5.3 Técnicas, sem urgência

| # | Item |
|---|---|
| 8 | 5 tabelas de tenant duplicadas na central — avaliar remoção na Etapa 1 |
| 9 | Sem alarme de falha de backup — hoje depende de leitura manual do `STATUS` |
| 10 | Sem `.env.testing` — testes dependem de variáveis passadas na linha de comando |
| 11 | PHPUnit ausente em produção (esperado, mas limita validação no servidor) |

---

## 6. Como reverter esta etapa

**Código:**

```bash
cd /var/www/ljsmartclinic
git fetch origin
git checkout pre-estabilizacao-20261118
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Efeito: volta a `8ec6e7f`, sem o laser. As tabelas `laser_*` continuam no banco (vazias).

**Banco:** os dumps de antes da etapa estão no snapshot
`estabilizacao-20261118-091946` e o backup diário de `20261118-031702` está íntegro.
Restaurar sempre em banco separado primeiro — ver `POLITICA_DE_BACKUP.md` §8.

**Menu:** a alteração em `modulos_desativados` é reversível em um `UPDATE`; nenhuma
migration foi aplicada nesta etapa.

---

## 7. Próximo passo proposto — Etapa 1

A Etapa 1 é a **fundação** dos módulos seguintes: permissões, feature flags, catálogo
e as correções I1–I4 identificadas no plano. Nada dela está autorizado ainda.

Antes de começar, preciso de duas respostas:

1. **Off-site:** qual destino usar?
2. **Homologação:** autoriza criar os bancos `hml_*` na mesma VPS agora, ou prefere
   tratar isso junto com a Etapa 1?

E da autorização expressa para iniciar a Etapa 1.
