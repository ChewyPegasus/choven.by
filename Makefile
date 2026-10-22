# Choven.by — all project commands. Run `make` (or `make help`) to list them.
#
# Two ways to run the project:
#   MODE=local  – PHP runs on your machine, Docker only for PostgreSQL, Redis and Mailpit.
#                 Default on Windows (Docker bind mounts are slow there).
#   MODE=docker – everything in Docker (default on Linux/macOS).
# Override per command (`make start MODE=docker`) or permanently in a `.make.local` file (MODE=docker).

-include .make.local

ifeq ($(OS),Windows_NT)
    # GNU Make on Windows: use Git Bash (the bash.exe in PATH is usually the WSL launcher).
    SHELL := C:/PROGRA~1/Git/bin/bash.exe
    # Unix tools of Git for Windows (awk, grep, sed, date...) for commands make runs without a shell.
    export PATH := C:\Program Files\Git\usr\bin;C:\Program Files\Git\mingw64\bin;$(PATH)
    MODE ?= local
    OPEN := cmd //c start ""
else
    SHELL := /bin/bash
    MODE ?= docker
    OPEN := $(shell command -v xdg-open || echo open)
endif
.SHELLFLAGS := -ec

PORT        ?= 8000
DOCKER_COMP ?= docker compose
PROD_COMP    = $(DOCKER_COMP) -f compose.prod.yaml --env-file .env.prod.local

ifeq ($(MODE),docker)
    PHP      = $(DOCKER_COMP) exec php php
    COMPOSER = $(DOCKER_COMP) exec php composer
    APP_URL  = http://localhost:$${HTTP_PORT:-8080}
    # php with extra environment variables: $(call php_env,VAR=value ...)
    php_env  = $(DOCKER_COMP) exec $(foreach v,$(1),-e $(v)) php php
else
    PHP      = php
    COMPOSER = composer
    APP_URL  = http://127.0.0.1:$(PORT)
    php_env  = $(1) php
endif
CONSOLE = $(PHP) bin/console

.DEFAULT_GOAL := help
.PHONY: $(shell grep -oE '^[a-zA-Z0-9_-]+:' $(firstword $(MAKEFILE_LIST)) | tr -d ':')

help: ## Show all commands
	@echo "Choven.by - mode: $(MODE)  (MODE=local|docker)"
	@awk 'BEGIN {FS = ":.*?## "} /^## /{printf "\n\033[1m%s\033[0m\n", substr($$0, 4)} /^[a-zA-Z0-9_-]+:.*?## /{printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

## Getting started
init: ## First start: dependencies, services, database with demo data, then run the site
ifeq ($(MODE),docker)
	$(DOCKER_COMP) up -d --build --wait
	$(MAKE) --no-print-directory demo-data
else
	$(MAKE) --no-print-directory services install db demo-data start
endif
	@echo ""
	@echo "  Site:   $(APP_URL)/ru/"
	@echo "  Admin:  $(APP_URL)/ru/admin  -  admin@choven.local / admin12345"
	@echo "  Client: user@choven.local / user12345"
	@echo "  Emails: http://localhost:8025"

start: ## Start the site (and the queue worker) in the background
ifeq ($(MODE),docker)
	$(DOCKER_COMP) up -d --wait
else
	$(MAKE) --no-print-directory services migrate
	@PORT=$(PORT) bin/dev start
endif
	@echo "  Open $(APP_URL)/ru/  |  emails: http://localhost:8025"

stop: ## Stop the site and the worker (services keep running; `make down` stops everything)
ifeq ($(MODE),docker)
	$(DOCKER_COMP) stop php nginx worker
else
	@PORT=$(PORT) bin/dev stop
endif

restart: stop start ## Restart the site and the worker

down: stop ## Stop everything, including database, Redis and Mailpit (data is kept)
	$(DOCKER_COMP) down --remove-orphans

status: ## What is running
ifneq ($(MODE),docker)
	@PORT=$(PORT) bin/dev status
endif
	@$(DOCKER_COMP) ps --format "table {{.Service}}\t{{.Status}}\t{{.Ports}}"

open: ## Open the site in the browser
	@$(OPEN) "$(APP_URL)/ru/"

admin-open: ## Open the admin panel in the browser
	@$(OPEN) "$(APP_URL)/ru/admin"

mail: ## Open Mailpit (all emails sent in development)
	@$(OPEN) "http://localhost:8025"

server: ## Run the web server in the foreground (Ctrl+C to stop) - local mode
	$(PHP) -S 127.0.0.1:$(PORT) -t public public/index.php

worker: ## Run the queue worker in the foreground (emails, scheduled jobs)
	$(CONSOLE) messenger:consume async scheduler_default -vv

