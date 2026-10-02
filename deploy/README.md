# Deploy — LJ SmartClinic

Como colocar o sistema no ar numa VPS. Os scripts aqui são para Ubuntu 22.04/24.04
com root. Nenhum deles roda sozinho: eles instalam, e você preenche os segredos.

## Ordem

| Passo | O quê | Quando |
|---|---|---|
| 0 | Comprar VPS e apontar o domínio | antes de tudo |
| 1 | `01-mysql.sql` | uma vez, cria banco e usuário |
| 2 | `02-provisionar.sh` | uma vez, instala o sistema |
| 3 | `.env.production.example` → `.env` | uma vez, preencha os segredos |
| 4 | `migrate`, `db:seed`, `npm run build` | uma vez |
| 5 | `certbot --nginx` | uma vez, depois de funcionar em HTTP |
| 6 | `03-backup.sh` | uma vez, e testar a restauração |
| 7 | `git pull` + `migrate` | a cada deploy |

## O que não tem como automatizar

APP_KEY, senhas do banco, do super admin e do SMTP, e o domínio. Estão marcados
com `<<<` no template — o script não pode adivinhar senha.

## Três decisões que moldam tudo

**Um banco por clínica.** Cada clínica tem seu schema MySQL (`clinic_000001`).
O painel cria o banco quando a clínica é cadastrada, então o usuário do app
precisa de `CREATE DATABASE` global — não dá para dar esse privilégio só para um
padrão no MySQL 8. Está em `01-mysql.sql` e é o preço desse desenho.

**Backup é N bancos.** Um dump só do banco central não recupera nenhum dado
clínico. `03-backup.sh` varre `tenant_connections` e faz um dump por clínica.

**O cookie seguro e o SSL andam juntos.** `SESSION_SECURE_COOKIE=true` antes do
certbot impede o login. O usuário não entra e não há mensagem de erro — o cookie
simplesmente não volta. Instale o certbot primeiro.

## Depois de cada mudança

```bash
cd /var/www/ljsmartclinic
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

`config:cache` e `route:cache` são o que fazem a diferença de performance. Se
mudar `.env` e esquecer de limpar o cache, a mudança não vale — `config:clear`
primeiro.

## Para reverter

Não existe rollback automático. O caminho é restaurar um backup dos bancos com
`03-backup.sh`, e o código antigo com `git checkout` na tag anterior. Se a
migration já rodou, o código antigo pode não entender o schema novo. Faça backup
antes de `migrate --force` em produção.

## Se algo der errado

`php artisan about` mostra ambiente, driver de fila/cache e URL — é a primeira
coisa a rodar quando algo quebra.

Os logs do PHP-FPM, que é onde moram os erros de web, ficam em
`/var/log/php8.3-fpm.log`. Os do Laravel, em `storage/logs/`.

Erro de banco quase sempre é senha ou grant faltando. `SHOW GRANTS FOR 'saas'@'127.0.0.1';`
confere.