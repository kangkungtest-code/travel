#!/usr/bin/env bash
# Langkah deploy Laravel di server. Dipanggil oleh deploy/tarik.sh dari folder aplikasi
# yang sudah di-checkout ke commit terbaru:
#   bash deploy/jalankan.sh <APP_ENV> <APP_URL> <DB_DATABASE> <APP_DEBUG>
#
# .env di server TIDAK ditimpa (kunci Xendit, SMTP, password DB, dll. tetap). Yang diatur
# di sini hanya nilai yang ditentukan oleh target deploy.
set -euo pipefail

APP_ENV_VAL="$1"
APP_URL_VAL="$2"
DB_NAME="$3"
APP_DEBUG_VAL="${4:-false}"

echo "==> $(git log -1 --oneline)"

if [ ! -f .env ]; then
  echo "File .env belum ada di $(pwd). Deploy pertama: tarik.sh (repo e-commerce) menyalin .env dari /var/www/dev."
  exit 1
fi

echo "==> Permission storage & cache"
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
sudo -n chown -R "$(whoami)":www-data storage bootstrap/cache
sudo -n chmod -R ug+rwX storage bootstrap/cache

echo "==> .env"
set_env () {
  local key="$1" val="$2" esc
  esc=$(printf '%s' "$val" | sed -e 's/[\/&|]/\\&/g')
  if grep -qE "^#?\s*${key}=" .env; then
    sed -i -E "s|^#?\s*${key}=.*|${key}=${esc}|" .env
  else
    printf '%s=%s\n' "$key" "$val" >> .env
  fi
}
set_env APP_ENV "$APP_ENV_VAL"
set_env APP_DEBUG "$APP_DEBUG_VAL"
set_env APP_URL "$APP_URL_VAL"
set_env DB_DATABASE "$DB_NAME"
NAMA_TOKO="$(php -r '$p = is_file("toko/profil.php") ? require "toko/profil.php" : []; echo str_replace("\"", "", $p["nama"] ?? "Toko");')"
set_env APP_NAME "\"${NAMA_TOKO}\""

echo "==> Composer install"
rm -f bootstrap/cache/config.php bootstrap/cache/routes-*.php
if [ "$APP_ENV_VAL" = "demo" ]; then
  composer install --no-dev --no-interaction --prefer-dist --no-progress --optimize-autoloader
else
  composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader
fi

if ! grep -qE '^APP_KEY=.+' .env; then
  php artisan key:generate --force
fi

echo "==> Migrate & seed"
php artisan config:clear
php artisan migrate --force
php artisan db:seed --force

echo "==> Cache"
php artisan storage:link 2>/dev/null || true
php artisan optimize
php artisan filament:optimize 2>/dev/null || true

sudo -n chown -R "$(whoami)":www-data storage bootstrap/cache
sudo -n chmod -R ug+rwX storage bootstrap/cache
sudo -n systemctl reload "php$(cat /etc/toko-php-version)-fpm" || true

echo "==> Cron scheduler"
CRON_LINE="* * * * * cd $(pwd) && php artisan schedule:run >> /dev/null 2>&1"
CRON_ADA="$(crontab -l 2>/dev/null || true)"
if ! printf '%s\n' "$CRON_ADA" | grep -qF "$CRON_LINE"; then
  printf '%s\n%s\n' "$CRON_ADA" "$CRON_LINE" | sed '/^$/d' | crontab -
fi

echo "==> Cek situs dari server"
HOSTNYA="$(echo "$APP_URL_VAL" | sed -E 's#^https?://([^/:]+).*#\1#')"
curl -sS -o /dev/null --max-time 20 --resolve "${HOSTNYA}:443:127.0.0.1" --resolve "${HOSTNYA}:80:127.0.0.1" \
  -w 'Situs: HTTP %{http_code}\n' "$APP_URL_VAL/" || true

echo "==> Deploy selesai"
