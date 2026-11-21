#!/bin/sh
# Runs every time Render starts (or wakes) the app.
set -e

# Render tells us which port to listen on
PORT="${PORT:-80}"
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

php artisan config:cache

# Safe to run on every start: only new migrations run; seeders never overwrite existing data
php artisan migrate --force
php artisan db:seed --force

chown -R www-data:www-data storage bootstrap/cache
exec apache2-foreground
