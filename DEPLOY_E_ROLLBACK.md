# Deploy e Rollback — LJ SmartClinic

**Atualizado em:** 18/11/2026 (Etapa 0 — estabilização)
**Produção:** `/var/www/ljsmartclinic` na VPS `179.236.238.61` (host `srv2029972`)
**Branch de produção:** `release/malheiro-20261117`
**Commit de produção:** `13c28c37`

---

## 1. Referências de release

| Ref | Aponta para | Significado |
|---|---|---|
| `release/malheiro-20261117` (branch) | `13c28c37` | Produção atual (14 commits + laser) |
| `release-malheiro-20261117` (tag anotada) | `13c28c37` | Marco do código validado na Etapa 0 |
| `pre-estabilizacao-20261118` (tag anotada) | `8ec6e7f` | Estado imediatamente antes da Etapa 0 |
| `release/daiane-vps-20261107` (branch antiga) | `550e3d8` no GitHub / `8ec6e7f` local | Não usar para deploy; ficou congelada |

O commit `13c28c37` contém:

- os 14 commits de notificações, WhatsApp, assinatura, fiscal e auditoria;
- o módulo **Regiões a Laser** completo;
- os módulos `turmas` (comum) e `turmas_pilates` (vertical Pilates).

A Malheiro (odontologia) tem `modulos_desativados = ["turmas","turmas_pilates"]` na tabela `saas_central.clinics`. A Daiane (fisioterapia + pilates + laser) vê os dois.

---

## 2. Antes de qualquer deploy

- [ ] Janela combinada e responsável definido.
- [ ] Backup manual executado e `STATUS` com `resultado=ok`.
- [ ] Snapshot do código atual (ou confirmação de que a tag anterior está no GitHub).
- [ ] Suíte de testes verde no ambiente de homologação.
- [ ] Migration nova revisada, com `down()` testado em clone.
- [ ] Feature flag nova **desligada** em produção.
- [ ] Autorização escrita para a entrega.

### Backup manual obrigatório

```bash
cd /var/www/ljsmartclinic
bash deploy/vps/03-backup.sh
cat /var/backups/ljsmartclinic/STATUS
```

Só prosseguir se `resultado=ok`.

---

## 3. Deploy passo a passo

> Executar como `root` em `/var/www/ljsmartclinic`.
> O deploy key da VPS (`/root/.ssh/deploy_lj`) é **somente leitura**: `git fetch` funciona, `git push` não. Push é feito da máquina de desenvolvimento (HTTPS).

### 3.1 Código

```bash
cd /var/www/ljsmartclinic
git fetch origin
git checkout release/malheiro-20261117
git pull --ff-only origin release/malheiro-20261117
git status --porcelain      # deve estar vazio
git rev-parse --short HEAD  # deve ser o commit aprovado
```

Se `git status` não estiver vazio, **parar** e investigar. Produção não recebe deploy com árvore suja.

### 3.2 Dependências PHP

```bash
composer install --no-dev --optimize-autoloader --no-interaction
```

Produção não tem PHPUnit instalado (é `--no-dev`). Testes rodam em homologação ou em worktree com dev.

### 3.3 Migrations

```bash
php artisan migrate --force              # banco central
php artisan tenants:migrate --force      # todos os bancos de clínica
php artisan tenants:migrate --pretend    # conferir: não deve sobrar nada
```

Migrations são aditivas. Nunca editar migration já aplicada.

### 3.4 Front-end

```bash
npm ci
npm run build
```

Conferir que `public/build/manifest.json` ficou mais novo que os fontes:

```bash
find resources/scss resources/css resources/js -newer public/build/manifest.json
```

Não deve listar nada.

### 3.5 Cache

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Se `route:cache` falhar, há closure em rota — corrigir antes de continuar.

### 3.6 Verificação

```bash
php artisan route:list --except-vendor | wc -l          # ~347
php artisan route:list --except-vendor | grep -i laser  # 11 rotas
php artisan route:list --except-vendor | grep -i turmas # rotas de turmas
```

Conferir no banco:

```bash
mysql -N -D saas_central -e "select id,vertical,verticals,modulos_desativados from clinics"
```

Conferir o menu (por clínica):

