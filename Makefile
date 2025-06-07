.PHONY: help install docker db server clear stop build dev migrations

.DEFAULT_GOAL := help

help:
	@echo "Choven.by - Water rafting management system"
	@echo ""
	@echo "Available commands:"
	@echo "  make install     - Install project dependencies"
	@echo "  make docker      - Start Docker containers"
	@echo "  make db          - Create and migrate the database"
	@echo "  make server      - Start Symfony development server"
	@echo "  make clear       - Clear cache"
	@echo "  make migrations  - Create migrations after entity changes"
	@echo "  make run         - Start the entire project (docker + db + server)"
	@echo "  make dev         - Install and start the project for development"
	@echo "  make stop        - Stop all processes"
	@echo "  make build       - Build the project for production"

install:
	@composer install

docker:
	@docker-compose up -d --build

db:
	@php bin/console doctrine:database:create --if-not-exists
	@php bin/console doctrine:migrations:migrate --no-interaction

migrations:
	@php bin/console make:migration

server:
	@symfony serve -d

clear:
	@php bin/console cache:clear

build:
	composer install --no-dev --optimize-autoloader
	php bin/console cache:clear --env=prod
	npm run build

dev: install docker server

run: docker db server

stop:
	@symfony server:stop
	@docker-compose down
