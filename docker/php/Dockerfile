FROM php:8.4-fpm

# System libraries needed by the PHP extensions below
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev libicu-dev \
    && docker-php-ext-install pdo_pgsql pgsql zip intl bcmath pcntl opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Run as the host user's UID/GID so files created in the bind mount are owned by you, not root
ARG UID=1000
ARG GID=1000
RUN groupmod -o -g ${GID} www-data && usermod -o -u ${UID} -g ${GID} www-data

COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/backend
USER www-data
