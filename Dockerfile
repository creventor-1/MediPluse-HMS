FROM php:8.2-apache

# Install mysql client utilities, ca-certificates (for SSL cloud DBs like Aiven), and PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    default-mysql-client \
    ca-certificates \
    && docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Allow .htaccess overrides
RUN printf '<Directory /var/www/html/>\n    Options -Indexes +FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/override.conf \
    && a2enconf override

# Copy application files (JSON array format handles space in directory name)
COPY ["MediPluse HMS/", "/var/www/html/"]

# Copy entrypoint
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

# Set ownership
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
