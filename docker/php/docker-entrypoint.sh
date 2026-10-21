#!/bin/sh
set -e

if [ "$1" = 'php-fpm' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
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

    # The FPM container waits for the database and applies pending migrations.
    if [ "$1" = 'php-fpm' ]; then
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

    setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX var public/uploads 2>/dev/null || chmod -R a+rwX var public/uploads
    setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX var public/uploads 2>/dev/null || true
fi

exec docker-php-entrypoint "$@"
