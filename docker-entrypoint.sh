#!/bin/bash
set -e

# Ensure Laravel runtime directories exist.
mkdir -p /var/www/storage/framework/{sessions,views,cache}
mkdir -p /var/www/storage/logs
mkdir -p /var/www/bootstrap/cache

# Runtime permissions. In dev, storage/framework and storage/logs are named
# Docker volumes, so this is much cheaper than touching the whole app tree.
if [ "${LARAVEL_FIX_PERMISSIONS:-true}" = "true" ]; then
    chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache 2>/dev/null || true
    chmod -R 775 /var/www/storage /var/www/bootstrap/cache 2>/dev/null || true
fi

# Generate APP_KEY if not set.
if [ -z "$APP_KEY" ] && [ "${LARAVEL_GENERATE_KEY:-true}" = "true" ]; then
    php artisan key:generate --force 2>/dev/null || true
fi

# Preserve previous production behavior by default, but let dev compose skip it.
if [ "${LARAVEL_AUTO_MIGRATE:-true}" = "true" ]; then
    php artisan migrate --force 2>/dev/null || true
fi

if [ "${LARAVEL_CACHE_BOOTSTRAP:-true}" = "true" ]; then
    php artisan config:clear 2>/dev/null || true
    php artisan config:cache 2>/dev/null || true
    php artisan route:cache 2>/dev/null || true
    php artisan view:cache 2>/dev/null || true
elif [ "${LARAVEL_CLEAR_BOOTSTRAP:-false}" = "true" ]; then
    php artisan optimize:clear 2>/dev/null || true
fi

exec "$@"
