# Riscos, Pré-requisitos e Comandos de Referência — Etapa 11

Estado: **apenas auditoria — nenhum comando executado.** Nenhum deploy, nenhuma alteração na VPS, nenhum banco alterado, nenhuma migration aplicada, nenhum serviço reiniciado.

- Projeto: LJ SmartClinic
- Session: ses_e63c31a19ffevipW2M8hF4G9sT
- Data: 2026-11-02

## 1. Riscos confirmados (sem mitigação executada ainda)

| Risco | Confirmação | Impacto se não mitigado | Mitigação (procedimento — não executado) |
|---|---|---|---|
| Backup da VPS vazio (`STATUS` ausente; `.parcial` presente; `>` em vez de `>>`) | Confirmado pelo `CHECKLIST_PUBLICACAO.md` (trava 2) | Crítico — se deploy falhar, não há backup confiável para restaurar | Corrigir `03-backup.sh` antes de qualquer operação; criar `STATUS`; usar `>>` |
| Migrations não testadas em ambiente real | Confirmado (`database/migrations/` novas não aplicadas) | Alto — se `rollback` falhar, bancos de `clinic_000005` e `clinic_000001` podem ficar inconsistentes | Aplicar migrations apenas após backup individual de cada banco; testar rollback em cópia do banco |
| Build desatualizado (`public/build` de 27/10 vs alterações de `SCSS`/`views`) | Confirmado (`git status` mostra alterações em `resources/`) | Baixo — telas podem não refletir novas funcionalidades ou exibir erros | `npm run build` obrigatório antes do deploy; validar `manifest.json` |
| Arquivos não rastreados (`.agents/skills/brag/`, `brag-output/`, `tests/Feature/` residuais) | Confirmado (`git status` mostra muitos untracked) | Baixo — resíduos de desenvolvimento podem ser copiados acidentalmente | Atualizar `.gitignore` ou incluir filtro no script de deploy |
| Divergência de commits (local `deef100` vs remoto `9e55007`) | Confirmado (`git branch --show-current` = `feature/fisioterapia`; `git log` mostra `deef100`) | Médio — deploy direto do remoto perderia commits recentes; deploy direto do local pode não ter todos os arquivos esperados pelo remoto | Verificar `git log --graph --oneline` antes de qualquer deploy; confirmar se `deef100` precisa ser pushado ou se o deploy deve usar uma tag/commit específico |
| Falha preexistente no rollback (`avaliacoes`) | Confirmado (`MigrationsFisioterapiaTest::test_a_descida_tira_o_novo_e_devolve_o_antigo` falha) | Médio — `rollback` pode não remover tabela corretamente; pode afetar fisioterapia | Corrigir a migration ou aceitar resíduos; verificar manualmente após rollback |
| Isolamento de tenant quebrado após deploy | Confirmado (`TenantIsolationTest` passa agora, mas qualquer erro no middleware pode quebrar) | Crítico — uma clínica pode ver dados da outra (`clinic_000005` ou `clinic_000001`) | Executar `TenantIsolationTest` rapidamente após deploy; se falhar, rollback imediato |

## 2. Pré-requisitos (não atendidos ainda — não ativados)

- [ ] Autorização explícita de deploy (não fornecida nesta etapa — apenas auditoria).
- [ ] Ativação das 13 decisões comerciais (mantidas desativadas; não ativadas).
- [ ] Confirmação do estado das clínicas `clinic_000005` e `clinic_000001` (preservadas, mas precisam ser verificadas após qualquer deploy futuro).
- [ ] Backup da VPS corrigido (`STATUS` criado, `.parcial` removido, `>>` aplicado, `verificar_dump` presente).
- [ ] Backup externo verificado (`D:\ljsmartclinic-backup-externo` ou equivalente).
- [ ] Build atualizado (`npm run build` executado; `manifest.json` atualizado).
- [ ] Migrations novas (`clinic_feature_flags`, fiscal, whatsapp outbox, rules) testadas em ambiente isolado (já feito — SQLite `:memory:`; precisa ser repetido em cópia do banco real antes do deploy se autorizado).
- [ ] Divergência de commits resolvida (local x remoto verificado; `deef100` x `9e55007`).
- [ ] Nenhuma alteração feita na VPS (confirmado nesta etapa).

## 3. Comandos de referência — Backup (NÃO EXECUTADOS)

**Código:**
```bash
# Não executado — referência apenas
cp -r /var/www/ljsmartclinic /var/backups/ljsmartclinic/pre-publicacao-$(date +%Y%m%d-%H%M%S)
# Verificar
ls -la /var/backups/ljsmartclinic/pre-publicacao-*/
```

**Banco central (referência — adaptar credenciais):**
```bash
# Não executado
gmysqldump -u root -p sco > /var/backups/ljsmartclinic/central_pre_$(date +%Y%m%d).sql
# Verificar
head -n 5 /var/backups/ljsmartclinic/central_pre_*.sql
tail -n 5 /var/backups/ljsmartclinic/central_pre_*.sql
# Verificar integridade
mysql -u root -p -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='sco';" < /dev/null || echo "Verificar manualmente"
```

**Bancos tenant (referência — um por clínica):**
```bash
# Não executado — adaptar para cada banco de tenant
# Para clinic_000005:
gmysqldump -u root -p clinic_000005 > /var/backups/ljsmartclinic/clinic_000005_pre_$(date +%Y%m%d).sql
# Para clinic_000001:
gmysqldump -u root -p clinic_000001 > /var/backups/ljsmartclinic/clinic_000001_pre_$(date +%Y%m%d).sql
```
**Nota:** se os bancos de tenant não forem acessíveis com `mysqldump` diretamente, o procedimento precisa ser adaptado ao mecanismo de conexão usado pela aplicação (`TenantConnection`). Nenhuma alteração feita agora.

