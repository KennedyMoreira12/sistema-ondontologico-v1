# Análise das Diferenças entre Local e VPS

Estado: **apenas auditoria — nenhuma alteração feita na VPS.** Nenhum `git pull`, `push`, `merge`, `checkout`, acesso remoto destrutivo ou alteração de arquivo remoto executado.

- Projeto: LJ SmartClinic
- Branch local: `feature/fisioterapia`
- Referência de produção (documento `CHECKLIST_PUBLICACAO.md`, 22/10): `origin/main` `7cb2f05`
- Referência da branch no remoto: `origin/feature/fisioterapia` `9e55007` (push feito 22/10)
- Commit atual local: `deef100` (diferente de `9e55007` — há divergência desde o push)
- Arquivos modificados (git status): **93** (local); checklist menciona **229** na branch total
- Arquivos soltos (não rastreados): **muitos** (`.agents/skills/brag/`, `app/Jobs/`, `tests/Feature/`, `docs/whatsapp/`, `docs/fisioterapia/`, `database/migrations/` novas, `resources/views/admin/fiscal/`, `resources/views/vendor/`, `config/fiscal.php`, `config/whatsapp.php`, `routes/` modificados, `backup-*`, `brag-output/`)
- Produção (referência checklist): `7cb2f05` com **21 arquivos modificados + 136 soltos**

## 1. Comparação por categoria (baseada em git local + documentos de referência da VPS)

### 1.1 Código-fonte modificado (git status — 19 arquivos modificados diretamente; checklist indica 93+)

| Arquivo (local) | Status | Implicação para VPS |
|---|---|---|
| `app/Http/Controllers/Api/EvolutionWebhookController.php` | Modificado | Evolução API — se alterado, pode afetar webhook; nenhuma alteração feita agora |
| `app/Http/Controllers/Api/WhatsAppController.php` | Modificado | WhatsApp — risco se deploy sem validação |
| `routes/api.php`, `routes/web.php`, `routes/console.php` | Modificados | Rotas novas ou alteradas; verificar compatibilidade com `origin/main` antes do deploy |
| `app/Providers/AppServiceProvider.php` | Modificado | Registro de serviços; impacto global |
| `bootstrap/app.php` | Modificado | Configuração de bootstrap; risco se houver erro no deploy |
| `app/Services/EvolutionService.php` | Modificado | Integração Evolution — não alterada nesta etapa; arquivo modificado apenas na branch |
| `app/Services/Tenant/InventarioTabelas.php` | Modificado | Proteção duplicado / concorrência — verificado; nenhuma alteração executada |
| `database/seeders/tenant/RolePermissionSeeder.php` | Modificado | Permissões; se aplicado, altera permissões |
| `resources/scss/app.scss`, `resources/views/layouts/app.blade.php` | Modificados | Frontend — requer `npm run build` |
| `resources/views/platform/prospeccao/index.blade.php` | Modificado | View alterada; se build não atualizado, pode exibir erro |

### 1.2 Arquivos novos / não rastreados (sem impacto direto na VPS ainda)

