#!/bin/sh
set -e

# 1. Run Composer install
composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader

# 2. Wait for the database to be ready
echo "Waiting for database..."
while ! nc -z db 3306; do
  sleep 1
done
echo "Database is ready."

# 3. Run migrations
php artisan migrate:fresh --force


# 4. Run seeders
php artisan db:seed --force

# 5. Clear caches and optimize for production
php artisan optimize

echo "Application setup complete. Starting services"

# 6. Start the services
# Start PHP-FPM in the background
php-fpm &

# Start Supervisor to manage queue and reverb workers
/usr/bin/supervisord -c /etc/supervisor/supervisord.conf &

# Start Nginx in the foreground. This is the main process that will keep the container running.
exec nginx -g 'daemon off;'
