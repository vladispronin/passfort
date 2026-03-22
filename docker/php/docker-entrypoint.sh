#!/bin/sh
set -e

# Устанавливаем зависимости (всегда, чтобы гарантировать согласованность с composer.lock)
echo "Installing Composer dependencies..."
cd /var/www/backend && composer install --no-interaction --prefer-dist

# Generate JWT keys if they don't exist
if [ ! -f "/var/www/backend/config/jwt/private.pem" ]; then
    echo "Generating JWT keys..."
    mkdir -p /var/www/backend/config/jwt
    cd /var/www/backend && php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction || true
fi

# Run migrations
echo "Running database migrations..."
cd /var/www/backend && php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || echo "Migrations failed, continuing..."

# Убедиться, что директории var/cache и var/log существуют и доступны для записи
echo "Setting up var directory permissions..."
mkdir -p /var/www/backend/var/cache/prod/doctrine/orm/Proxies
mkdir -p /var/www/backend/var/cache/dev/doctrine/orm/Proxies
mkdir -p /var/www/backend/var/log
chown -R www-data:www-data /var/www/backend/var/ 2>/dev/null || chmod -R 777 /var/www/backend/var/

exec "$@"
