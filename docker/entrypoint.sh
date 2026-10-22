#!/bin/bash
set -e

# Sanitize carriage returns from environment files if mounted from Windows
[ -f /var/www/html/.env ] && sed -i 's/\r$//' /var/www/html/.env 2>/dev/null || true
[ -f /var/www/html/.env.example ] && sed -i 's/\r$//' /var/www/html/.env.example 2>/dev/null || true

echo "==================================================="
echo "  CuyoTech SSIS API - Container Initialization    "
echo "==================================================="

# 1. Custom command passthrough (e.g. running artisan or bash)
if [ "$#" -gt 0 ] && [ "$1" != "serve" ]; then
    exec "$@"
fi

# 2. Automated environment file setup
if [ ! -f /var/www/html/.env ]; then
    echo "[Init] .env file not found. Creating from .env.example..."
    if [ -f /var/www/html/.env.example ]; then
        cp /var/www/html/.env.example /var/www/html/.env
    else
        touch /var/www/html/.env
    fi
fi

# 3. Ensure required directories and permissions
echo "[Init] Setting up directory structures and permissions..."
mkdir -p /var/www/html/database
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache

chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod 777 /var/www/html/database 2>/dev/null || true

# 4. Ensure database connectivity or SQLite file setup
if [ "$DB_CONNECTION" = "sqlite" ]; then
    if [ ! -f /var/www/html/database/database.sqlite ]; then
        echo "[Init] Initializing database/database.sqlite..."
        touch /var/www/html/database/database.sqlite
        chmod 666 /var/www/html/database/database.sqlite 2>/dev/null || true
    fi
elif [ "$DB_CONNECTION" = "pgsql" ]; then
    echo "[Init] Waiting for PostgreSQL database connection (${DB_HOST:-postgres}:${DB_PORT:-5432})..."
    RETRY_COUNT=0
    MAX_RETRIES=30
    until pg_isready -h "${DB_HOST:-postgres}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-cuyotech}" -d "${DB_DATABASE:-cuyotech_ssis}" >/dev/null 2>&1 || [ "$RETRY_COUNT" -ge "$MAX_RETRIES" ]; do
        sleep 1
        RETRY_COUNT=$((RETRY_COUNT + 1))
    done
    if [ "$RETRY_COUNT" -ge "$MAX_RETRIES" ]; then
        echo "[Init] Warning: PostgreSQL not immediately reachable, attempting migrate anyway..."
    else
        echo "[Init] PostgreSQL is ready and accepting connections."
    fi
fi

# 5. Automated Composer dependencies installation
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "[Init] Composer dependencies missing. Running composer install..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# 6. Ensure APP_KEY exists
if ! grep -q "^APP_KEY=base64:" /var/www/html/.env 2>/dev/null && [ -z "$APP_KEY" ]; then
    echo "[Init] Generating application encryption key..."
    php artisan key:generate --force
fi

# 7. Automated database migrations
echo "[Init] Running database migrations..."
php artisan migrate --force

# 8. Automated database seeding (seed if empty or if FORCE_SEED is true)
USER_COUNT=$(php artisan tinker --execute 'try { echo \App\Models\User::count(); } catch (\Throwable $e) { echo "0"; }' 2>/dev/null || echo "0")
USER_COUNT=$(echo "$USER_COUNT" | tr -d '\r' | tr -d '[:space:]')
if [ "$USER_COUNT" = "0" ] || [ -z "$USER_COUNT" ] || [ "$FORCE_SEED" = "true" ] || [ "$FORCE_SEED" = "1" ]; then
    echo "[Init] Seeding database with official curriculum and users..."
    php artisan db:seed --force
else
    echo "[Init] Database already contains ${USER_COUNT} user records. Skipping automatic re-seed."
fi

# 9. Clear and optimize caches for container runtime
echo "[Init] Refreshing application caches..."
php artisan config:clear
php artisan route:clear

echo "==================================================="
echo "  Starting Laravel API server on 0.0.0.0:8000     "
echo "==================================================="
exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload
