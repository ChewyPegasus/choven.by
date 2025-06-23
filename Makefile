.PHONY: help install docker db server clear stop build dev migrations kafka-start kafka-stop kafka-status docker-php

.DEFAULT_GOAL := help

help:
	@echo "Choven.by - Water rafting management system"
	@echo ""
	@echo "Available commands:"
	@echo "  make install      - Install project dependencies"
	@echo "  make docker       - Start Docker containers"
	@echo "  make db           - Create and migrate the database"
	@echo "  make server       - Start Symfony development server"
	@echo "  make clear        - Clear cache"
	@echo "  make migrations   - Create migrations after entity changes"
	@echo "  make run          - Start the entire project (docker + db + server)"
	@echo "  make dev          - Install and start the project for development"
	@echo "  make stop         - Stop all processes"
	@echo "  make build        - Build the project for production"
	@echo "  make kafka-start  - Start Kafka order consumer (in background)"
	@echo "  make kafka-stop   - Stop Kafka order consumer"
	@echo "  make kafka-status - Check if Kafka consumer is running"
	@echo "  docker-php        - Enter the php container"

install:
	@echo "Installing dependencies inside the PHP container..."
	@docker-compose exec -u www-data php composer install

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

build:
	composer install --no-dev --optimize-autoloader
	php bin/console cache:clear --env=prod
	npm run build

dev: docker install db clear server

run: docker install db clear

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