#!/bin/sh
set -e

# Shared by the development image (php-fpm) and the production image (FrankenPHP, docker/frankenphp/Caddyfile).
# The web container (php-fpm / frankenphp) waits for the database and applies migrations; the queue worker
# (`php bin/console messenger:consume ...`) starts after it is healthy and skips both.

# Options only ("--config ..."): run them with FrankenPHP, as its own entrypoint does.
if [ "${1#-}" != "$1" ] && command -v frankenphp >/dev/null 2>&1; then
    set -- frankenphp run "$@"
fi

WEB_SERVER=0
if [ "$1" = 'php-fpm' ] || { [ "$1" = 'frankenphp' ] && [ "$2" = 'run' ]; }; then
    WEB_SERVER=1
fi

if [ "$WEB_SERVER" = '1' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
    mkdir -p var/cache var/log var/sessions public/uploads

    # Development: the code is a bind mount, install dependencies on first start.
    if [ "$APP_ENV" != 'prod' ]; then
        if [ ! -f vendor/autoload_runtime.php ]; then
            composer install --prefer-dist --no-progress --no-interaction
        fi
        if [ ! -d assets/vendor ]; then
            php bin/console importmap:install --no-interaction || true
        fi
    fi

    if [ "$WEB_SERVER" = '1' ]; then
        ATTEMPTS_LEFT=60
        until [ $ATTEMPTS_LEFT -eq 0 ] || php bin/console dbal:run-sql -q "SELECT 1" >/dev/null 2>&1; do
            sleep 1
            ATTEMPTS_LEFT=$((ATTEMPTS_LEFT - 1))
            echo "Waiting for the database... $ATTEMPTS_LEFT attempts left"
        done

        if [ $ATTEMPTS_LEFT -eq 0 ]; then
            echo 'The database is not reachable:'
            php bin/console dbal:run-sql "SELECT 1" || true
            exit 1
        fi

        if [ "${RUN_MIGRATIONS:-1}" = '1' ]; then
            php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing --allow-no-migration
        fi
    fi

    # In production the directories live inside the image/volumes; in development they are a bind mount
    # (often from Windows/macOS) where recursive permission changes are slow and unnecessary.
    # The FrankenPHP image already runs as www-data, which owns them.
    if [ "$APP_ENV" = 'prod' ] && [ "$(id -u)" = '0' ]; then
        setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX var public/uploads 2>/dev/null || chmod -R a+rwX var public/uploads
        setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX var public/uploads 2>/dev/null || true
    fi
fi

exec docker-php-entrypoint "$@"
