COMPOSE := docker compose
PHP     := $(COMPOSE) exec -T php
CONSOLE := $(PHP) php bin/console

.DEFAULT_GOAL := help
.PHONY: help up down build sh logs composer install migrate diff schema fixtures jwt test stan cs cs-fix lint check reset

help: ## Show available targets
	@grep -hE '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

up: ## Build (if needed) and start the stack
	$(COMPOSE) up -d --build
	@echo "API: http://localhost:$${HTTP_PORT:-8080}/api"

down: ## Stop the stack (keeps the database volume)
	$(COMPOSE) down

build: ## Rebuild images from scratch
	$(COMPOSE) build --no-cache

sh: ## Interactive shell in the PHP container
	$(COMPOSE) exec php bash

logs: ## Follow container logs
	$(COMPOSE) logs -f

composer: ## Run composer, e.g. make composer c="require foo/bar"
	$(PHP) composer $(c)

install: ## Install PHP dependencies
	$(PHP) composer install

migrate: ## Apply pending migrations
	$(CONSOLE) doctrine:migrations:migrate --no-interaction

diff: ## Generate a migration from the entity mapping
	$(CONSOLE) doctrine:migrations:diff

schema: ## Verify the mapping matches the database
	$(CONSOLE) doctrine:schema:validate

fixtures: ## Load development fixtures
	$(CONSOLE) doctrine:fixtures:load --no-interaction

jwt: ## Generate the JWT key pairs for dev and test
	$(CONSOLE) lexik:jwt:generate-keypair --skip-if-exists
	$(CONSOLE) lexik:jwt:generate-keypair --skip-if-exists --env=test

test: ## Run the PHPUnit suite
	$(PHP) vendor/bin/phpunit

stan: ## Static analysis
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G

cs: ## Check coding standards (no changes)
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Apply coding standards
	$(PHP) vendor/bin/php-cs-fixer fix

lint: ## Lint container, YAML and Twig
	$(CONSOLE) lint:container
	$(CONSOLE) lint:yaml config
	$(CONSOLE) lint:twig templates

check: cs stan lint test ## Full quality gate

reset: ## Drop the stack and its data, then start clean
	$(COMPOSE) down -v
	$(MAKE) up