| Categoria | Arquivos novos (exemplos) | Significado |
|---|---|---|
| Controllers | `Admin/FiscalController.php`, `Api/*` novos | Funcionalidades fiscais e administrativas novas |
| Middleware | `AssignCorrelationId.php` | Correlation ID — útil para auditoria |
| Jobs | `app/Jobs/` (vários) | Trabalhos em fila — `QUEUE_CONNECTION` precisa ser verificado |
| Models (Central) | `ClinicFeatureFlag.php`, `FiscalCompany.php`, `Nfse.php`, `NfseEvent.php` | Migrations correspondentes existem; se aplicadas, adicionam tabelas |
| Models (WhatsApp) | `WhatsAppConversation.php`, `WhatsAppMessage.php`, `WhatsAppAlert.php`, `WhatsAppNote.php`, `WhatsAppOutboxMessage.php`, `WhatsAppQuickReply.php`, `WhatsAppRule.php` | Funcionalidades de WhatsApp novas; migrations `tenant/` correspondentes (`2026_10_29_000002` e `000003`) |
| Services | `Fiscal/`, `WhatsApp/*` (vários) | Lógica de negócio nova; se `AppServiceProvider.php` alterado, pode registrar serviços automaticamente |
| Migrations (Central) | `2026_10_29_000001_create_clinic_feature_flags_table.php` | Nova tabela; se aplicada, adiciona flags por clínica |
| Migrations (Tenant) | `2026_10_22_000001_create_fiscal_tables.php`, `2026_10_29_000002_create_whatsapp_outbox_table.php`, `2026_10_29_000003_create_whatsapp_rules_e_apoio_tables.php` | Migrations de tenant; aplicam-se ao banco de cada clínica; **não aplicadas agora** |
| Config | `config/fiscal.php`, `config/whatsapp.php` | Configurações novas; se não copiadas para VPS, funcionalidades podem falhar |
| Views (Admin) | `resources/views/admin/fiscal/` | Novas telas; requer build e verificação de rotas |
| Tests | `tests/Feature/Fiscal/`, `tests/Feature/WhatsAppMenusInterativosTest.php`, `tests/Feature/WhatsAppOutboxTest.php`, `tests/Feature/WhatsAppRegrasTest.php`, `tests/Unit/Fiscal/` | Cobertura de testes nova; não afeta produção diretamente |
| Backup / Docs | `docs/whatsapp/RESULTADOS_ETAPA10.md`, `docs/whatsapp/RELATORIO_TESTES.md`, `docs/fisioterapia/PLANO_PUBLICACAO_VPS.md`, `docs/fisioterapia/ANALISE_DIFERENCAS_LOCAL_VPS.md`, `output_full_tests.txt` | Documentação; nenhum impacto operacional |

### 1.3 Arquivos soltos / não rastreados que podem ser resíduos de testes ou builds locais

- `.agents/skills/brag/`
- `brag-output/`
- `ljsmartclinic-migracao-20261031-184952.tar.gz`
- `tests/Feature/` adicionais (possíveis resíduos de testes anteriores)
- `app/Console/Commands/` novos (`RecoverWhatsAppOutboxCommand.php`, `ReplayWhatsAppOutboxCommand.php`, `WhatsAppHealthCommand.php`)

**Observação:** esses arquivos não estão em `.gitignore` e podem ser acidentalmente copiados para a VPS se o deploy não filtrar. O plano de deploy deve incluir uma verificação (`.gitignore` atualizado ou script de deploy que ignore arquivos não rastreados).

### 1.4 Build do frontend (local)

- `public/build/manifest.json`: 27/10/2026 (anterior à última alteração de `resources/scss/app.scss` e `resources/views/layouts/app.blade.php` — ambas estão no `git status` como modificadas)
- `public/build/assets/`: arquivos `app-*.css` e `app-*.js` existem, mas as datas são 27/10.
- **Conclusão:** se a branch incluir alterações de frontend desde 27/10, o build atual está **desatualizado**. `npm run build` será obrigatório antes do deploy.

### 1.5 Migrations (estado)

- **Nenhuma migration aplicada em produção** (restrição respeitada).
- Migrations novas no repositório (não aplicadas):
  - Central: `clinic_feature_flags` (29/10)
  - Tenant: fiscal (22/10), whatsapp outbox (29/10), whatsapp rules (29/10)
- Se o deploy incluir `php artisan migrate`, essas tabelas serão criadas.
- **Risco:** se `clinic_000005` ou `clinic_000001` não tiverem as tabelas novas, funcionalidades fiscais/WhatsApp podem falhar até que a migration seja aplicada a cada banco de tenant.

### 1.6 Estado do banco real (local)

- `database.sqlite`: 98304 bytes, 22/09/2026 — **não alterado** (restrição respeitada).
- Nenhuma query destrutiva executada.
- Nenhuma alteração nos registros de `clinic_000005` ou `clinic_000001`.

## 2. Diferenças entre local (`feature/fisioterapia`, commit `deef100`) e referência remota (`origin/feature/fisioterapia`, commit `9e55007`)

Como a branch já foi pushada em 22/10 (`9e55007`), e o commit local atual (`deef100`) é posterior ao push, há uma **divergência** entre o remoto (`origin`) e o local. Isso significa que se o deploy usar `git pull` direto do remoto, não receberia os commits mais recentes (como `deef100`).

**Observação (sem ação):**
- A diferença de commits precisa ser verificada (`git log --graph --oneline --decorate`) antes de qualquer deploy.
- Nenhum `git pull`, `push`, `merge` ou `checkout` foi executado agora.

## 3. Riscos específicos para `clinic_000005` (Daiane) e `clinic_000001` (Malheiro)

