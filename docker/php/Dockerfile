# syntax=docker/dockerfile:1.7
#
# Targets:
#   php_dev  – development image (PHP-FPM behind nginx, see compose.yaml), the source code is mounted as a volume
#   app_prod – self-contained production image: FrankenPHP (Caddy + PHP in worker mode) with the code, vendor and
#              precompressed assets baked in; the same image runs the queue worker (see compose.prod.yaml)

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

HEALTHCHECK --interval=10s --timeout=3s --retries=3 --start-period=180s --start-interval=3s CMD ["php-fpm-healthcheck"]
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
FROM dunglas/frankenphp:1-php${PHP_VERSION}-bookworm AS app_prod

ENV APP_ENV=prod \
    COMPOSER_ALLOW_SUPERUSER=1

# brotli and zstd are only used by asset-map:compile to precompress the assets that Caddy then serves as they are.
RUN set -eux; \
    install-php-extensions \
        apcu \
        brotli \
        gd \
        intl \
        opcache \
        pdo_pgsql \
        redis \
        zip \
        zstd \
    ; \
    apt-get update; \
    apt-get install -y --no-install-recommends libcap2-bin; \
    # The server runs as www-data and still binds ports 80/443.
    setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp; \
    apt-get purge -y --auto-remove libcap2-bin; \
    rm -rf /var/lib/apt/lists/*; \
    mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=composer/composer:2-bin /composer /usr/bin/composer
COPY docker/php/conf.d/app.ini $PHP_INI_DIR/conf.d/10-app.ini
COPY docker/php/conf.d/app.prod.ini $PHP_INI_DIR/conf.d/20-app.prod.ini
COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY --chmod=755 docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --chmod=755 docker/frankenphp/healthcheck.sh /usr/local/bin/app-healthcheck

WORKDIR /app

# Dependencies first, for better layer caching.
COPY composer.json composer.lock symfony.lock ./
RUN set -eux; \
    composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress --no-interaction

COPY . ./
RUN set -eux; \
    rm -rf tests; \
    mkdir -p var/cache var/log public/uploads public/media; \
    composer dump-autoload --classmap-authoritative --no-dev; \
    composer dump-env prod; \
    composer run-script --no-dev post-install-cmd; \
    php bin/console asset-map:compile; \
    # Fail the build if precompression silently did not happen.
    for ext in br zst gz; do ls public/assets/styles/*.css.$ext > /dev/null; done; \
    # Responsive AVIF/WebP variants of the photos in assets/images (public/media); uploads get theirs at runtime.
    php bin/console app:images:optimize --no-interaction; \
    chmod +x bin/console; \
    chown -R www-data:www-data var public/uploads public/media /data/caddy /config/caddy; \
    sync

USER www-data

HEALTHCHECK --interval=10s --timeout=5s --retries=3 --start-period=180s --start-interval=3s CMD ["app-healthcheck"]

ENTRYPOINT ["docker-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
