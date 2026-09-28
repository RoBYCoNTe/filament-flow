# The targets here need PHP on the machine that runs them. When it lives in the container of a
# host application instead, `scripts/check.sh` decides where to run each check and calls the
# scripts of composer.json.

.PHONY: help lint lint-fix phpstan test check check-all

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

lint: ## Check code style (Pint)
	vendor/bin/pint --test

lint-fix: ## Fix code style automatically (Pint)
	vendor/bin/pint

phpstan: ## Run static analysis (PHPStan)
	vendor/bin/phpstan analyse

test: ## Run tests
	vendor/bin/phpunit

check: lint phpstan ## Run lint + static analysis

check-all: lint phpstan test ## Run all checks (lint + static analysis + tests)