logs: ## Follow application logs
ifeq ($(MODE),docker)
	$(DOCKER_COMP) logs -f --tail=100
else
	@bin/dev logs
endif

## Docker
services: ## Start only PostgreSQL, Redis and Mailpit
	$(DOCKER_COMP) up -d --wait database redis mailer

up: ## Start the whole stack in Docker (http://localhost:8080)
	$(DOCKER_COMP) up -d --build --wait

build: ## Rebuild the Docker images from scratch
	$(DOCKER_COMP) build --pull --no-cache

ps: ## Docker containers and their state
	$(DOCKER_COMP) ps

docker-logs: ## Follow logs of the Docker containers (s=php to pick one)
	$(DOCKER_COMP) logs -f --tail=100 $(s)

sh: ## Shell in the PHP container
	$(DOCKER_COMP) exec php bash

psql: ## PostgreSQL console
	$(DOCKER_COMP) exec database sh -c 'psql -U "$$POSTGRES_USER" "$$POSTGRES_DB"'

redis-cli: ## Redis console
	$(DOCKER_COMP) exec redis redis-cli

## Dependencies
install: ## Install PHP and JavaScript dependencies
	$(COMPOSER) install
	$(CONSOLE) importmap:install

update: ## Update dependencies within the allowed versions
	$(COMPOSER) update
	$(CONSOLE) importmap:update

outdated: ## Show outdated dependencies
	$(COMPOSER) outdated --direct
	$(CONSOLE) importmap:outdated

audit: ## Check dependencies for known vulnerabilities
	$(COMPOSER) audit
	$(CONSOLE) importmap:audit

## Database
db: ## Create the database and apply migrations
	$(CONSOLE) doctrine:database:create --if-not-exists
	$(CONSOLE) doctrine:migrations:migrate -n --allow-no-migration

migrate: ## Apply new migrations
	$(CONSOLE) doctrine:migrations:migrate -n --allow-no-migration

migration: ## Create a migration from entity changes
	$(CONSOLE) make:migration

migrations-status: ## List migrations and their status
	$(CONSOLE) doctrine:migrations:status

rollback: ## Revert the last migration
	$(CONSOLE) doctrine:migrations:migrate prev -n

fixtures: ## Add demo data (users, bookings, reviews)
	$(CONSOLE) doctrine:fixtures:load --append -n

demo-data: # Demo data only into an empty database (so `make init` can be run again)
	@if $(CONSOLE) dbal:run-sql "SELECT 1 FROM users LIMIT 1" | grep -q "empty result set"; then \
		$(MAKE) --no-print-directory fixtures; \
	else \
		echo "  demo data: the database already has users, skipped"; \
	fi

reset: ## Drop the database and recreate it with demo data
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(MAKE) --no-print-directory db fixtures

sql: ## Run an SQL query: make sql q="select count(*) from orders"
	$(CONSOLE) dbal:run-sql "$(q)"

schema-check: ## Check that the database matches the entities
	$(CONSOLE) doctrine:schema:validate

backup: ## Dump the database to var/backups/ (PROD_MODE=1 for the production stack)
	@mkdir -p var/backups
	$(if $(PROD_MODE),$(PROD_COMP),$(DOCKER_COMP)) exec -T database sh -c 'pg_dump -U "$$POSTGRES_USER" -Fc "$$POSTGRES_DB"' > var/backups/choven-$$(date +%Y%m%d-%H%M%S).dump
	@echo "  saved: var/backups/$$(ls -1t var/backups | head -1)"

restore: ## Restore a dump: make restore file=var/backups/choven-....dump (PROD_MODE=1 for production)
	@test -n "$(file)" || { echo "usage: make restore file=var/backups/...dump"; exit 1; }
	$(if $(PROD_MODE),$(PROD_COMP),$(DOCKER_COMP)) exec -T database sh -c 'pg_restore -U "$$POSTGRES_USER" -d "$$POSTGRES_DB" --clean --if-exists --no-owner' < $(file)

## Users
admin: ## Create an administrator: make admin email=you@example.com [name="Name"]
	@test -n "$(email)" || { echo "usage: make admin email=you@example.com"; exit 1; }
	$(CONSOLE) app:user:create $(email) --admin $(if $(name),--name="$(name)")

user: ## Create a regular user: make user email=client@example.com
	@test -n "$(email)" || { echo "usage: make user email=client@example.com"; exit 1; }
	$(CONSOLE) app:user:create $(email) $(if $(name),--name="$(name)")

promote: ## Give admin rights to an existing user: make promote email=...
	$(CONSOLE) app:user:role $(email) admin

demote: ## Remove admin rights: make demote email=...
	$(CONSOLE) app:user:role $(email) user

