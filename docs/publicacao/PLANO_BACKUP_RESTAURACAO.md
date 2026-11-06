# PLANO DE BACKUP E RESTAURAÇÃO — LJ SmartClinic

**Versão de referência:** commit `d73d2c1ae37368c32f0b063d226aecd03acfec8e`  
**Ambiente:** preparação exclusivamente local (não executar backup na VPS neste documento; apenas procedimento)  
**Destino:** VPS `minha-vps` (179.236.238.61), projeto `/var/www/ljsmartclinic`

---

## 1. Objetivo

Garantir que, antes de qualquer alteração na VPS, existam backups verificados do banco central, de todos os bancos de clínicas e dos arquivos da aplicação, permitindo restauração em caso de falha.

## 2. Backups obrigatórios (antes de qualquer alteração)

### 2.1 Banco central (SaaS)

- Contém: clínicas, usuários, planos, assinaturas, conexões de tenants.
- Não contém dados clínicos.
- Comando de referência (ajustar credenciais ao ambiente da VPS):
  ```bash
  mysqldump -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" > backup_central_$(date +%Y%m%d_%H%M%S).sql
  ```

### 2.2 Bancos de todas as clínicas (tenants)

Cada clínica tem seu próprio banco (`clinic_XXXXXX`). Incluem:
- `clinic_000001` — Malheiro (intocado, deve ser preservado)
- `clinic_000005` — Daiane (alvo da implementação)
- Todos os demais tenants ativos

Comando de referência (iterar sobre lista de tenants):
```bash
mysqldump -u "$TENANT_DB_USERNAME" -p"$TENANT_DB_PASSWORD" "clinic_000005" > backup_clinic_000005_$(date +%Y%m%d_%H%M%S).sql
```

### 2.3 Arquivos da aplicação

- Diretório do projeto: `/var/www/ljsmartclinic`
- `storage/` (logs, cache, sessões, uploads)
- `bootstrap/cache/` (cache de configuração, rotas, autoload)
- `vendor/` e `node_modules/` (opcional, se houver procedimento de reinstalação)

Comando de referência:
```bash
tar -czf backup_app_$(date +%Y%m%d_%H%M%S).tar.gz -C /var/www ljsmartclinic
```

### 2.4 Arquivos de ambiente (`.env`)

- Fazer backup do `.env` atual da VPS (contém credenciais reais).
- **Não copiar o `.env` local para a VPS.**
- **Não incluir o `.env` no pacote de release.**

## 3. Verificação de integridade

Após criar cada backup:

| Verificação | Como fazer |
|---|---|
| Arquivo não vazio | `ls -lh backup_*.sql backup_*.tar.gz` |
| Dump SQL válido | `head -20 backup_*.sql` (deve conter `CREATE TABLE` ou `INSERT`) |
| Tar.gz válido | `tar -tzf backup_*.tar.gz > /dev/null` |
| Checksum | `sha256sum backup_*.sql backup_*.tar.gz` |

Registrar: data, tamanho, localização e hash SHA256 de cada backup.

## 4. Localização e proteção dos backups

- Armazenar **fora** do diretório público da aplicação (ex.: `/var/backups/ljsmartclinic/` ou diretório fora de `/var/www/ljsmartclinic/public`).
- Garantir que backups **não sejam acessíveis pela web**.
- Permissões restritas: `chmod 600` para arquivos de backup com dados sensíveis.
- Proprietário: usuário do sistema que executa o backup, não `www-data` (se possível).

## 5. Teste de restauração em ambiente isolado

Antes de confiar no backup para produção:

1. Copiar o backup para um ambiente de staging/desenvolvimento isolado.
2. Restaurar o banco central: `mysql -u user -p db < backup_central.sql`.
3. Restaurar cada tenant: `mysql -u user -p clinic_XXXXXX < backup_clinic_XXXXXX.sql`.
4. Verificar integridade: contar tabelas, verificar dados de amostra.
5. Confirmar que a aplicação inicializa com o backup restaurado.

## 6. Retenção

- Manter pelo menos 3 backups históricos antes de qualquer alteração.
- Retenção mínima de 30 dias para backups de produção.
- Rotacionar backups antigos automaticamente.

## 7. Procedimento de restauração (somente em caso de falha)

1. Colocar a aplicação em manutenção (`php artisan down`).
2. Restaurar o banco central do backup.
3. Restaurar os bancos de tenants afetados do backup.
4. Restaurar os arquivos da aplicação do backup (se houver corrupção).
5. Limpar cache: `php artisan config:clear`, `php artisan route:clear`, `php artisan view:clear`, `composer dump-autoload`.
6. Tirar da manutenção: `php artisan up`.
7. Validar login, agenda, financeiro, atendimentos e isolamento de tenants.
8. Registrar o incidente e a restauração.

## 8. O que NÃO fazer

- Não copiar dados pessoais reais para ambientes de demonstração.
- Não armazenar backups em local acessível pela web.
- Não incluir backups no pacote de release.
- Não executar restauração sem validar o backup primeiro.
