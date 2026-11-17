#!/usr/bin/env bash
#
# Aplica o modulo Regioes a Laser em producao.
# Deve ser executado NA VPS, como root, dentro da pasta do pacote:
#
#   cd /root/laser-pacote && bash deploy-laser.sh
#
# Variavel opcional: APP_DIR (padrao /var/www/ljsmartclinic)
#
set -Eeuo pipefail

APP_DIR="${APP_DIR:-/var/www/ljsmartclinic}"
PKG_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="/var/backups/ljsmartclinic"
SNAP="$BACKUP_DIR/pre-laser-$STAMP.tar.gz"
BKP="$BACKUP_DIR/pre-laser-$STAMP"

NOVOS=(
  app/Http/Controllers/AplicacaoLaserController.php
  app/Http/Controllers/PacoteLaserController.php
  app/Http/Controllers/RegiaoLaserController.php
  app/Models/AplicacaoLaser.php
  app/Models/PacoteLaser.php
  app/Models/RegiaoLaser.php
  app/Services/Laser/AplicacaoLaserService.php
  database/migrations/tenant/2026_11_16_000002_create_laser_tables.php
  resources/views/laser/_tabs.blade.php
  resources/views/laser/historico.blade.php
  resources/views/laser/pacotes.blade.php
  resources/views/laser/regioes.blade.php
  tests/Feature/LaserTest.php
)

ALTERADOS=(
  app/Http/Controllers/PacienteController.php
  app/Models/Paciente.php
  app/Services/Tenant/InventarioTabelas.php
  app/Support/Modulos/CatalogoModulos.php
  resources/views/pacientes/prontuario.blade.php
  routes/web.php
)

erro() { echo "ERRO: $*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || erro "rode como root"
[[ -d "$APP_DIR/.git" ]] || erro "$APP_DIR nao e um repositorio git"
cd "$APP_DIR"

echo "==> 1/8 Conferindo o pacote"
for f in "${NOVOS[@]}"; do
  [[ -f "$PKG_DIR/files/$f" ]] || erro "arquivo ausente no pacote: $f"
done
[[ -f "$PKG_DIR/alterados.patch" ]] || erro "alterados.patch ausente"

echo "==> 2/8 Conferindo a base do codigo"
git rev-parse --verify 8ec6e7fe >/dev/null 2>&1 || erro "commit base 8ec6e7fe nao encontrado"
git merge-base --is-ancestor 8ec6e7fe HEAD || erro "producao nao contem o commit base 8ec6e7fe"

echo "==> 3/8 Testando o patch dos 6 arquivos alterados"
if ! git apply --check "$PKG_DIR/alterados.patch"; then
  erro "o patch nao aplica limpo. Nada foi alterado. Arquivos com conflito:
$(git apply --check "$PKG_DIR/alterados.patch" 2>&1 || true)"
fi

echo "==> 4/8 Snapshot do estado atual"
mkdir -p "$BACKUP_DIR"
tar czf "$SNAP" \
  --exclude='./vendor' --exclude='./node_modules' --exclude='./storage/logs' \
  app routes resources database config public 2>/dev/null || true
echo "    snapshot: $SNAP"

echo "==> 5/8 Backup dos arquivos que serao alterados"
for f in "${ALTERADOS[@]}" "${NOVOS[@]}"; do
  if [[ -f "$APP_DIR/$f" ]]; then
    mkdir -p "$BKP/$(dirname "$f")"
    cp -a "$APP_DIR/$f" "$BKP/$f"
  fi
done
echo "    backup:   $BKP"

echo "==> 6/8 Aplicando arquivos"
git apply --whitespace=nowarn "$PKG_DIR/alterados.patch"
for f in "${NOVOS[@]}"; do
  mkdir -p "$APP_DIR/$(dirname "$f")"
  cp -a "$PKG_DIR/files/$f" "$APP_DIR/$f"
done

echo "==> 7/8 Migrations"
php artisan migrate --force
php artisan tenants:migrate --force

echo "==> 8/8 Caches e verificacao"
php artisan optimize:clear
php artisan optimize
php artisan route:list --path=laser

echo
echo "OK: modulo laser aplicado em $APP_DIR"
echo "Snapshot para rollback: $SNAP"
echo "Arquivos antigos:      $BKP"
