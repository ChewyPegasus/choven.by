# syntax=docker/dockerfile:1.7
#
# Targets:
#   php_dev    – development image, the source code is mounted as a volume
#   php_prod   – self-contained production image (code, vendor and compiled assets baked in)
#   nginx_prod – nginx serving public/ of php_prod and proxying PHP requests to it

ARG PHP_VERSION=8.3

FROM php:${PHP_VERSION}-fpm-bookworm AS php_base

# install-php-extensions builds extensions with their system dependencies and cleans up after itself
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN set -eux; \
    install-php-extensions \
        apcu \
        gd \
        intl \
        opcache \
        pdo_pgsql \
        redis \
        zip \
    ; \
    apt-get update; \
    apt-get install -y --no-install-recommends git unzip acl; \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer/composer:2-bin /composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    PHP_INI_SCAN_DIR=":/usr/local/etc/php/app.conf.d"

COPY docker/php/conf.d/app.ini /usr/local/etc/php/app.conf.d/10-app.ini
COPY docker/php/php-fpm.d/zz-app.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY --chmod=755 docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint

WORKDIR /app

HEALTHCHECK --interval=10s --timeout=3s --retries=3 CMD ["php-fpm-healthcheck"]
COPY --chmod=755 docker/php/php-fpm-healthcheck.sh /usr/local/bin/php-fpm-healthcheck

ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
FROM php_base AS php_dev

ENV APP_ENV=dev

RUN set -eux; \
    mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"; \
    install-php-extensions xdebug; \
    { echo 'xdebug.mode=off'; echo 'xdebug.client_host=host.docker.internal'; } > /usr/local/etc/php/app.conf.d/20-xdebug.ini

# ---------------------------------------------------------------------------
FROM php_base AS php_prod

ENV APP_ENV=prod

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/conf.d/app.prod.ini /usr/local/etc/php/app.conf.d/20-app.prod.ini

# Dependencies first, for better layer caching.
COPY composer.json composer.lock symfony.lock ./
RUN set -eux; \
    composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress --no-interaction

COPY . ./
RUN set -eux; \
    rm -rf legacy tests; \
    mkdir -p var/cache var/log public/uploads; \
    composer dump-autoload --classmap-authoritative --no-dev; \
    composer dump-env prod; \
    composer run-script --no-dev post-install-cmd; \
    php bin/console asset-map:compile; \
    chmod +x bin/console; \
    chown -R www-data:www-data var public/uploads; \
    sync

# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS nginx_prod

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=php_prod /app/public /app/public
