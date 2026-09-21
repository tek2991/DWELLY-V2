#!/bin/sh
set -e

# Ensure storage link exists if public/storage is missing
if [ ! -L /var/www/html/public/storage ] && [ ! -d /var/www/html/public/storage ]; then
    php artisan storage:link --quiet || true
fi

# Ensure Filament assets are published if missing
if [ ! -d /var/www/html/public/css/filament ]; then
    php artisan filament:assets --quiet || true
fi

# Ensure storage permissions are intact
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

exec "$@"
