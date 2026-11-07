# Plano de Publicação na VPS — Etapa 11 (Apenas Auditoria / Preparação)

**Estado: preparado, NÃO executado.** Nenhum deploy, push, pull, merge, migration ou alteração de dados foi realizado nesta etapa.

- Projeto: LJ SmartClinic
- Branch local: `feature/fisioterapia` (commit atual: `deef100`; referência em docs: `9e55007` — branch já pushada para `origin/feature/fisioterapia` em 22/10)
- Destino futuro (não alterado): `/var/www/ljsmartclinic`
- Sessão: `ses_e63c31a19ffevipW2M8hF4G9sT`
- Data: 2026-11-02

## 1. Resumo dos achados da auditoria (sem alterar nada)

| Item | Estado local | Estado VPS (referência de docs) | Observação |
|---|---|---|---|
| Branch de trabalho | `feature/fisioterapia` (93 arquivos alterados, 30 commits) | `origin/feature/fisioterapia` em `9e55007` (push feito 22/10) | Push já existe; deploy ainda não autorizado |
| Build frontend | `public/build/manifest.json` (27/10/2026) — existente | Versão antiga (não verificada diretamente; assumida pelo checklist) | Se houver alteração de SCSS/views, `npm run build` será necessário antes do deploy |
| Banco central (`sco`) | SQLite isolado nos testes; `database.sqlite` preservado (09/2026) | Produção roda com banco real; backup antigo na VPS sem `STATUS` | **Risco alto:** backup da VPS pode ser vazio (14 noites seguidas) |
| Banco tenant | SQLite `:memory:` nos testes; migrations `tenant/` presentes | Tabelas por clínica; `clinic_000005` (Daiane) e `clinic_000001` (Malheiro) preservadas | Nenhuma alteração feita nos bancos reais |
| Migrations novas (não aplicadas) | Central: `clinic_feature_flags` (29/10); Tenant: fiscal (`22/10`), whatsapp outbox (`29/10`), rules (`29/10`) | Nenhuma aplicada em produção (restrição respeitada) | Aplicar apenas após backup verificável |
| Arquivos modificados (git) | 93 arquivos alterados; 30 commits na branch; 229 arquivos alterados segundo `CHECKLIST_PUBLICACAO.md` | Produção: `7cb2f05` com 21 arquivos modificados + 136 soltos | Diferença grande — publicar tudo junto precisa ser decisão consciente |
| Evolução / n8n / WhatsApp | Não modificados nesta etapa | Não alterados | Restrições respeitadas |
| Credenciais / DNS / SSL | Não alterados | Não alterados | Nenhuma alteração de configuração de rede |

## 2. Sequência de publicação com validações (não executada)

Cada etapa abaixo inclui **validação antes** e **validação depois**. Nenhuma é executada agora.

### Etapa A — Confirmação de autorização (manual)
- [ ] Confirmação explícita de que as 13 decisões comerciais estão ativadas (se desejado) ou permanecem desativadas.
- [ ] Confirmação de que `feature/daiane-personalizacao` (contexto) está alinhado com `feature/fisioterapia` (branch real).

### Etapa B — Backup verificável (não executado — comandos abaixo são apenas referência)
**Antes de qualquer operação:**
```bash
# Código
cp -r /var/www/ljsmartclinic /var/backups/ljsmartclinic/pre-publicacao-$(date +%Y%m%d-%H%M%S)

# Banco central (exemplo — adaptar credenciais)
gmysqldump -u root -p sco > /var/backups/ljsmartclinic/central_pre_$(date +%Y%m%d).sql
# Verificar: wc -l; head -n 5; tail -n 5

# Bancos tenant (exemplo — listar clínicas e fazer por cada uma)
# NOTA: clinic_000005 e clinic_000001 devem ser preservados integralmente
# Comando de referência (não executado agora):
# mysqldump ... clinic_000005 > ...
```
**Validação:** arquivo `STATUS` em `/var/backups/ljsmartclinic/` com hash/checksum; arquivo `.parcial` deve ser removido após verificação completa.

### Etapa C — Build do frontend (se necessário)
```bash
npm ci
npm run build
# Validação: public/build/manifest.json atualizado; assets recentes; sem erros no console de build
```
**Observação:** `public/build/` já existe (27/10). Se `resources/scss/app.scss` ou `resources/views/layouts/app.blade.php` foram alterados na branch, o build é obrigatório.

### Etapa D — Aplicação de migrations (somente após backup e autorização)
```bash
php artisan migrate --force --database=central  # apenas se necessário
# Para cada tenant: conectar ao banco correto e rodar migrations tenant
# NÃO executar agora
```
**Validação:** `migrate:status` retorna `Yes` para as novas; nenhum erro de chave estrangeira; `clinic_feature_flags` criada corretamente.

### Etapa E — Deploy para `/var/www/ljsmartclinic`
```bash
# Referência (não executado):
# git pull or rsync / deploy script / vps/ deploy procedure
# Validar: curl -I https://<dominio>/; verificar logs; verificar banco
```
**Validação antes:** backup verificado; build atualizado; migrations aplicadas; testes rápidos (isolation, audit) passam.
**Validação depois:** `TenantIsolationTest` passa rapidamente; `clinic_000005` e `clinic_000001` visíveis; auditoria não quebra; WhatsApp não interrompido.

