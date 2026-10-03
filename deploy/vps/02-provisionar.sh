#!/usr/bin/env bash
#
# Provisionamento da VPS — LJ SmartClinic
#
# Deve rodar como root, em uma VPS recém-comprada com Ubuntu 22.04/24.04.
# Faz o que é repetível e segura: instalar, configurar, criar o banco e o
# primeiro super admin. Não é idempotente no sentido de poder rodar três vezes
# sem estragar nada — mas RERROGAR depois de um deploy bem-sucedido NÃO deve
# QUEBRAR nada, e os passos de serviço detectam isso.
#
#     ssh root@SEU_IP
#     git clone <repo> /var/www/ljsmartclinic && cd /var/www/ljsmartclinic
#     bash deploy/vps/02-provisionar.sh
#
# Depois de rodar, ainda falta: editar o .env, `php artisan key:generate` e
# colocar o SSL. Os três passos ficam sinalizados no final.

set -euo pipefail

# --- Configuração -----------------------------------------------------------
# Ajuste o domínio ANTES de rodar, ou o nginx vai subir com o host errado.
#
# Para domínio IDN (acentuado), use a forma punycode: o nginx e o certbot
# operam sobre ASCII, e `ljaltomação.com.br` no server_name simplesmente não
# casa. Convertida: python3 -c "print('ljaltomação.com.br'.encode('idna').decode())"
DOMINIO="${DOMINIO:-seudominio.com.br}"
USUARIO_WWW="${USUARIO_WWW:-www}"
DIRETORIO_APP="${DIRETORIO_APP:-/var/www/ljsmartclinic}"
BANCO_CENTRAL="${BANCO_CENTRAL:-saas_central}"
USUARIO_BANCO="${USUARIO_BANCO:-saas}"

# Versão do PHP = a que a distro já entrega. Forçar 8.3 via PPA do Ondrej
# funciona no 22.04/24.04, mas no 26.04 a PPA atrasa e o apt falha. A
# aplicação pede `^8.3`, então 8.5 do Ubuntu nativo serve e dispensa PPA.
PHP_VERSAO="${PHP_VERSAO:-$(apt-cache policy php | awk '/Candidate:/{print $2}' | cut -d. -f1,2)}"
PHP_VERSAO="${PHP_VERSAO:-8.3}"