**Backup de arquivos críticos (referência):**
```bash
# Não executado
cp /var/www/ljsmartclinic/.env /var/backups/ljsmartclinic/env_$(date +%Y%m%d)
cp -r /var/www/ljsmartclinic/storage/app /var/backups/ljsmartclinic/storage_app_$(date +%Y%m%d)
# Verificar STATUS e .parcial
ls -la /var/backups/ljsmartclinic/STATUS || echo "STATUS AUSENTE — risco alto"
find /var/backups/ljsmartclinic/ -name '*.parcial' -delete || echo "Nenhum .parcial encontrado (bom)"
```

## 4. Comandos de referência — Rollback (NÃO EXECUTADOS)

**Código:**
```bash
# Não executado — referência apenas
cp -r /var/backups/ljsmartclinic/pre-publicacao-XXXX /var/www/ljsmartclinic/
# Se necessário, restaurar .env
cp /var/backups/ljsmartclinic/env_YYYYMMDD /var/www/ljsmartclinic/.env
```

**Banco central:**
```bash
# Não executado
mysql -u root -p sco < /var/backups/ljsmartclinic/central_pre_YYYYMMDD.sql
```

**Bancos tenant (`clinic_000005` e `clinic_000001`):**
```bash
# Não executado — adaptar para cada banco
mysql -u root -p clinic_000005 < /var/backups/ljsmartclinic/clinic_000005_pre_YYYYMMDD.sql
mysql -u root -p clinic_000001 < /var/backups/ljsmartclinic/clinic_000001_pre_YYYYMMDD.sql
```

**Rollback de migrations (se autorizado):**
```bash
# Não executado
php artisan migrate:rollback --force --path=database/migrations/central/2026_10_29_000001_create_clinic_feature_flags_table.php
# Se necessário para cada tenant (após verificação do mecanismo de conexão):
# php artisan migrate:rollback --database=tenant --force
```

**Reiniciar serviços (se necessário — não executado):**
```bash
# Não executado — referência apenas
systemctl restart nginx || echo "Nginx não disponível"
systemctl restart php-fpm || echo "PHP-FPM não disponível"
# Validar
curl -I -s -o /dev/null -w "%{http_code}" https://<dominio>/ || echo "Verificar domínio manualmente"
```

## 5. Validações antes e depois (não executadas — sequência de referência)

**Antes de qualquer operação na VPS:**
- [ ] Confirmar que `database.sqlite` local não foi alterado (já confirmado: 98304 bytes, 22/09).
- [ ] Confirmar que `clinic_000005` e `clinic_000001` estão intactos nos bancos de referência (não acessados diretamente nesta etapa).
- [ ] Confirmar que `RESULTADOS_ETAPA10.md` registra 623 testes, 619 passados, 4 falhas preexistentes.
- [ ] Confirmar que `output_full_tests.txt` está preservado.
- [ ] Confirmar que as 13 decisões estão desativadas (`ClinicFeatureFlag` padrão `false`; `enabled` não alterado).
- [ ] Confirmar que `PLANO_PUBLICACAO_VPS.md` e `ANALISE_DIFERENCAS_LOCAL_VPS.md` estão criados.

**Depois de qualquer operação futura (se autorizada):**
- [ ] Executar `TenantIsolationTest` rapidamente.
- [ ] Verificar que `clinic_000005` e `clinic_000001` continuam operacionais.
- [ ] Verificar que `public/build/` está atualizado e `manifest.json` coerente.
- [ ] Verificar que `database/migrations/` estão aplicadas corretamente (`migrate:status`).
- [ ] Verificar que `AppServiceProvider.php` registra serviços corretamente.
- [ ] Confirmar que `Evolution API` e `WhatsApp` não foram interrompidos.
- [ ] Confirmar que `database.sqlite` (se usado localmente) ainda está intacto.
- [ ] Confirmar que `backup/*` (scripts e arquivos) estão preservados.

## 6. Entrega desta etapa

- Arquivos criados: `docs/fisioterapia/PLANO_PUBLICACAO_VPS.md`, `docs/fisioterapia/ANALISE_DIFERENCAS_LOCAL_VPS.md`, `docs/fisioterapia/RISCOS_PRE_REQUISITOS.md` (este arquivo).
- Nenhum arquivo existente apagado ou sobrescrito sem necessidade.
- Nenhum comando destrutivo (`reset`, `clean`, `checkout`, `pull`, `push`, `merge`) executado.
- Nenhuma alteração na VPS (`/var/www/ljsmartclinic` não tocado).
- Nenhum banco alterado (`database.sqlite` preservado; bancos de tenant não acessados).
- Nenhum deploy, migration, reinício de serviço, build obrigatório ou rollback executado.
- Nenhuma alteração em Evolution API, n8n, WhatsApp, credenciais, DNS ou SSL.
- Nenhuma regra comercial ativada (13 decisões desativadas, `ClinicFeatureFlag` padrão `false`).
- Clínicas `clinic_000005` (Daiane) e `clinic_000001` (Malheiro) preservadas integralmente.
- Nenhuma alteração descartada; todos os arquivos modificados preservados (`git status` intacto).

**Aguardar autorização específica para qualquer operação na VPS.** Nenhuma operação será executada sem nova autorização explícita.