### Etapa F — Monitoramento pós-publicação (referência)
- Verificar `/var/www/ljsmartclinic/deploy/vps/03-backup.sh`: deve ter `verificar_dump`, acumular log (`>>`), manter `STATUS`.
- Confirmar que o cron de backup funciona (não sobrescreve vazio).
- Confirmar que `clinic_000005` e `clinic_000001` estão operacionais.

## 3. Riscos específicos para Daiane (`clinic_000005`) e Malheiro (`clinic_000001`)

- **Dados operacionais:** pacientes, agendamentos, evolução, avaliacões e registros fiscais estão nos bancos de cada clínica. Nenhuma alteração foi feita agora.
- **Flag de recurso:** a migration central `clinic_feature_flags` (29/10) introduz `enabled` padrão `false`. Se aplicada sem verificação, novas funcionalidades (WhatsApp outbox, fiscal) podem ficar desligadas para ambasas clínicas até configuração manual — aceitável, mas precisa ser verificado.
- **Rollback de `avaliacoes`:** se `MigrationsFisioterapiaTest` falhar novamente em produção (falha preexistente), a tabela pode não ser removida corretamente na descida — risco de inconsistência em fisioterapia.
- **Backup da VPS:** se o backup atual for vazio (trava 2 do checklist), qualquer deploy sem backup externo verificável é risco crítico.
- **Isolamento:** se houver erro no middleware de tenant durante o deploy, uma clínica pode enxergar dados da outra — o `TenantIsolationTest` deve ser executado rapidamente após qualquer deploy.
- **WhatsApp / Evolution:** alterações nos controllers `EvolutionWebhookController` e `WhatsAppController` estão na branch modificada; se o deploy ocorrer sem validação, pode interromper atendimentos.

## 4. Pré-requisitos obrigatórios antes de qualquer deploy (não atendidos ainda)

- [ ] Autorização explícita de deploy (restrição respeitada: não executado).
- [ ] As 13 decisões comerciais confirmadas (não ativadas agora; mantidas desativadas).
- [ ] Backup da VPS corrigido (`STATUS` existe; `.parcial` removido; `>>` no cron; `verificar_dump` presente).
- [ ] Backup externo verificado (`D:\ljsmartclinic-backup-externo` conforme checklist).
- [ ] Build atualizado e validado (`public/build/` coerente com branch).
- [ ] Migrations testadas em ambiente isolado (já feito — SQLite `:memory:`).
- [ ] Testes críticos passados (`TenantIsolationTest`, auditoria, backup external).
- [ ] Confirmação de que `clinic_000005` e `clinic_000001` não serão afetados por migração ou rollback.

## 5. Comandos de backup / rollback (referência — NÃO EXECUTADOS agora)

**Backup (referência):**
```bash
# Código
cp -r /var/www/ljsmartclinic /var/backups/ljsmartclinic/pre-publicacao-$(date +%Y%m%d-%H%M%S)
# Banco central (exemplo)
gmysqldump -u root -p sco > /var/backups/ljsmartclinic/central_pre_$(date +%Y%m%d).sql
# Verificar
ls -la /var/backups/ljsmartclinic/STATUS
# Se STATUS não existe, backup é invocado até que exista
```

**Rollback (referência — apenas se autorizado):**
```bash
# Código
# 1. Restaurar código
cp -r /var/backups/ljsmartclinic/pre-publicacao-XXX /var/www/ljsmartclinic
# 2. Restaurar bancos (exemplo central)
mysql -u root -p sco < /var/backups/ljsmartclinic/central_pre_YYYYMMDD.sql
# 3. Reverter migrations (se necessário e autorizado)
php artisan migrate:rollback --force
# 4. Reiniciar serviços (se necessário e autorizado)
systemctl restart nginx php-fpm
# 5. Validar isolamento e auditoria rapidamente
```

## 6. Estado da VPS (não alterado nesta etapa)

- Nenhum arquivo em `/var/www/ljsmartclinic` foi modificado.
- Nenhum serviço reiniciado.
- Nenhum banco alterado.
- Nenhuma migração aplicada.
- Nenhuma credencial, DNS ou SSL alterada.
- Nenhuma regra comercial ativada.
- Nenhum deploy executado.

## 7. Entrega desta etapa

- Arquivo criado: `docs/fisioterapia/PLANO_PUBLICACAO_VPS.md` (este arquivo)
- Arquivo aliado criado: `docs/fisioterapia/ANALICE_DIFERENCAS_LOCAL_VPS.md`
- Nenhum comando de deploy, push, pull, migration, build ou rollback foi executado.
- Todos os arquivos existentes preservados; nenhum sobrescrito.
- Sessão preservada: `ses_e63c31a19ffevipW2M8hF4G9sT`.

**Aguardar autorização específica para qualquer operação na VPS.**
