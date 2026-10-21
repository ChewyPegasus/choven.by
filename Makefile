# Choven.by — developer commands. Run `make` to see them all.
# Everything runs inside Docker; set LOCAL=1 to run PHP commands with the PHP installed on your machine.

DOCKER_COMP = docker compose
PHP_CONT    = $(DOCKER_COMP) exec php
ifeq ($(LOCAL),1)
PHP      = php
COMPOSER = composer
else
PHP      = $(PHP_CONT) php
COMPOSER = $(PHP_CONT) composer
endif
CONSOLE = $(PHP) bin/console

.DEFAULT_GOAL := help
.PHONY: help up down restart logs sh build init install db migrate migration fixtures reset admin worker \
        assets cache test test-unit test-functional coverage phpstan cs cs-fix lint quality \
        prod-build prod-up prod-down backup restore

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

## —— Docker ————————————————————————————————————————————————————————————————
up: ## Start the development stack (http://localhost:8080, mail: http://localhost:8025)
	$(DOCKER_COMP) up -d --build --wait

down: ## Stop the stack
	$(DOCKER_COMP) down --remove-orphans

restart: down up ## Restart the stack

logs: ## Follow logs of all containers
	$(DOCKER_COMP) logs -f --tail=100

sh: ## Shell inside the PHP container
	$(PHP_CONT) bash

build: ## Rebuild images without cache
	$(DOCKER_COMP) build --pull --no-cache

init: up fixtures ## First start: containers, dependencies, database, demo data
	@echo "Ready: http://localhost:8080 — admin@choven.local / admin12345"

## —— Application ———————————————————————————————————————————————————————————
install: ## composer install + importmap
	$(COMPOSER) install
	$(CONSOLE) importmap:install

db: ## Create the database and run migrations
	$(CONSOLE) doctrine:database:create --if-not-exists
	$(CONSOLE) doctrine:migrations:migrate -n --allow-no-migration

migrate: ## Run pending migrations
	$(CONSOLE) doctrine:migrations:migrate -n --allow-no-migration

migration: ## Generate a migration from entity changes
	$(CONSOLE) make:migration

fixtures: ## Load demo data (keeps the tours and routes created by migrations)
	$(CONSOLE) doctrine:fixtures:load --append -n

reset: ## Drop and recreate the database with demo data
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(MAKE) db fixtures

admin: ## Create an administrator: make admin email=you@example.com
	$(CONSOLE) app:user:create $(email) --admin

worker: ## Run the queue worker in the foreground (emails, scheduled jobs)
	$(CONSOLE) messenger:consume async scheduler_default -vv

assets: ## Compile assets for production (public/assets)
	$(CONSOLE) asset-map:compile

cache: ## Clear the cache
	$(CONSOLE) cache:clear

## —— Quality ————————————————————————————————————————————————————————————————
test: ## Run all tests (recreates the test database)
	$(PHP) vendor/bin/phpunit

test-unit: ## Unit tests only (no database)
	$(PHP) vendor/bin/phpunit --testsuite unit

test-functional: ## Functional tests only
	$(PHP) vendor/bin/phpunit --testsuite functional

coverage: ## HTML coverage report in var/coverage (needs Xdebug or PCOV)
	$(PHP_CONT) env XDEBUG_MODE=coverage php vendor/bin/phpunit --coverage-html var/coverage

phpstan: ## Static analysis (level 8)
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G

cs: ## Check coding style
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Fix coding style
	$(PHP) vendor/bin/php-cs-fixer fix

lint: ## Lint container, Twig, YAML and Doctrine mapping
	$(CONSOLE) lint:container
	$(CONSOLE) lint:twig templates
	$(CONSOLE) lint:yaml config translations --parse-tags
	$(CONSOLE) doctrine:schema:validate --skip-sync

quality: lint cs phpstan test ## Everything CI runs

## —— Production —————————————————————————————————————————————————————————————
PROD = $(DOCKER_COMP) -f compose.prod.yaml --env-file .env.prod.local

prod-build: ## Build production images
	$(PROD) build --pull

prod-up: ## Start / update the production stack
	$(PROD) up -d --wait

prod-down: ## Stop the production stack
	$(PROD) down

backup: ## Dump the database to var/backups/ (add PROD_MODE=1 for the production stack)
	@mkdir -p var/backups
	$(if $(PROD_MODE),$(PROD),$(DOCKER_COMP)) exec -T database sh -c 'pg_dump -U "$$POSTGRES_USER" -Fc "$$POSTGRES_DB"' > var/backups/choven-$$(date +%Y%m%d-%H%M%S).dump
	@ls -1t var/backups | head -1

restore: ## Restore a dump: make restore file=var/backups/choven-….dump
	$(DOCKER_COMP) exec -T database sh -c 'pg_restore -U "$$POSTGRES_USER" -d "$$POSTGRES_DB" --clean --if-exists' < $(file)