log()  { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
aviso(){ printf '\033[1;33maviso: %s\033[0m\n' "$*"; }
erro() { printf '\033[1;31merro: %s\033[0m\n' "$*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || erro "Rode como root: sudo bash deploy/vps/02-provisionar.sh"

log "LJ SmartClinic — provisionamento"
log "Domínio: $DOMINIO | Diretório: $DIRETORIO_APP"

# --- 1. Pacotes base --------------------------------------------------------
#
# unattended-upgrades sem isto é um servidor que acumula CVE por meses: numa
# VPS o sistema não reinicia sozinho, então a atualização de segurança é
# manual e nunca acontece.
log "1/8 Atualizando o sistema e instalando pacotes base"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq \
    nginx curl git unzip \
    software-properties-common \
    unattended-upgrades
systemctl enable --now unattended-upgrades

# --- 2. PHP -----------------------------------------------------------------
#
# As extensões não são opcionais: pdo_mysql e mbstring sem as quais o Laravel
# nem conecta; gd porque o processing de imagem; bcmath e intl para o cálculo
# financeiro e para as traduções.
log "2/8 Instalando PHP $PHP_VERSAO e extensões"
# Pacotes nativos da distro, sem PPA: é o que reduz a chance de o provisiona-
# mento quebrar numa versão nova do Ubuntu.
apt-get install -y -qq \
    "php${PHP_VERSAO}-cli" "php${PHP_VERSAO}-fpm" "php${PHP_VERSAO}-mysql" \
    "php${PHP_VERSAO}-mbstring" "php${PHP_VERSAO}-xml" "php${PHP_VERSAO}-curl" \
    "php${PHP_VERSAO}-zip" "php${PHP_VERSAO}-gd" "php${PHP_VERSAO}-bcmath" \
    "php${PHP_VERSAO}-intl" "php${PHP_VERSAO}-redis"

[[ -S "/run/php/php${PHP_VERSAO}-fpm.sock" || -d "/run/php/php${PHP_VERSAO}-fpm" ]] \
    || erro "PHP-FPM $PHP_VERSAO não subiu. Confira: systemctl status php${PHP_VERSAO}-fpm"

# --- 3. Composer e Node -----------------------------------------------------
log "3/8 Instalando Composer e Node"
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# O Node 18 do Ubuntu já saiu de suporte; o build do Vite 8 exige 20+.
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt-get install -y -qq nodejs

# --- 4. MariaDB -------------------------------------------------------------
#
# MariaDB e não MySQL da Oracle porque é o que a distro mantém com security
# support e o que o Laravel suporta da mesma forma. Trocar aqui exigiria
# revisar collation e o GRANT global de CREATE (item 01-mysql.sql).
log "4/8 Instalando MariaDB"
apt-get install -y -qq mariadb-server
systemctl enable --now mariadb

log "   Rodando o SQL de criação do usuário da aplicação"
aviso "Se o 01-mysql.sql ainda tiver a senha placeholder, edite-o e rode:"
aviso "   mysql -u root -p < $DIRETORIO_APP/deploy/vps/01-mysql.sql"

# --- 5. Usuário do sistema --------------------------------------------------
#
# O PHP roda como `www-data`, nunca como root. Um app com privilégio de root
# transforma qualquer RCE em acesso total à máquina.
#
# O código fica root:root e www-data só LÊ. Não é preciosismo: se um upload
# malicioso ou uma RCE conseguirem gravar em app/, o backdoor sobrevive a um
# `git checkout` e a um deploy novo. Root:root faz o backdoor morrer no
# próximo pull.
log "5/8 Configurando permissões de $DIRETORIO_APP"
mkdir -p "$DIRETORIO_APP"
chown -R root:root "$DIRETORIO_APP"
find "$DIRETORIO_APP" -type f -exec chmod 644 {} \;
find "$DIRETORIO_APP" -type d -exec chmod 755 {} \;

# Únicos caminhos que o Laravel escreve em runtime.
chown -R www-data:www-data "$DIRETORIO_APP/storage" "$DIRETORIO_APP/bootstrap/cache"
chmod -R 775 "$DIRETORIO_APP/storage" "$DIRETORIO_APP/bootstrap/cache"

# --- 6. Nginx ---------------------------------------------------------------
log "6/8 Configurando Nginx para $DOMINIO"

# envsubst é o que troca $DOMINIO, $DIRETORIO_APP e $PHP_VERSAO. Sem ele o
# nginx sobe com o literal `$DOMINIO` no server_name, que nunca casa com
# nenhum host, e o site simplesmente não responde.
envsubst '${DOMINIO} ${DIRETORIO_APP} ${PHP_VERSAO}' \
    < "$DIRETORIO_APP/deploy/vps/nginx.conf" \
    > /etc/nginx/sites-available/ljsmartclinic

if grep -q '\${' /etc/nginx/sites-available/ljsmartclinic; then
    erro "Sobrou placeholder sem substituir em nginx.conf — o nginx não sobe assim."
fi

ln -sf /etc/nginx/sites-available/ljsmartclinic /etc/nginx/sites-enabled/ljsmartclinic
rm -f /etc/nginx/sites-enabled/default

nginx -t || erro "Configuração do Nginx inválida — corrija nginx.conf"
systemctl reload nginx

# --- 7. Cron ----------------------------------------------------------------
#
# schedule:run é o que dispara tarefas agendadas. Sem o cron, nada agendado
# roda — e o silêncio é o pior sintoma, porque nada dá erro.
#
# O --stop-when-empty faz o worker de fila sair assim que não houver job. Com
# --stop-when-empty e --max-time ele custa zero quando o app não despacha nada
# (que é o caso hoje: não existe nenhuma classe ShouldQueue). Se um dia o
# app passar a enfileirar trabalho pesado, troque por um serviço systemd
# com Restart=always em vez de continuar pollando por cron.
log "7/8 Configurando cron do Laravel"
cat > /etc/cron.d/ljsmartclinic <<EOF
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
* * * * * www-data cd $DIRETORIO_APP && php artisan schedule:run >> /dev/null 2>&1
* * * * * www-data cd $DIRETORIO_APP && php artisan queue:work --stop-when-empty --max-time=60 >> /dev/null 2>&1
EOF
chmod 644 /etc/cron.d/ljsmartclinic

# --- 8. Firewall ------------------------------------------------------------
log "8/8 Fechando portas desnecessárias"
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

# --- Fim --------------------------------------------------------------------
cat <<EOF

$(aviso "") Provisionamento concluído. FALTAM ESTES PASSOS, TODOS OBRIGATÓRIOS:

  1. Criar o banco e o usuário (troque a senha no arquivo antes):

         mysql -u root -p < $DIRETORIO_APP/deploy/vps/01-mysql.sql

  2. Configurar o .env:

         cd $DIRETORIO_APP
         cp .env.production.example .env
         nano .env

     Preencha APP_URL, as duas senhas do banco (DB_PASSWORD e
     TENANT_DB_PASSWORD — precisam ser IGUAIS), ADMIN_EMAIL, ADMIN_PASSWORD
     e o SMTP. Sete SESSION_SECURE_COOKIE=true só depois do SSL.

  3. Gerar a chave da aplicação:

         php artisan key:generate

  4. Instalar, migrar e semear:

         composer install --no-dev --optimize-autoloader
         npm ci && npm run build
         php artisan migrate --force
         php artisan db:seed --force

     O db:seed cria o super admin com ADMIN_PASSWORD do .env. Troque a senha
     logo depois — é a mesma credencial do primeiro acesso.

5. SSL (obrigatório antes de qualquer cliente real):

          apt-get install -y certbot python3-certbot-nginx
          certbot --nginx -d $DOMINIO -d $USUARIO_WWW.$DOMINIO

      Sem isso o SESSION_SECURE_COOKIE=true impede o login e o usuário não
      entra. Com o site em HTTP e o cookie não-seguro acontece o contrário: a
      sessão trafega em claro e qualquer um na rede copia a de uma clínica.

      O certificado exige registro DNS A apontando para esta VPS. Sem o A, o
      certbot falha com "Timeout during connect" e o site fica preso em HTTP.

   6. Cache de produção — compile como www-data, NÃO como root:

          cd $DIRETORIO_APP
          umask 022
          sudo -u www-data php artisan config:cache
          sudo -u www-data php artisan route:cache
          sudo -u www-data php artisan view:cache
          sudo -u www-data php artisan event:cache

      Confira logo em seguida: curl -I -H "Host: $DOMINIO" http://127.0.0.1/login
      tem que devolver 200. Se devolver 500, é isto:

      - Artisan roda como root e escreve os arquivos com dono root. O www-data
        não consegue lê-los e TODO o site devolve 500. Compilar como www-data
        resolve na raiz, em vez de chmod depois.

      - `chown -R www-data` na PASTA não basta para os arquivos dentro dela.
        O que decide é o dono do ARQUIVO. Foi assim que o bug passou: a pasta
        estava www-data, os 105 arquivos compilados dentro dela eram root:root.

      - um `umask 077` deixado no shell faz o cache sair 600 em vez de 644,
        com o mesmo sintoma de 500.

      Se ainda assim precisar consertar um cache compilado como root:

          chgrp -R www-data bootstrap/cache storage/framework/views
          chmod -R g+r bootstrap/cache storage/framework/views

      Note que o sintoma aparece SÓ na página de login, e não em / ou /painel:
      a view do formulário é a única que precisa ser lida do disco. Rotas que
      só redirecionam continuam respondendo 302 com o cache quebrado, o que
      faz o problema parecer intermitente. Confira sempre /login, não /.

   7. Backup — instale e TESTE a restauração antes do primeiro cliente:

          bash $DIRETORIO_APP/deploy/vps/03-backup.sh instalar

EOF
