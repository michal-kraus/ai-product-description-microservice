.DEFAULT_GOAL := help
.PHONY: help up down restart logs worker test coverage stan lint cs cs-fix check cache-clear install

help: ## Display available commands
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

install: ## Install Composer dependencies
	composer install --prefer-dist --optimize-autoloader

up: ## Start Docker containers in the background (Redis, Ollama, App, Worker)
	docker compose up -d

down: ## Stop and remove Docker containers
	docker compose down

restart: down up ## Restart the Docker environment

logs: ## Follow Docker container logs
	docker compose logs -f

worker: ## Start the Symfony Messenger async consumer
	php bin/console messenger:consume async -vv

test: ## Run the PHPUnit test suite
	bin/phpunit

coverage: ## Run PHPUnit tests with code coverage report (PCOV)
	vendor/bin/phpunit --coverage-text

stan: ## Run PHPStan static analysis (Level 8)
	vendor/bin/phpstan analyse

lint: ## Lint YAML files and the Symfony Dependency Injection container
	php bin/console lint:yaml config --parse-tags
	php bin/console lint:container

cs: ## Check coding standards (PHP-CS-Fixer, dry-run)
	vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Fix coding standards automatically (PHP-CS-Fixer)
	vendor/bin/php-cs-fixer fix

check: lint cs stan coverage ## Run the full verification suite (lint + cs + stan + coverage)

cache-clear: ## Clear Symfony cache
	php bin/console cache:clear
