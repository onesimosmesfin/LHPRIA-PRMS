FROM php:8.2-apache

# Install MariaDB and required utilities
RUN apt-get update && apt-get install -y --no-install-recommends \
    mariadb-server \
    mariadb-client \
    dos2unix \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite

# ServerName configuration
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY land/ /var/www/html/land/
COPY land/index.php /var/www/html/index.php
COPY entrypoint.sh /entrypoint.sh

# Convert line endings and ensure execution permission on entrypoint
RUN dos2unix /entrypoint.sh \
    && chmod +x /entrypoint.sh \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
