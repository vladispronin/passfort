#!/bin/sh
set -e

# Install composer dependencies if vendor doesn't exist
if [ ! -d "/var/www/backend/vendor" ]; then
    echo "Installing Composer dependencies..."
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

exec "$@"
