#!/bin/sh
set -e
# Render fournit PORT ; Apache doit écouter dessus.
PORT="${PORT:-10000}"
sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

php artisan config:cache
php artisan route:cache

# Seul le service web migre (variable RUN_MIGRATIONS=true), avec la connexion directe Neon.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  DB_URL="${DB_URL_DIRECT:-$DB_URL}" php artisan migrate --force
fi

exec "$@"
