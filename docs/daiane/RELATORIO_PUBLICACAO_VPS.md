# Publicação da implementação Daiane na VPS

**Data da publicação:** 07/11/2026 (UTC)
**Servidor:** `srv2029972` (Ubuntu, PHP 8.5.4, MariaDB 11.8.6, nginx + php8.5-fpm)
**Aplicação:** `/var/www/ljsmartclinic`
**URL:** https://xn--ljaltomao-s2a7b.com.br

## 1. Resultado

A implementação da Daiane foi publicada em produção preservando o trabalho
multi-vertical que existia apenas na árvore da VPS (Pilates, laser e massagem),
os demais tenants e os módulos já existentes.

| Item | Valor |
| --- | --- |
| Branch ativa na VPS | `release/daiane-vps-20261107` |
| Commit de código publicado | `5548d8762ff918d26816fce76c96cbd38126d34c` |
| Snapshot real da produção (pré-publicação) | `81262f7a971a6991b3ccb5472f1c0822c1088953` |
| Base histórica da VPS | `7cb2f054d9612c45c869776454febbe34e4d118b` (`main`) |
| Bundle publicado | `release-daiane-vps-20261107.bundle` |
| SHA-256 do bundle | `c586d97ff9df2ffad0b090ae93077a2c3309d3eb3e172f33e72109884489f6c6` |

## 2. Divergência encontrada e estratégia

A branch da Daiane (`903f4ab`) é descendente de `7cb2f05`, mas a VPS havia sido
atualizada em 03/nov com trabalho local não commitado (Pilates, laser,
massagem, catálogo maior). Publicar a branch diretamente teria removido esse
trabalho.

Por isso a release foi reconstruída em duas etapas:

1. Snapshot fiel da árvore real da VPS, sem tocar nos arquivos:
   commit `81262f7` (722 arquivos), criado a partir de um índice temporário.
2. Merge de `release/daiane-vps-20261106` (branch Daiane sobre o espelho antigo)
   sobre `81262f7`, com 41 conflitos resolvidos preservando os dois lados:
   - **VPS:** catálogo Pilates/laser, `VerticalManager` multi-vertical,
     `TenantProvisioner` com ID forçado + `verticals`, migrations idempotentes,
     telas `platform/clinicas`, `profissionais`, 9 papéis no seeder.
   - **Daiane:** financeiro, atendimentos, contas a pagar, fornecedores,
     pagamentos, duplicatas, relatórios/CSV, agenda (`dia`, `mes`,
     `disponiveis`, `reagendar`, `presenca`), `DemoDaianeSeeder`,
     idempotência de consumo por `appointment_id`.
   - **União manual:** `routes/web.php`, `layouts/app.blade.php`,
     `CatalogoModulos` (módulo `atendimentos` + `situacaoParaClinica`),
     `menu-modulos.blade.php`.

Foi ajustada uma incompatibilidade encontrada na suíte: a view
`prescricoes/index.blade.php` (lado Daiane) usava `$profissionais`, enquanto o
controller mantinha `$profissional` (profissional logado). A view foi alinhada
ao controller, preservando o comportamento da VPS.

## 3. Validação antes da publicação

| Verificação | Resultado |
| --- | --- |
| Sintaxe PHP (`php -l`) | 91 arquivos, 0 erros |
| Suíte completa (PHPUnit) | **708 testes, 708 aprovados, 2.810 asserções, 0 falhas, 2 risky pré-existentes** |
| Rotas | 301 rotas carregadas sem conflito |
| Blade | todos os templates compilados |
| Pint | aplicado (`PacienteController` normalizado) |
| `composer.json` / `package.json` | inalterados |

## 4. Backups (pré-publicação)

Diretório: `/var/backups/ljsmartclinic/release-daiane-20261107_013324`

| Arquivo | SHA-256 |
| --- | --- |
| `saas_central.sql.gz` | `e13ea375ce22e8aede4c7f13192d66b51711bdfe9f02e37806d700d04258a8d0` |
| `clinic_000001.sql.gz` | `59884bb92c00389d43adf54ec388c22d42d6028bc479dc1c0e9ca037923de3b7` |
| `clinic_000005.sql.gz` | `1f60d8d63796be6a1427e7f63cd4fe9107bf1d70d55b86f2acba8253f0bfde8a` |
| `app_files.tar.gz` | `f46364148719b4c16462449ddd20cd75372c39d58b6493fc503a7bd0ae15a5b1` |
| `env.backup` | `2ec83510804593856715e0f9ee71692f585018c550b32ae31d6b08ba8c912790` |

Validações executadas: `sha256sum -c`, `gzip -t`, `tar -tzf` e restauração real
dos três bancos em bases descartáveis (78, 54 e 61 tabelas na época). A cópia
descartável foi removida depois.

## 5. Publicação executada

1. Bundle transferido e verificado na VPS (`sha256sum` = `c586d97f...`).
2. HEAD apontado para o snapshot `81262f7` e índice sincronizado com
   `git reset --mixed` (sem `reset --hard`, sem `clean`, sem tocar na árvore).
3. `git switch release/daiane-vps-20261107` — 183 arquivos atualizados,
   `HEAD = 5548d87`.
4. `composer dump-autoload --optimize` — 5.027 classes.
5. `npm run build` — assets gerados (`app-BbG7tucX.css`, `app-CBg-kT3m.css`,
   `app-DYSjGEp_.js`).