## Background jobs and emails
jobs: ## Run scheduled jobs now (job=reminders|complete|unpaid|waitlist|purge, all by default)
	$(CONSOLE) app:jobs:run $(job)

telegram-poll: ## Receive messages to the Telegram bot without a webhook (development)
	$(CONSOLE) app:telegram:poll

bot-webhook: ## Register the bot webhook (needs a public HTTPS DEFAULT_URI): make bot-webhook bot=telegram|viber
	@test -n "$(bot)" || { echo "usage: make bot-webhook bot=telegram|viber"; exit 1; }
	$(CONSOLE) app:bot:webhook $(bot)

failed: ## Emails that could not be sent
	$(CONSOLE) messenger:failed:show

retry-failed: ## Send failed emails again
	$(CONSOLE) messenger:failed:retry --force

schedule: ## Show the schedule of background jobs
	$(CONSOLE) debug:scheduler

## Cache and assets
cache: ## Clear the cache
	$(CONSOLE) cache:clear

cache-pools: ## Clear application caches (settings, rate limits)
	$(CONSOLE) cache:pool:clear cache.app cache.rate_limiter

assets: ## Compile assets into public/assets (production build)
	$(CONSOLE) asset-map:compile

assets-clean: ## Remove compiled assets (development serves them on the fly)
	rm -rf public/assets

images: ## Build responsive AVIF/WebP variants of all photos (assets/images and uploads)
	$(CONSOLE) app:images:optimize

routes: ## List all routes
	$(CONSOLE) debug:router

translations: ## Show missing translations (locale=ru|be|en)
	$(CONSOLE) debug:translation $(or $(locale),en) --only-missing

## Quality
test: ## Run all tests (the test database is recreated)
	$(PHP) vendor/bin/phpunit

test-unit: ## Unit tests (no database)
	$(PHP) vendor/bin/phpunit --testsuite unit

test-integration: ## Integration tests
	$(PHP) vendor/bin/phpunit --testsuite integration

test-functional: ## Functional (HTTP) tests
	$(PHP) vendor/bin/phpunit --testsuite functional

test-filter: ## Run matching tests: make test-filter t=Booking
	$(call php_env,SKIP_DB_RESET=1) vendor/bin/phpunit --filter "$(t)"

coverage: ## Code coverage report in var/coverage (needs Xdebug or PCOV)
	$(call php_env,XDEBUG_MODE=coverage) vendor/bin/phpunit --coverage-html var/coverage

phpstan: ## Static analysis (level 8)
	$(CONSOLE) cache:warmup -q
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G

cs: ## Check code style
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Fix code style
	$(PHP) vendor/bin/php-cs-fixer fix

lint: ## Lint container, Twig, YAML, Doctrine mapping and composer.json
	$(CONSOLE) lint:container
	$(CONSOLE) lint:twig templates
	$(CONSOLE) lint:yaml config translations --parse-tags
	$(CONSOLE) doctrine:schema:validate --skip-sync
	$(COMPOSER) validate --strict

quality: lint cs phpstan test ## Everything CI checks

## Production (compose.prod.yaml + .env.prod.local)
prod-env: ## Create .env.prod.local from the example (fill in the secrets afterwards)
	@test -f .env.prod.local && echo ".env.prod.local already exists" || { cp .env.prod.example .env.prod.local; echo "created .env.prod.local - fill in APP_SECRET, POSTGRES_PASSWORD, MAILER_DSN, DEFAULT_URI, TRUSTED_HOSTS"; }

prod-build: ## Build production images
	$(PROD_COMP) build --pull

prod-up: ## Start or update production (migrations run automatically, old php/nginx containers are removed)
	$(PROD_COMP) up -d --wait --remove-orphans

prod-down: ## Stop production
	$(PROD_COMP) down

prod-ps: ## Production containers
	$(PROD_COMP) ps

prod-logs: ## Follow production logs (s=app|worker to pick a container)
	$(PROD_COMP) logs -f --tail=200 $(s)

prod-sh: ## Shell in the production app container
	$(PROD_COMP) exec app bash

prod-console: ## Run a console command in production: make prod-console c="app:jobs:run"
	$(PROD_COMP) exec app bin/console $(c)

prod-admin: ## Create an administrator in production: make prod-admin email=...
	$(PROD_COMP) exec app bin/console app:user:create $(email) --admin

prod-deploy: prod-build prod-up ## Rebuild and restart production after `git pull`

## Housekeeping
clean: ## Remove cache, logs, compiled assets and image variants
	rm -rf var/cache/* var/log/* public/assets public/media public/uploads/_v

git-log: ## Commit history as a graph
	git log --graph --oneline --decorate -30