```bash
php artisan tinker --execute '$c = App\Models\Central\Clinic::find(1); print_r(app(App\Services\Tenant\VerticalManager::class)->modulosVisiveis($c));'
```

### 3.7 Smoke test

- Login na Malheiro.
- Dashboard, pacientes, agenda, prontuário.
- Menu não mostra `turmas`, `turmas_pilates` nem `regioes_laser`.
- Login na Daiane: menu mostra `turmas`, `turmas_pilates` e `regioes_laser`.
- Sino de notificações abre.
- Nenhum erro novo em `storage/logs/laravel.log`.

---

## 4. Rollback

### 4.1 Regra

Rollback de código **não** desfaz migration. Rollback de banco **não** desfaz código. Escolher o rollback certo para o tipo de problema.

### 4.2 Código

Voltar para o estado anterior à Etapa 0:

```bash
cd /var/www/ljsmartclinic
git fetch origin
git checkout pre-estabilizacao-20261118
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Ou voltar para um commit específico:

```bash
git checkout <commit-aprovado>
```

**Efeito:** `8ec6e7f` não tem o módulo laser. As tabelas `laser_*` continuam no banco (vazias). O menu volta ao estado anterior.

Para voltar à release atual:

```bash
git checkout release/malheiro-20261117
```

### 4.3 Migration

```bash
php artisan migrate:rollback --step=1 --force
php artisan tenants:migrate:rollback --step=1 --force
```

Só usar migration com `down()` testado. Para o laser:

```bash
php artisan tenants:migrate:rollback --step=1 --force
```

Isso remove `laser_aplicacoes`, `laser_pacotes` e `laser_regioes` (vazias em 18/11). **Não** usar em produção sem clone validado.

### 4.4 Banco

1. Fazer dump do estado atual (mesmo com problema).
2. Restaurar o dump aprovado em banco **separado** (`clinic_000001_restore`).
3. Validar contagens.
4. Trocar a conexão da clínica de forma controlada.
5. Registrar dump usado, data e responsável.

Nunca restaurar por cima do banco em uso sem dump imediatamente anterior. Ver `POLITICA_DE_BACKUP.md` §8.

### 4.5 Feature flag

Desligar a flag por clínica tem efeito imediato, sem deploy. É o rollback mais barato para módulo novo.

### 4.6 Conteúdo publicado

- Post em rede social: não há rollback automático; remover manualmente na rede.
- Material de treinamento: mudar estado `publicado → arquivado`.

---

## 5. O que nunca fazer

- `git push --force` em branch compartilhada.
- `git reset --hard` na produção sem dump fresco.
- Restaurar dump por cima do banco em uso.
- Deploy com `git status` sujo.
- Rodar migration sem `down()` testado.
- Reduzir retenção de backup sem off-site comprovado.
- Ativar módulo na Malheiro sem autorização específica.

---

## 6. Estado da Etapa 0 (18/11/2026)

| Item | Situação |
|---|---|
| Produção no Git | `release/malheiro-20261117` = `13c28c37`, árvore limpa |
| Branch/tag no GitHub | Publicadas (`13c28c37`) |
| Pilates | `turmas_pilates` restaurado no código; oculto só na Malheiro |
| Laser | Versionado em `13c28c37`; 3 tabelas vazias nas clínicas 1 e 5 |
| Lixo operacional | 49 arquivos movidos para `/var/backups/ljsmartclinic-snapshots/quarentena-20261118-092801` |
| Backup | Diário 03:17, `STATUS=ok`, retenção 14 dias (inalterada) |
| Restore test | Aprovado (3 clínicas, 621 pacientes, 74 migrations, 73 tabelas, 75 FKs) |
| Off-site | **Pendente** — nenhuma cópia fora da VPS |
| Testes | `LaserTest` + `MultiVerticalTest`: 29 testes, 87 asserções, OK |

---

## 7. Contatos e acessos

- SSH: `ssh root@179.236.238.61` (chave já autorizada).
- Deploy key (VPS → GitHub): `/root/.ssh/deploy_lj` (somente leitura).
- Push (dev → GitHub): HTTPS com credencial da máquina de desenvolvimento.
- MySQL: aplicação usa `saas`; restore de teste usa `mysql -u root` (socket).

Nenhum segredo deve ser copiado para este documento.
