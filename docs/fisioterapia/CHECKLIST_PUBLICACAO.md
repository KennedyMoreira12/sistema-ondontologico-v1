# Checklist de publicação — LJ SmartClinic

**Estado: preparado, não executado.** Push, deploy, migration em produção e
alteração de dados continuam **não autorizados**. Este documento existe para
que a hora de publicar seja uma sequência conferida, e não uma improvisação
com o backup do dia seguinte como plano B.

Nada aqui foi executado. Os comandos são a sequência **que seria** executada,
com o motivo de cada passo e o que fazer quando o passo dá errado.

---

## 1. Onde o sistema está hoje

| Item | Estado (21/10/2026) |
|---|---|
| Branch de trabalho | `feature/fisioterapia` |
| Commit da branch | `14f9961` |
| `origin/main` | `7cb2f05` (6 de outubro) |
| Commits a publicar | **30** |
| Arquivos alterados | **229** |
| Produção | `7cb2f05` **com 21 arquivos modificados e 136 arquivos soltos fora do commit** |
| Suíte de testes | 515 testes · 514 passam · 1 falha conhecida |
| Backup externo | automatizado, cópia verificada em `D:\ljsmartclinic-backup-externo` |
| Backup da VPS | **versão antiga, sem `STATUS`** (ver trava 2) |

Os 30 commits **não são só a adaptação para a fisioterapia**. Oito deles são
trabalho anterior que nunca foi publicado: atendimento com IA, painel de
redes sociais da clínica e prospecção comercial. Publicar a branch publica
tudo isso junto. Isso é bom — o código está mais completo hoje do que
está em produção — mas precisa ser uma decisão consciente, não um efeito
colateral.

---

## 2. As três travas

### Trava 1 — Autorização

Push e deploy **não** estão autorizados neste momento. Enquanto isso não
mudar, nada abaixo é executado. Os passos 1 a 9 existem para quando a
autorização vier.

### Trava 2 — O backup da VPS ainda é o antigo (risco alto)

O `03-backup.sh` corrigido está no repositório, mas **a VPS continua rodando a
versão antiga**. Conferido na VPS em 21/10:

- `/var/backups/ljsmartclinic/STATUS` **não existe**;
- o script que o cron executa (`/var/www/ljsmartclinic/deploy/vps/03-backup.sh`)
  **não tem** `verificar_dump`, nem `.parcial`, nem `STATUS`;
- o cron das 3:17 ainda sobrescreve o log (`>`) em vez de acumular (`>>`).

**O que isso significa na prática:** a VPS pode continuar produzindo backup
vazio. Foi exatamente o que aconteceu por 14 noites seguidas antes da Fase A.
O backup externo **recusa** copiar um dump vazio, então a falha aparece — mas
o backup não acontece, e o resultado é uma noite sem cópia válida.

**A boa notícia:** o cron aponta para um arquivo **dentro do repositório**.
O `git pull` do passo 3 leva a versão corrigida junto, e a partir daí a VPS
passa a gravar o `STATUS` e a recusar dump vazio. Publicar resolve a trava 2,
desde que o passo 3 realmente substitua aquele arquivo.

### Trava 3 — A produção tem 157 arquivos fora do commit

`git status` na VPS: 21 arquivos modificados e 136 não rastreados.
Esse material **não existe em nenhum repositório** e é o que está rodando em
produção hoje. Do lado bom, ele **já está no histórico do Git local**: são os
commits `47c2b86`, `22054a2`, `2a669d5`, `49c5d45`, entre outros, que estão
na branch `feature/fisioterapia`.

O risco do `git pull` nesta condição é real: se a branch trouxer alterações
nos mesmos arquivos que estão modificados na VPS, o pull **para** — ou, com
`git reset --hard`, **apaga o trabalho**. Por isso o passo 2 (snapshot) vem
antes do passo 3, e nenhum `reset --hard` roda sem o passo 2 feito.

---

## 3. Sequência

### Passo 0 — Janela e combinado

- [ ] Avisar quem usa o sistema (a Malheiro) sobre a janela.
- [ ] Definir o horário de término da janela.
- [ ] Confirmar que existe **acesso ao painel** e a um **canal de contato**
      durante a janela.

### Passo 1 — Backup antes de mexer

- [ ] Rodar o backup externo: `deploy\externo\backup-externo.ps1`
- [ ] Conferir o `registro.log` na origem: `resultado=OK`.
- [ ] Conferir que a pasta nova no pendrive tem `SHA256SUMS.txt` **e**
      `VERIFICACAO.txt`. Sem o marcador de verificação, a cópia é tratada
      como interrompida e refeita na próxima execução.
- [ ] Guardar o caminho da pasta nova: ela é a referência do rollback.

```powershell
# simula sem tocar em nada (opcional, para conferir o script antes)
powershell -ExecutionPolicy Bypass -File deploy\externo\backup-externo.ps1 -Simular
```

