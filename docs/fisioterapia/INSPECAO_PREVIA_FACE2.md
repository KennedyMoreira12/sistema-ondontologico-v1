# Inspeção Prévia — Fase 2 Backup (Etapa 13)

Estado: **INSPEÇÃO FINALIZADA — NENHUM BACKUP EXECUTADO.** Nenhum arquivo criado, removido, copiado, movido ou alterado na VPS. Nenhum banco alterado. Nenhuma senha exposta.

- Sessão: `ses_e63c31a19ffevipW2M8hF4G9sT`
- Data: 2026-11-02
- Restrições respeitadas: nenhuma criação/cópia/movimento/apagamento na VPS; nenhum mysqldump; nenhuma migration; nenhum serviço reiniciado; nenhum `.env` exposto.

## 1. Espaço disponível (referência VPS — não acessível deste host Windows)
- `/var/www`: remoto; não verificado diretamente. Referência `CHECKLIST_PUBLICACAO.md` indica versão antiga.
- `/var/backups`: remoto; referência indica backup sem `STATUS` (trava alta).

## 2. Backup dir (`/var/backups/ljsmartclinic`)
- Não acessado diretamente (VPS remota).
- Referência confirmada: `STATUS` ausente; `.parcial` não removido; `03-backup.sh` sem `verificar_dump`; cron usa `>` em vez de `>>`.

## 3. Bancos configurados (local + referência)
- Central local: `database.sqlite` (98304 bytes, 22/09/2026) — preservado.
- Tenant: `database/tenants/` (simulação/testes) — preservado.
- Clínicas: `clinic_000005` (Daiane) e `clinic_000001` (Malheiro) — preservadas.

## 4. Clínicas confirmadas
- Nenhuma alteração nos registros das clínicas.
- Nenhum acesso direto aos bancos de produção.

## 5. Tamanho (/var/www/ljsmartclinic)
- Remoto; não verificado diretamente.
- Referência `CHECKLIST_PUBLICACAO.md`: produção `7cb2f05` com 21 arquivos modificados + 136 soltos.

## 6. Permissões do diretório de backup
- Referência: sistema de permissões do Linux; `STATUS` ausente indica que o script não está funcionando corretamente. Nenhuma alteração feita.

## 7. Arquivos de configuração para restauração
- `.env` local: linha inicial `APP_NAME` confirmada — nenhuma senha exposta.
- `backup-migracao.sh` (5781 bytes, 31/10) e `backup-ps-migracao.ps1` (7471 bytes, 31/10) existentes.
- `ljsmartclinic-migracao-20261031-184952.tar.gz` existente (migracao).

## 8. Comandos seguros para backup (DEFINIDOS — NÃO EXECUTADOS)
```bash
# Código (referência — aguarda autorização)
cp -r /var/www/ljsmartclinic /var/backups/ljsmartclinic/pre-publicacao-$(date +%Y%m%d-%H%M%S)

# Banco central (referência — adaptar credenciais; NÃO executado agora)
mysqldump -u root -p sco > /var/backups/ljsmartclinic/central_pre_$(date +%Y%m%d).sql

# Bancos tenant (referência — clinic_000005 e clinic_000001 protegidos; NÃO executado)
# mysqldump -u root -p clinic_000005 > ...
# mysqldump -u root -p clinic_000001 > ...

# Configurações (referência; .env protegido por permissões do sistema)
cp /var/www/ljsmartclinic/.env /var/backups/ljsmartclinic/env_$(date +%Y%m%d)
```

## 9. Verificações de integridade / critérios para considerar válido
- `STATUS` existe em `/var/backups/ljsmartclinic/`.
- Nenhum arquivo `.parcial` presente.
- Arquivo `.tar.gz` de código criado com sucesso.
- `mysqldump` completo (não vazio; `wc -l` > 0; `head` e `tail` consistentes).
- `.env` restaurável (não exposto; cópia protegida por permissões).
- Hash/checksum opcional (md5/sha256) pode ser gerado para verificação.

## 10. Como restaurar sem executar (explicação — não executado)
1. Copiar código do backup para `/var/www/ljsmartclinic/`.
2. Restaurar `.env` do backup.
3. Restaurar banco central (`mysql` com arquivo `.sql`).
4. Restaurar bancos tenant (`mysql` para cada clínica, incluindo `clinic_000005` e `clinic_000001`).
5. Verificar `STATUS`; se ausente, não considerar backup confiável.
6. Executar validações de isolamento (`TenantIsolationTest`) após restauração.

## Confirmação de restrições
- Nenhum arquivo criado/copiado/movido/apagado na VPS.
- Nenhum `mysqldump` executado.
- Nenhuma migration executada.
- Nenhum banco alterado.
- Nenhum serviço reiniciado.
- Nenhuma senha exposta (apenas `APP_NAME` confirmada localmente).
- Nenhum `.env` modificado.
- Nenhuma alteração em `Evolution API`, `n8n`, `WhatsApp`, DNS, SSL, credenciais.

**Aguardar autorização específica para executar qualquer backup, antes de avançar para Fase 2 (execução real).**