| Risco | Probabilidade | Impacto | Mitigação (procedimento, não executado) |
|---|---|---|---|
| Migrations de `clinic_feature_flags` aplicadas sem verificação | Baixa (se não executadas) | Média — novas flags desligadas por padrão; se não configuradas, funcionalidades fiscais/WhatsApp não aparecem | Aplicar migrations apenas após verificação de que `enabled` está correto; configurar flags manualmente se necessário |
| Migrations tenant (`fiscal`, `whatsapp`) aplicadas sem backup do banco da clínica | Média (se deploy incluir migrations) | Alto — se `rollback` falhar, dados operacionais podem ser afetados | Backup individual de cada banco tenant (`mysqldump`) antes de qualquer migration; rollback testado |
| Falha no rollback de `avaliacoes` (`MigrationsFisioterapiaTest` falha preexistente) | Média | Média — tabela pode não ser removida corretamente; pode afetar fisioterapia | Corrigir a migration ou aceitar que `rollback` pode deixar resíduos; verificar manualmente após rollback |
| Frontend desatualizado (`public/build` de 27/10 vs alterações de SCSS/views) | Alta (se houver alterações) | Baixo — telas podem exibir erros visuais ou não refletir novas funcionalidades | Executar `npm run build` antes do deploy; validar `manifest.json` atualizado |
| Backup da VPS vazio (trava 2 do checklist) | Alta | Crítico — se algo der errado, não há backup confiável para restaurar | Corrigir `03-backup.sh` na VPS antes de qualquer deploy; criar `STATUS`; usar `>>` no cron |
| Arquivos não rastreados (`.agents/skills/brag/`, `brag-output/`, etc.) copiados para produção | Baixa | Baixo — resíduos de desenvolvimento | Incluir `.gitignore` atualizado ou filtro no script de deploy |
| `TenantIsolationTest` falhar após deploy (isolamento quebrado) | Baixa | Crítico — clínica pode ver dados de outra | Executar `TenantIsolationTest` rapidamente após deploy; se falhar, rollback imediato |

## 4. Procedimento de comparação local x VPS (apenas auditoria — não executado)

Como não foi autorizado acesso destrutivo à VPS, a comparação foi feita por:

1. **Referência do documento existente (`docs/fisioterapia/CHECKLIST_PUBLICACAO.md`)** — descreve o estado da produção (`7cb2f05`, 21 arquivos modificados + 136 soltos).
2. **Estado local (`git status`, `git log`)** — confirma divergência (`feature/fisioterapia` local `deef100` vs remoto `9e55007`).
3. **Referência do deploy (`deploy/vps/`)** — indica que a VPS tem script `03-backup.sh` desatualizado.
4. **Arquivos de build (`public/build/`)** — confirmam que build existe, mas pode estar desatualizado.
5. **Migrations (`database/migrations/`)** — confirmam que migrations novas existem, mas não foram aplicadas.

Se fosse necessário comparar diretamente (não feito), os comandos de referência seriam:
```bash
# Não executado — referência apenas
# ssh user@vps "ls -la /var/www/ljsmartclinic/app/Models/Central/"
# ssh user@vps "ls -la /var/www/ljsmartclinic/public/build/"
# ssh user@vps "cat /var/www/ljsmartclinic/deploy/vps/03-backup.sh"
# ssh user@vps "ls -la /var/backups/ljsmartclinic/STATUS"
```

## 5. Estado final desta etapa (sem alterações na VPS)

- Nenhum arquivo na VPS alterado.
- Nenhum serviço reiniciado.
- Nenhum banco alterado.
- Nenhuma migration aplicada.
- Nenhum deploy executado.
- Nenhuma alteração em `Evolution API`, `n8n`, `WhatsApp`, credenciais, DNS ou SSL.
- Nenhuma regra comercial ativada (13 mantidas desativadas).
- `clinic_000005` e `clinic_000001` preservadas integralmente.
- Arquivos existentes (`CHECKLIST_PUBLICACAO.md`, `deploy/README.md`, `deploy/vps/`, etc.) preservados; nenhum sobrescrito sem necessidade.
- Arquivos novos criados: `docs/fisioterapia/PLANO_PUBLICACAO_VPS.md`, `docs/fisioterapia/ANALISE_DIFERENCAS_LOCAL_VPS.md`.

**Aguardar autorização específica para qualquer operação na VPS.**
