# syntax=docker/dockerfile:1

FROM php:8.4-fpm-bookworm

ARG UID=1000
ARG GID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        pdo_mysql \
        zip \
        opcache

# Shared cache backend for the rate limiter and the token-revocation registries.
RUN pecl install redis \
    && docker-php-ext-enable redis

# Remap www-data to the host user so files written from the container
# (vendor/, var/, migrations/) stay editable on the host.
RUN usermod  -u "${UID}" www-data \
    && groupmod -g "${GID}" www-data \
    && chown -R www-data:www-data /var/www

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini      /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    COMPOSER_HOME=/tmp/composer

# Must exist with the right owner before the named volume is initialised from it,
# otherwise Docker creates a root-owned mount and Composer cannot write its cache.
RUN mkdir -p /tmp/composer \
    && chown -R www-data:www-data /tmp/composer

WORKDIR /app

EXPOSE 9000

CMD ["php-fpm"]
