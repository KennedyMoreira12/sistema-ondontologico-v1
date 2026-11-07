# Deploy — LJ SmartClinic

Como colocar o sistema no ar numa VPS. Os scripts aqui são para Ubuntu 22.04 ou
mais novo, com root. Nenhum deles roda sozinho: eles instalam, e você preenche os
segredos.

A versão do PHP é a que a distro entrega (8.5 no Ubuntu 26.04), detectada
automaticamente. Não há PPA involved: em Ubuntu novo a PPA do Ondrej atrasa e o
`apt` falha. O `composer.json` pede `^8.3`, que 8.5 satisfaz.

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
| 8 | `externo/backup-externo.ps1` | uma vez, para o backup não morar só na VPS |

## O que não tem como automatizar

APP_KEY, senhas do banco, do super admin e do SMTP, e o domínio.

No `.env.production.example` os campos que você precisa preencher estão **vazios
de propósito**. Não coloque texto de exemplo em campo de senha: um placeholder
tipo `TROQUE-ESTA-SENHA` que sobreviva ao deploy vira senha real em produção sem
ninguém perceber. `EnvProductionExampleTest` falha se algum campo voltar a ter
conteúdo de exemplo.

## Três decisões que moldam tudo

**Um banco por clínica.** Cada clínica tem seu schema MySQL (`clinic_000001`).
O painel cria o banco quando a clínica é cadastrada, então o usuário do app
precisa de `CREATE DATABASE` global — não dá para dar esse privilégio só para um
padrão no MySQL 8. Está em `01-mysql.sql` e é o preço desse desenho.

O mesmo usuário não pode derrubar o banco central por accident (`clinic_%` e
`saas_central` são escopos separados, e não há nada em `mysql.*`). Ele **pode**,
porém, executar `DROP DATABASE saas_central` — o privilégio `DROP` no nível do
banco é o mesmo para tabela e para banco no MySQL, e o app precisa dele para
rollback de migration. `01-mysql.sql` explica isso em detalhe e lista as
mitigações; a que importa é backup fora da máquina.

**Backup é N bancos.** Um dump só do banco central não recupera nenhum dado
clínico. `03-backup.sh` varre `tenant_connections` e faz um dump por clínica.

**O cookie seguro e o SSL andam juntos.** `SESSION_SECURE_COOKIE=true` antes do
certbot impede o login. O usuário não entra e não há mensagem de erro — o cookie
simplesmente não volta. Instale o certbot primeiro.

## Backup fora da VPS

O `03-backup.sh` protege contra banco corrompido, migration errada e
`DROP DATABASE` acidental. Ele **não** protege contra a perda da VPS: o dump
fica na mesma máquina que ele protege, e o próprio script avisa isso no fim da
execução.

`externo/backup-externo.ps1` fecha essa janela. Ele roda na sua máquina, puxa a
rotação mais recente para o destino removível (o pendrive), confere o SHA-256 de
cada arquivo contra o hash calculado no servidor, e só depois poda as cópias
antigas. O passo a passo, o agendamento no Windows e a restauração estão em
[`externo/README.md`](externo/README.md).

Vale rodar o teste de restauração uma vez a partir dessa cópia: ela percorre
um caminho diferente (ssh, scp, pendrive) do que o `03-backup.sh` percorre, e um
backup nunca restaurado é um backup que só existe na teoria.

## O `STATUS` do backup

O `03-backup.sh` grava o resultado da última execução em
`/var/backups/ljsmartclinic/STATUS`:

```
resultado=ok
rotulo=20261021-031701
em=2026-10-21T03:17:05-03:00
detalhe=backup completo
```

O log do cron é sobrescrito a cada noite, então o `STATUS` é o que sobrevive.
Para conferir se o backup de ontem deu certo:

```bash
ssh root@179.236.238.61 "cat /var/backups/ljsmartclinic/STATUS"
```

`resultado=falha` significa que a última noite **não** produziu backup — e
esperar pela próxima cópia seria esperar por uma cópia de hoje à noite.

## Depois de cada mudança

```bash
cd /var/www/ljsmartclinic
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
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
`/var/log/php8.5-fpm.log` (o número acompanha a versão; confira com
`php -v`). Os do Laravel, em `storage/logs/`.

Erro de banco quase sempre é senha ou grant faltando. `SHOW GRANTS FOR 'saas'@'127.0.0.1';`
confere.

## Permissões: a parte que não se vê

O erro mais caro de um deploy Laravel não é o código, é o `umask`.

```bash
# código: root lê, www-data só lê
chown -R root:root /var/www/ljsmartclinic
find /var/www/ljsmartclinic -type d -exec chmod 755 {} +
find /var/www/ljsmartclinic -type f -exec chmod 644 {} +

# o que o PHP-FPM realmente escreve
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# .env: só root e o php-fpm leem
chown root:www-data .env
chmod 640 .env
```

Por que o código é `root` e não `www-data`: se o PHP-FPM é dono do código, uma
injeção de SQL ou um RCE grava um arquivo que o próximo `git pull` não remove,
porque o dono não é o git. Com o código em `root`, o `git reset --hard` limpa
tudo que não versionado.

Consequência prática: **`git pull` tem que rodar como root.** Se rodar como
`www-data`, o git aborta com `detected dubious ownership`. Na dúvida:

```bash
cd /var/www/ljsmartclinic && git reset --hard origin/main
```

**Cacheado como root é o 500 clássico.** Se `php artisan config:cache` roda como
root, os arquivos saem com dono root e o `www-data` não consegue lê-los. O
sintoma é enganoso de propósito:

- `/` responde **302** (só redireciona, não precisa de view)
- `/painel` responde **302** (mesma razão)
- `/login` responde **500** (é a única rota que precisa ler a view do disco)

Um site com "login quebrado" mas "demais rotas normais" quase sempre é isso.
Confira sempre `/login`, nunca `/`. E compilar como root faz a pasta ficar com
dono www-data e os arquivos dentro dela com dono root — `chown` na pasta não
corrige arquivo. A correção é na raiz:

```bash
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
```

Se precisar consertar um cache já estragado:

```bash
chgrp -R www-data bootstrap/cache storage/framework/views
chmod -R g+r bootstrap/cache storage/framework/views
```

**Cuidado com `umask 077`.** Ele é correto para criar `/root/.senha_admin` e
`/root/.my.cnf`, e errado para qualquer outra coisa. Se um script de setup roda
com ele e depois executa `php artisan config:cache` no mesmo shell, os caches
saem `600 root:root` e o site responde `500 Permission denied` — o erro aponta
para o PHP-FPM e a causa está no shell, longe do log. Depois de criar segredo
com `umask 077`, volte para `umask 022` antes de cachear:

```bash
umask 022
php artisan config:cache
chown www-data:www-data bootstrap/cache -R
chmod 664 bootstrap/cache/*.php
```

Diagnóstico rápido quando o site dá 500 e o nginx diz "permission denied":

```bash
curl -I -H "Host: SEUDOMINIO" http://127.0.0.1/login
find bootstrap/cache storage/framework/views -type f ! -user www-data
php artisan optimize:clear
sudo -u www-data php artisan config:cache && sudo -u www-data php artisan view:cache
```

O `find` acima é o que resolve o caso na maioria das vezes. Se não achar nada,
o problema é outro — e aí sim vale ligar `APP_DEBUG=true` temporariamente para
ver a exceção. Não esqueça de voltar para `false`.