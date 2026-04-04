#!/bin/sh
set -e

# Устанавливаем зависимости (в prod без dev-пакетов)
echo "Installing Composer dependencies..."
if [ "$APP_ENV" = "prod" ]; then
    cd /var/www/backend && COMPOSER_MEMORY_LIMIT=-1 composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
else
    cd /var/www/backend && composer install --no-interaction --prefer-dist
fi

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
chmod -R 777 /var/www/backend/var/

# Прогреть кэш в prod, чтобы proxy-классы Doctrine были сгенерированы заранее
if [ "$APP_ENV" = "prod" ]; then
    echo "Warming up cache..."
    cd /var/www/backend && php bin/console cache:warmup --env=prod --no-debug || echo "Cache warmup failed, continuing..."
    chmod -R 777 /var/www/backend/var/
fi

exec "$@"
