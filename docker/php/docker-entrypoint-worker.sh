#!/bin/sh
set -e

# Ждём пока основной PHP-контейнер установит зависимости
echo "Waiting for vendor/autoload.php (composer install in php container)..."
until [ -f /var/www/backend/vendor/autoload.php ]; do
    echo "  vendor not ready, retrying in 3s..."
    sleep 3
done

echo "Messenger worker starting..."
exec "$@"
