#!/bin/bash
set -e

# Pastikan direktori storage dan cache ada
mkdir -p /var/www/storage/framework/{sessions,views,cache}
mkdir -p /var/www/storage/logs
mkdir -p /var/www/bootstrap/cache

# Set permission — gunakan 777 agar kompatibel dengan volume mount dari Windows
# (chown tidak efektif pada bind mount Windows → Linux)
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache 2>/dev/null || true
chmod -R 777 /var/www/storage /var/www/bootstrap/cache

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force 2>/dev/null || true
fi

# Run migrations
php artisan migrate --force 2>/dev/null || true

# Clear old cache first, then Cache config and routes
php artisan config:clear 2>/dev/null || true
php artisan config:cache 2>/dev/null || true
php artisan route:cache 2>/dev/null || true
php artisan view:cache 2>/dev/null || true

exec "$@"
