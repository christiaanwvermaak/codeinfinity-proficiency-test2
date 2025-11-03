FROM php:8.4-apache

# Install system dependencies and PHP extensions needed for SQLite and Composer
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev zip unzip git curl sqlite3 libsqlite3-dev \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Install Composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer


WORKDIR /var/www/html

# Copy composer files first to leverage Docker cache when dependencies don't change
COPY composer.json composer.lock* /var/www/html/

# If composer.json exists, install PHP dependencies during build (non-interactive)
RUN if [ -f /var/www/html/composer.json ]; then composer install --no-interaction --prefer-dist --working-dir=/var/www/html; fi

# Install and enable SQLite extensions for PHP (pdo_sqlite and sqlite3)
RUN docker-php-ext-install pdo_sqlite sqlite3 || true

# Install and enable custom Apache configuration (httpd.conf)
COPY httpd.conf /etc/apache2/conf-available/httpd.conf
RUN a2enconf httpd

# Copy custom PHP ini overrides
COPY php/conf/uploads.ini /usr/local/etc/php/conf.d/uploads.ini


# Remove any conf.d ini files that would load PDO extensions
RUN rm -f /usr/local/etc/php/conf.d/*pdo*.ini || true
RUN rm -f /usr/local/etc/php/conf.d/*pdo_*.ini || true

# Copy application source
COPY app /var/www/html

# Ensure correct permissions for Apache
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

EXPOSE 80

CMD ["apache2-foreground"]
