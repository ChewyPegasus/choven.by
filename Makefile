.PHONY: help install docker db server clear stop build dev migrations kafka-start kafka-stop kafka-status docker-php compile-assets cron-start cron-stop cron-logs cron-status query admin git-log phpstan phpstan-strict phpstan-baseline phpstan-clear code-quality

.DEFAULT_GOAL := help

help:
	@echo "Choven.by - Water rafting management system"
	@echo ""
	@echo "Available commands:"
	@echo "  make install           - Install project dependencies"
	@echo "  make docker            - Start Docker containers"
	@echo "  make db                - Create and migrate the database"
	@echo "  make server            - Start Symfony development server"
	@echo "  make clear             - Clear cache"
	@echo "  make migrations        - Create migrations after entity changes"
	@echo "  make run               - Start the entire project (docker + db + server)"
	@echo "  make dev               - Install and start the project for development"
	@echo "  make stop              - Stop all processes"
	@echo "  make kafka-start       - Start Kafka order consumer (in background)"
	@echo "  make kafka-stop        - Stop Kafka order consumer"
	@echo "  make kafka-status      - Check if Kafka consumer is running"
	@echo "  make docker-php        - Enter the php container"
	@echo "  make refresh           - Clear cache and recompile assets"
	@echo "  make cron-start        - Start the cron container"
	@echo "  make cron-stop         - Stop the cron container"
	@echo "  make cron-logs         - Logs from cron container"
	@echo "  make cron-status       - Status of cron container"
	@echo "  make query             - Run raw SQL query (use q=...)"
	@echo "  make admin             - Make user admin (use admin=...)"
	@echo "  make git-log           - Show git log graph"
	@echo "  make phpstan           - Run PHPStan analysis"
	@echo "  make phpstan-light     - Run PHPStan with lower level for faster analysis"
	@echo "  make phpstan-baseline  - Generate PHPStan baseline"
	@echo "  make phpstan-clear     - Clear PHPStan cache"
	@echo "  make code-quality      - Run all code quality checks"
	@echo "  test                   - Run all tests"
	@echo "  test-unit              - Run unit-tests"
	@echo "  test-coverage          - Run coverage-tests"
	@echo "  test-integration       - Run integration-tests"
	@echo "  test-functional        - Run functional-tests"
	
install:
	@docker-compose exec php chown -R www-data:www-data var vendor
	@docker-compose exec php git config --global --add safe.directory /var/www
	@docker-compose exec -u www-data -e COMPOSER_PROCESS_TIMEOUT=600 php php -d xdebug.mode=off /usr/bin/composer install --no-scripts --optimize-autoloader
    
	@echo "Warming up cache..."
	@docker-compose exec -u www-data php php bin/console cache:clear
	@echo "Compiling assets..."
	@docker-compose exec -u www-data php php bin/console asset-map:compile

docker:
	@docker-compose up -d --build

db:
	@docker-compose exec -u www-data php php bin/console doctrine:database:create --if-not-exists
	@docker-compose exec -u www-data php php bin/console doctrine:migrations:migrate --no-interaction

migrations:
	@docker-compose exec -u www-data php php bin/console make:migration

server:
	@echo "The application is served by the Nginx container. Please access it at http://localhost"

clear:
	@docker-compose exec -u www-data php php bin/console cache:clear

dev: docker install db server

run: docker install db

stop: kafka-stop
	@docker-compose down

kafka-start:
	@echo "Starting Kafka order consumer container..."
	@docker-compose up -d consumer

kafka-stop:
	@echo "Stopping Kafka consumer container..."
	@docker-compose stop consumer

kafka-status:
	@echo "Kafka consumer container status:"
	@docker-compose ps consumer

docker-php:
	docker-compose exec -u www-data php bash

refresh:
	@echo "Clearing cache..."
	@docker-compose exec -u www-data php php bin/console cache:clear
	@echo "Recompiling assets..."
	@docker-compose exec -u www-data php php bin/console asset-map:compile
	@echo "✅ Refresh complete. Your changes should be visible now."

cron-start:
	@echo "Starting cron container..."
	@docker-compose up -d cron

cron-stop:
	@echo "Stopping cron container..."
	@docker-compose stop cron

cron-logs:
	@docker-compose exec cron tail -f /var/log/cron.log

cron-status:
	@echo "Cron container status:"
	@docker-compose ps cron

query:
	docker-compose exec php bin/console doctrine:query:sql "$(q)"

admin:
	docker-compose exec php php bin/console app:user:make-admin "$(admin)"

git-log:
	git log --graph --oneline --decorate

phpstan-prepare: ## Prepare environment for PHPStan
	@echo "Preparing environment for PHPStan analysis..."
	@docker-compose exec -u www-data php php bin/console cache:clear --env=dev
	@docker-compose exec -u www-data php php bin/console cache:warmup --env=dev
	@echo "Environment prepared!"

phpstan: phpstan-prepare
	docker-compose exec -u www-data php vendor/bin/phpstan analyse --memory-limit=512M

phpstan-baseline: phpstan-prepare
	docker-compose exec -u www-data php vendor/bin/phpstan analyse --generate-baseline --memory-limit=512M

phpstan-clear:
	docker-compose exec -u www-data php vendor/bin/phpstan clear-result-cache

phpstan-light: phpstan-prepare
	@echo "Running light PHPStan analysis (level 6)..."
	docker-compose exec -u www-data php vendor/bin/phpstan analyse --level=6 --memory-limit=256M

code-quality: phpstan
	@echo "Code quality checks completed!"

test:
	@docker-compose exec php bin/console cache:clear --env=test --no-warmup
	@docker-compose exec php vendor/bin/phpunit --testdox

test-unit:
	@docker-compose exec php vendor/bin/phpunit tests/Unit --testdox

test-functional:
	@docker-compose exec php vendor/bin/phpunit tests/Functional --testdox

test-integration:
	@docker-compose exec php vendor/bin/phpunit tests/Integration --testdox

test-coverage:
	@docker-compose exec php vendor/bin/phpunit --coverage-html var/coverage