### Passo 2 — Snapshot do estado atual da VPS

Este passo existe porque a produção não está limpa (trava 3).

- [ ] Guardar o `git status` e o `git diff` da VPS:

```bash
ssh root@179.236.238.61 '
  cd /var/www/ljsmartclinic
  git status --porcelain > /root/pre-deploy-status.txt
  git diff > /root/pre-deploy-diff.patch
  tar czf /root/pre-deploy-untracked.tar.gz $(git ls-files --others --exclude-standard | head -200)
'
```

- [ ] Copiar esses três arquivos para fora da VPS.
- [ ] Confirmar que o `tar` tem conteúdo (lista de arquivos maior que zero).

### Passo 3 — Publicar o código

A ordem importa: **primeiro** o código novo, **depois** as migrations.

- [ ] Push da branch (ainda não autorizado):

```powershell
git push origin feature/fisioterapia
```

- [ ] Na VPS, como `root` (o git aborta com `dubious ownership` se rodar
      como `www-data`):

```bash
cd /var/www/ljsmartclinic
git status --porcelain          # confirmar que é o esperado
git stash push -u -m "estado da producao antes do deploy"   # sobe o material solto
git fetch origin
git checkout feature/fisioterapia     # ou: git merge --ff-only origin/feature/fisioterapia
git stash pop                          # devolve o material solto por cima
```

> O `git stash pop` **pode** dar conflito nos 21 arquivos modificados. Se der,
> **pare**: com o código novo no lugar e o material solto à mão, dá para
> resolver arquivo por arquivo com o diff do passo 2. Não use `git checkout --`
> para "resolver" conflito: isso apaga o material solto.

### Passo 4 — Dependências e front

```bash
cd /var/www/ljsmartclinic
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

- [ ] Conferir se `public/build` mudou de data — build que não gera arquivo
      novo é build que não rodou.

### Passo 5 — Migrations

Duas etapas: o banco central e o banco de cada clínica.

- [ ] Banco central:

```bash
php artisan migrate --force --no-interaction
php artisan migrate:status        # as 6 migrations da Fase C precisam estar "ran"
```

- [ ] Banco da clínica:

```bash
php artisan tenants:migrate --force --no-interaction
```

**O que essas migrations fazem, e por que a Malheiro não muda:**

| Migration | Banco | Efeito |
|---|---|---|
| `2026_10_21_000001_add_vertical_to_clinics_table` | central | coluna `vertical` com padrão `odontologia` |
| `2026_10_21_000002_add_registro_profissional_to_professionals_table` | clínica | coluna `registro_profissional` (CREFITO), `cro` preservado |
| `2026_10_21_000003_create_avaliacoes_table` | clínica | tabela nova |
| `2026_10_21_000004_create_evolucoes_sessao_table` | clínica | tabela nova |
| `2026_10_21_000005_create_pacotes_sessao_table` | clínica | tabela nova |
| `2026_10_21_000006_create_consumo_sessao_table` | clínica | tabela nova |

A coluna `vertical` entra com **`odontologia` como padrão**, então a clínica
da Malheiro continua odontológica sem ninguém mexer no dado dela. Nenhuma
migration renomeia, remove ou apaga coluna odontológica. Todas as seis têm
`down()`.

- [ ] Depois das migrations, conferir o inventário:

```bash
php artisan tenants:auditar-tabelas
```

Esse comando existe para **quebrar em vermelho** se alguma tabela odontológica
sumir. Na primeira execução em produção, ele é a prova de que a adaptação
para a fisioterapia não desfez a odontologia.

### Passo 6 — Cache

```bash
umask 022
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
```

- [ ] Conferir o dono dos arquivos de cache:

```bash
find bootstrap/cache storage/framework/views -type f ! -user www-data
```

O resultado tem que ser **vazio**. Cache com dono `root` é o 500 clássico:
`/` e `/painel` respondem 302 e só `/login` quebra.

### Passo 7 — Fumaça

- [ ] `curl -I -H "Host: SEUDOMINIO" http://127.0.0.1/login` → **200**.
- [ ] Abrir o sistema e entrar como a clínica da Malheiro.
- [ ] Abrir a agenda, o prontuário e o odontograma: **o odontograma tem que
      continuar igual**.
- [ ] Abrir o menu: os 12 módulos padronizados, com "Documentos" dentro da
      ficha e não no menu.
- [ ] Abrir um módulo que é "planejado" na odontologia (avaliação, evolução,
      pacotes) e conferir a mensagem exata:
      **"Funcionalidade planejada — ainda não disponível para uso."**
- [ ] Conferir a fila e o cache: nenhuma mensagem de uma clínica pode
      aparecer na tela de outra.

### Passo 8 — Backup depois

- [ ] Rodar o backup externo de novo e conferir `resultado=OK`.
- [ ] Conferir que a VPS gravou o `STATUS`:

```bash
ssh root@179.236.238.61 "cat /var/backups/ljsmartclinic/STATUS"
```

O esperado é `resultado=ok`. Se for `resultado=falha`, a noite anterior não
produziu backup — e o `03-backup.sh` novo só entra em vigor na **próxima**
execução do cron, ou em uma execução manual:

```bash
bash /var/www/ljsmartclinic/deploy/vps/03-backup.sh
```

- [ ] Conferir que o dump novo **não** tem 20 bytes:

```bash
ls -l /var/backups/ljsmartclinic/*.sql.gz | tail -3
```

### Passo 9 — Fechamento

- [ ] Anotar o commit que está em produção.
- [ ] Anotar o horário de cada passo (serve de registro para o próximo).
- [ ] Guardar o caminho do backup do passo 1 no pendrive.

---

## 4. Se der errado

**Não existe rollback automático.** O caminho é o código antigo e o banco
antigo.

| Situação | O que fazer |
|---|---|
| Erro de PHP no código novo | `git reset --hard <commit anterior>` na VPS e limpar o cache |
| Migration rodou com defeito | `php artisan migrate:rollback --step=1` (todas as seis têm `down()`) |
| Banco corrompido ou dado errado | restaurar o dump do passo 1 (`externo/README.md` traz o caminho) |
| Site dá 500 e o log não ajuda | `php artisan about`, depois `find bootstrap/cache storage/framework/views -type f ! -user www-data` |

Depois de qualquer rollback, **o cache precisa ser refeito** — um
`config:cache` velho aponta para código que não está mais no disco.

---

## 5. Verificações depois de publicar

- [ ] `php artisan about` — ambiente, driver de fila e de cache, URL.
- [ ] `cat /var/backups/ljsmartclinic/STATUS` → `resultado=ok`.
- [ ] `php artisan tenants:auditar-tabelas` → sem tabela faltando.
- [ ] `php artisan migrate:status` → tudo "ran".
- [ ] `storage/logs/laravel.log` sem erro novo nas últimas linhas.
- [ ] O backup externo rodou depois do deploy e gravou `resultado=OK`.

---

## 6. O que **não** entra nesta publicação

- **Nenhum dado da Malheiro é alterado.** A coluna `vertical` entra com
  `odontologia` como padrão e nenhuma migration odontológica é tocada.
- **Nada de Evolution API, n8n, WhatsApp, DNS ou SSL.**
- **Nenhuma credencial de produção é trocada.**
- **O módulo de tratamentos continua "planejado"** — não existe plano de
  tratamento da fisioterapia definido, e o catálogo não inventa um.
- **Nenhum dado fictício entra em produção.** Tudo que aparece nas telas
  de demonstração é de teste.
- **A falha conhecida da suíte não é corrigida aqui**
  (`SocialPostPanelImageTest`): é anterior a este trabalho e está fora do
  escopo autorizado.

---

## 7. Pendências que continuam abertas

| # | Pendência | Quem resolve |
|---|---|---|
| 1 | Push e deploy | autorização do proprietário |
| 2 | Os 10 pontos de confirmação da cliente (contratos, pacotes, convênios, ficha, unidades, marca, CREFITO, texto jurídico, repositório do GitHub Pages, Malheiro intocada) | cliente |
| 3 | Plano de tratamento da fisioterapia | cliente — sem isso o módulo fica "planejado" |
| 4 | Agendar o backup externo no Agendador de Tarefas | dono da máquina (comando pronto em `deploy/externo/README.md`) |
| 5 | Limpeza dos arquivos soltos na produção (10 cópias do `ProspectController.php.backup*`, 3 do `app.scss`, 3 do `platform.blade.php`, 3 do `routes/platform.php`, `backup_antes_remocao/`, `test.env`, `test.txt`) | ver `RELATORIO_LIMPEZA.md` |

O item 5 é o que a trava 3 deixa mais_visível: são ~20 arquivos de backup
jogados no meio do código, em produção, que ninguém versionou. Não são
necessários para o deploy, e podem ser removidos depois — nunca antes.

---

## Documentos relacionados

- [Fase A — Estabilização](../fisioterapia/FASE_A_ESTABILIZACAO.md)
- [Fase A.1 — Backup externo](../fisioterapia/FASE_A1_BACKUP_EXTERNO.md)
- [Fase A.1-B — Backup externo automatizado](../fisioterapia/FASE_A1B_BACKUP_EXTERNO_AUTOMATIZADO.md)
- [Fase C — Adaptação à fisioterapia](../fisioterapia/FASE_C_ADAPTACAO_FISIOTERAPIA.md)
- [Fase D — Treinamento de módulos](../fisioterapia/FASE_D_TREINAMENTO.md)
- [Relatório de limpeza pendente](RELATORIO_LIMPEZA.md)
- [Deploy — o procedimento de campo](../deploy/README.md)
