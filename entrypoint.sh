#!/bin/bash
set -e

echo "Starting MariaDB..."
service mariadb start

echo "Verifying prms_db database initialization..."
mysql -e "CREATE DATABASE IF NOT EXISTS prms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" || true
mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION; FLUSH PRIVILEGES;" || true

# Check if tables already exist
TABLES=$(mysql -N -s -e "SELECT count(*) FROM information_schema.tables WHERE table_schema='prms_db';" 2>/dev/null || echo "0")
if [ "$TABLES" = "0" ] || [ -z "$TABLES" ]; then
    echo "Importing prms_db.sql..."
    if [ -f /var/www/html/land/prms_db.sql ]; then
        mysql prms_db < /var/www/html/land/prms_db.sql
        echo "prms_db schema imported successfully."
    fi
else
    echo "prms_db tables already exist ($TABLES tables found)."
fi

# Adapt Apache listening port if cloud host provides $PORT (e.g. Render/Heroku)
if [ -n "$PORT" ] && [ "$PORT" != "80" ]; then
    echo "Configuring Apache to listen on port $PORT..."
    sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf
    sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:$PORT>/" /etc/apache2/sites-available/000-default.conf
fi

echo "Starting Apache Web Server in foreground..."
exec apache2-foreground
