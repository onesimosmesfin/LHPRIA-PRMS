#!/bin/bash
set -e

echo "Starting MariaDB service..."
service mariadb start

# Wait a brief moment to ensure MariaDB is ready to accept commands
for i in {1..15}; do
    if mysqladmin ping --silent 2>/dev/null; then
        break
    fi
    echo "Waiting for MariaDB socket..."
    sleep 1
done

echo "Configuring MySQL / MariaDB permissions and user access..."
# 1. Create prms_db
mysql -e "CREATE DATABASE IF NOT EXISTS \`prms_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" || true

# 2. Grant root access without unix_socket restriction (allows PHP/Apache to connect as root)
mysql -e "
ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password USING PASSWORD('');
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED VIA mysql_native_password USING PASSWORD('');
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED VIA mysql_native_password USING PASSWORD('');
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;

-- Create dedicated non-root application user
CREATE USER IF NOT EXISTS 'prms_user'@'localhost' IDENTIFIED VIA mysql_native_password USING PASSWORD('prms_password_2026');
GRANT ALL PRIVILEGES ON \`prms_db\`.* TO 'prms_user'@'localhost';
CREATE USER IF NOT EXISTS 'prms_user'@'127.0.0.1' IDENTIFIED VIA mysql_native_password USING PASSWORD('prms_password_2026');
GRANT ALL PRIVILEGES ON \`prms_db\`.* TO 'prms_user'@'127.0.0.1';
CREATE USER IF NOT EXISTS 'prms_user'@'%' IDENTIFIED VIA mysql_native_password USING PASSWORD('prms_password_2026');
GRANT ALL PRIVILEGES ON \`prms_db\`.* TO 'prms_user'@'%';

FLUSH PRIVILEGES;
" 2>/dev/null || mysql -e "
ALTER USER 'root'@'localhost' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
CREATE USER IF NOT EXISTS 'prms_user'@'localhost' IDENTIFIED BY 'prms_password_2026';
GRANT ALL PRIVILEGES ON \`prms_db\`.* TO 'prms_user'@'localhost';
CREATE USER IF NOT EXISTS 'prms_user'@'127.0.0.1' IDENTIFIED BY 'prms_password_2026';
GRANT ALL PRIVILEGES ON \`prms_db\`.* TO 'prms_user'@'127.0.0.1';
FLUSH PRIVILEGES;
" || true

# Check if tables already exist
TABLES=$(mysql -N -s -e "SELECT count(*) FROM information_schema.tables WHERE table_schema='prms_db';" 2>/dev/null || echo "0")
if [ "$TABLES" = "0" ] || [ -z "$TABLES" ]; then
    echo "Importing prms_db.sql..."
    if [ -f /var/www/html/land/prms_db.sql ]; then
        mysql prms_db < /var/www/html/land/prms_db.sql
        echo "prms_db schema imported from /var/www/html/land/prms_db.sql."
    elif [ -f /var/www/html/prms_db.sql ]; then
        mysql prms_db < /var/www/html/prms_db.sql
        echo "prms_db schema imported from /var/www/html/prms_db.sql."
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
