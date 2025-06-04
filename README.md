docker-compose up -d --build

symfony serve

php bin/console doctrine:database:create

php bin/console doctrine:migrations:migrate