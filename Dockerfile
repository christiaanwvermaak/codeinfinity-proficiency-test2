FROM php:8.2-apache

# Install system dependencies and PHP extensions needed for SQLite and Composer
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev zip unzip git curl sqlite3 libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Install Composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html

# Copy composer files first to leverage Docker cache when dependencies don't change
COPY composer.json composer.lock* /var/www/html/

# If composer.json exists, install PHP dependencies during build (non-interactive)
RUN if [ -f composer.json ]; then composer install --no-interaction --prefer-dist; fi

# Copy application source
COPY . /var/www/html

# Ensure correct permissions for Apache
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

EXPOSE 80

CMD ["apache2-foreground"]
