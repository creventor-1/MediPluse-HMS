#!/bin/bash
set -e

# If cloud provider defines PORT, configure Apache ports
if [ -n "$PORT" ]; then
    echo "Configuring Apache for PORT $PORT..."
    sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
    sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:$PORT>/g" /etc/apache2/sites-available/000-default.conf
fi

# Automatic database setup and migrations
if [ "${AUTO_MIGRATE:-true}" = "true" ] || [ "${AUTO_MIGRATE:-1}" = "1" ]; then
    echo "Checking database and running migrations..."
    php /var/www/html/database/migrate.php || echo "Notice: Database migrations skipped or database unreachable."
fi

exec apache2-foreground
