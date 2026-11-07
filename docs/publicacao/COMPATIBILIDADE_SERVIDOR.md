# COMPATIBILIDADE DO SERVIDOR — LJ SmartClinic

**Versão de referência:** commit `d73d2c1ae37368c32f0b063d226aecd03acfec8e`  
**Ambiente validado localmente:** PHP 8.3.35 (CLI), Laravel Framework 13.33.0, SQLite (demo)  
**Destino:** VPS `minha-vps` (179.236.238.61), projeto `/var/www/ljsmartclinic`

---

## 1. Versão do PHP

| Item | Valor local (válido) | Requisito na VPS |
|---|---|---|
| PHP | 8.3.35 (cli, NTS, x64) | PHP 8.3+ (conforme `composer.json`: `"php": "^8.3"`) |
| Laravel Framework | 13.33.0 | Laravel 13.x (conforme `composer.json`: `"laravel/framework": "^13.17"`) |

## 2. Extensões PHP necessárias

As extensões abaixo estão ativas localmente e devem estar disponíveis na VPS:

```
bcmath, calendar, Core, ctype, curl, date, dom, exif, fileinfo,
filter, gd, hash, iconv, intl, json, libxml, mbstring, mysqlnd,
openssl, pcre, PDO, pdo_sqlite, Phar, random, readline, Reflection,
session, SimpleXML, sodium, SPL, sqlite3, standard, tokenizer, xml,
xmlreader, xmlwriter, zip, zlib
```

Extensões críticas para operação em produção (MySQL, Redis, filas, cache):
- `pdo_mysql` / `mysqli` — banco central e tenants (MySQL)
- `openssl` — criptografia, HTTPS
- `mbstring` — multibyte (PT-BR)
- `bcmath` — cálculos financeiros
- `gd` — processamento de imagens (se aplicável)
- `zip` — compactação (se aplicável)
- `pdo_sqlite` — apenas para ambiente demo/teste local

## 3. Dependências Composer

Conforme `composer.json`:

**Produção (`require`):**
- `php`: ^8.3
- `laravel/framework`: ^13.17
- `laravel/tinker`: ^3.0

**Desenvolvimento (`require-dev` — não instalar em produção):**
- `fakerphp/faker`: ^1.23
- `laravel/boost`: ^2.9
- `laravel/breeze`: ^2.4
- `laravel/pail`: ^1.2.5
- `laravel/pao`: ^1.0.6
- `laravel/pint`: ^1.27
- `mockery/mockery`: ^1.6
- `nunomaduro/collision`: ^8.6
- `phpunit/phpunit`: ^12.5.12

**Procedimento na VPS:**
```bash
composer install --no-dev --optimize-autoloader
```

## 4. Dependências Node

Conforme `package.json`:

- `vite`: ^8.0.0
- `laravel-vite-plugin`: ^3.1
- `sass-embedded`: ^1.83.0
- `bootstrap`: ^5.3.3
- `bootstrap-icons`: ^1.11.3
- `@popperjs/core`: ^2.11.8
- `@laravel/multiplex`: ^0.1.0 (opcional)

**Procedimento na VPS (apenas se houver alteração de assets):**
```bash
npm install --ignore-scripts
npm run build
```

## 5. Configurações necessárias no `.env`

**Não copiar o `.env` local para a VPS.** O `.env` da VPS deve ser mantido e ajustado apenas pelo administrador. Variáveis relevantes para esta implementação:

| Variável | Descrição | Observação |
|---|---|---|
| `APP_ENV` | Ambiente | `production` na VPS |
| `APP_DEBUG` | Depuração | `false` na VPS |
| `APP_URL` | URL pública | Sem alterar DNS/SSL |
| `DB_CONNECTION` | Banco central | `mysql` |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Credenciais centrais | Manter as atuais da VPS |
| `TENANT_DB_DRIVER` | Driver dos tenants | `mysql` (produção) |
| `TENANT_DB_HOST`, `TENANT_DB_PORT`, `TENANT_DB_USERNAME`, `TENANT_DB_PASSWORD` | Credenciais dos tenants | Manter as atuais da VPS |
| `TENANT_DB_PREFIX` | Prefixo dos bancos | `clinic_` |
| `TENANT_DB_PADDING` | Preenchimento do ID | `6` |
| `QUEUE_CONNECTION` | Filas | `redis` |
| `CACHE_STORE` | Cache | `redis` |
| `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` | Redis | Manter as atuais da VPS |
| `SESSION_DRIVER` | Sessões | `database` |
| `FILESYSTEM_DISK` | Armazenamento | `local` |

**Não incluir segredos ou valores reais do `.env` no pacote de release.**

## 6. Permissões necessárias

| Diretório | Propósito | Permissão sugerida |
|---|---|---|
| `storage/` | Logs, cache, sessões, uploads | Gravável pelo servidor web |
| `storage/framework/` | Cache de views, sessões, cache | Gravável |
| `bootstrap/cache/` | Cache de configuração, rotas, autoload | Gravável |
| `public/` | Assets públicos | Leitura pelo servidor web |
| `storage/app/demo/` | Demo local (não usar em produção) | Não aplicar na VPS |

## 7. Filas, cache e agendador

- `QUEUE_CONNECTION=redis` — filas processadas via Redis. Garantir que o worker esteja rodando (`php artisan queue:work` ou equivalente via supervisor).
- `CACHE_STORE=redis` — cache em Redis.
- Agendador Laravel (`php artisan schedule:run`) configurado no cron do servidor.

## 8. Armazenamento

- `FILESYSTEM_DISK=local` — arquivos armazenados em `storage/app/`.
- `TENANT_UPLOADS_PATH=uploads` — uploads dos tenants.

## 9. Compatibilidade do demo local

O ambiente de demonstração usa SQLite (`storage/app/demo/central.sqlite` e `storage/app/demo/tenants/clinic_000005.sqlite`). Isso é **apenas para desenvolvimento e teste local**. Não usar SQLite em produção.

## 10. Verificações recomendadas na VPS antes da publicação

```bash
php -v                      # Deve ser 8.3+
php -m | grep -E 'pdo_mysql|openssl|mbstring|bcmath'
composer --version          # Composer disponível
php artisan --version       # Laravel 13.x (após composer install)
```