6. `php artisan tenants:migrate --force`.
7. `php artisan optimize:clear` + `php artisan optimize`.
8. Reload de `php8.5-fpm` e `nginx`.

### Migrations de tenant

Antes da publicação:

- `clinic_000001` (Malheiro): 54 tabelas; sem o bloco de fisioterapia/fiscal.
- `clinic_000005` (Daiane): 61 tabelas; com fisioterapia/fiscal até
  `2026_11_03_000001`.

Depois da publicação, **as duas clínicas ficaram com 64 tabelas** e receberam:

- `2026_11_05_081447_add_financeiro_e_atendimento_fields_to_tables`
- `2026_11_05_081450_create_accounts_payable_tables`

Colunas confirmadas nas duas bases: `payments.paciente_id`,
`appointments.area`, `consumo_sessao.appointment_id`, `consumo_sessao.reposicao`.
Tabelas confirmadas: `accounts_payable`, `accounts_payable_pagamentos`,
`financeiro_categorias`.

> A Malheiro também recebeu o bloco aditivo de fisioterapia/fiscal porque a
> migration nova de financeiro altera `pacotes_sessao`/`consumo_sessao`, que
> precisam existir. Nenhum dado foi apagado ou reimportado; nenhum seeder de
> demonstração foi executado.

## 6. Verificações pós-publicação

| Verificação | Resultado |
| --- | --- |
| `HEAD` da VPS | `5548d87` em `release/daiane-vps-20261107` |
| Árvore | apenas 49 arquivos não versionados de backup/lixo preservados |
| `.env` | SHA-256 idêntico ao backup (`2ec83510...`) |
| `https://.../login` | 200 |
| `/up` | 200 |
| `/` | 302 para `/login` |
| Assets do build | 200 (CSS e JS) |
| Rotas autenticadas (`/agenda`, `/atendimentos`, `/financeiro/contas-pagar`, `/financeiro/fornecedores`, `/relatorios/receitas`, `/pilates/turmas`, `/laser/regioes`, `/fiscal`) | 302 para login (rotas registradas, sem 404/500) |
| `php8.5-fpm` / `nginx` | ativos |
| Erros novos no `laravel.log` | nenhum após a publicação |
| Fiscal | todos os provedores desligados (`sendnf`, `focusnfe`, `nfeio`, `plugnotas`, SendNF direto) |
| Fila | `sync` (sem worker; nenhum envio real de WhatsApp disparado) |

### Preservação de dados e isolamento

| Clínica | Pacientes | Agendamentos | Profissionais |
| --- | --- | --- | --- |
| `clinic_000001` — Malheiro | 496 | 0 | 0 |
| `clinic_000005` — Daiane | 767 | 1.362 | 5 |

Os números distintos confirmam que cada tenant continua no seu próprio banco,
sem reimportação de planilhas nem mistura de dados.

### Módulos preservados

- Catálogo com `turmas_pilates`, `regioes_laser`, `fiscal`, `atendimentos` e
  `situacaoParaClinica`.
- `VERTICAIS = [odontologia, fisioterapia, pilates, laser]`.
- Rotas `turmas_pilates.index`, `regioes_laser.index` e `modulos.planejado`.
- Nenhuma alteração em DNS, SSL, Evolution API, n8n ou credenciais.

## 7. Rollback

**Código (volta exatamente à árvore pré-publicação):**

```bash
cd /var/www/ljsmartclinic
git switch prod-real-20261107      # 81262f7 = snapshot fiel da produção
composer dump-autoload --optimize
npm run build
php artisan optimize:clear && php artisan optimize
systemctl reload php8.5-fpm nginx
```

Referências mantidas:

- `prod-real-20261107` = `81262f7` (árvore real de produção antes da release)
- `prod-rollback-20261107` = `7cb2f05` (commit anterior)
- `main` = `7cb2f05`

O schema novo é aditivo (colunas/tabelas), portanto o código antigo continua
funcionando sobre ele. Se for necessário reverter o schema também, restaurar os
dumps de `clinic_000001` e `clinic_000005` do diretório de backup.

**Banco (restauração completa):**

```bash
gunzip -c /var/backups/ljsmartclinic/release-daiane-20261107_013324/clinic_000001.sql.gz | mysql -u root clinic_000001
gunzip -c /var/backups/ljsmartclinic/release-daiane-20261107_013324/clinic_000005.sql.gz | mysql -u root clinic_000005
gunzip -c /var/backups/ljsmartclinic/release-daiane-20261107_013324/saas_central.sql.gz   | mysql -u root saas_central
```

## 8. Observações

- Os papéis/permissões existentes foram mantidos como estavam (6 papéis por
  clínica). Nenhum seeder foi executado em produção. Os controladores novos
  usam permissões já existentes (`agenda`, `pacientes`, `financeiro`,
  `relatorios`), portanto não há bloqueio de acesso por permissão ausente.
- A branch `release/daiane-vps-20261107` ainda não foi enviada ao GitHub; a
  publicação foi feita por bundle direto na VPS.
- Este relatório foi adicionado em um commit de documentação posterior ao
  commit de código `5548d87`; ele não altera nenhum arquivo da aplicação.
- Os 49 arquivos não versionados (backups antigos de `ProspectController`,
  `backup_antes_remocao/`, `create|store`, `.scss.backup*`, etc.) continuam no
  disco, intocados.
