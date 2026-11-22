#!/bin/sh
set -eu

port="${PORT:-10000}"

# Render supplies PORT at runtime. Apache's default port is changed only inside
# this disposable container.
sed -ri "s/^Listen 80$/Listen ${port}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

php artisan storage:link --force >/dev/null 2>&1 || true
php artisan view:cache >/dev/null

exec apache2-foreground
