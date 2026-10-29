#!/bin/sh
set -e
# Render fournit PORT ; Apache doit écouter dessus.
PORT="${PORT:-10000}"
sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Seul le service web (Apache) migre, avec la connexion directe Neon (variable RUN_MIGRATIONS=true).
# À faire AVANT config:cache : une configuration en cache ignorerait DB_URL_DIRECT.
if [ "${RUN_MIGRATIONS:-false}" = "true" ] && [ "$1" = "apache2-foreground" ]; then
  DB_URL="${DB_URL_DIRECT:-$DB_URL}" php artisan migrate --force
  # Référentiel de départ (idempotent). Aucune donnée de démonstration en production.
  DB_URL="${DB_URL_DIRECT:-$DB_URL}" php artisan db:seed --class=BlockReasonSeeder --force
fi

# Hébergement sans terminal (plan gratuit) : crée le premier administrateur depuis ADMIN_EMAIL,
# ADMIN_NAME et ADMIN_PASSWORD, une seule fois. Avant config:cache pour lire l'environnement.
if [ "${CREATE_ADMIN_ON_BOOT:-false}" = "true" ] && [ "$1" = "apache2-foreground" ]; then
  php artisan users:create-admin --from-env
fi

php artisan config:cache
php artisan route:cache

exec "$@"
