.PHONY: help lint lint-fix phpstan test check check-all ddev-lint ddev-lint-fix ddev-phpstan ddev-check ddev-check-all

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

# --- DDEV targets (run from host with aries DDEV) ---

ARIES_DIR := ../studio-capolupo/aries
DDEV := cd $(ARIES_DIR) && ddev exec
FF := packages/robyconte/filament-flow

RUN_IN_FF := $(DDEV) bash -c 'cd $(FF) &&

ddev-lint: ## [DDEV] Check code style via aries DDEV
	$(RUN_IN_FF) vendor/bin/pint --test'

ddev-lint-fix: ## [DDEV] Fix code style via aries DDEV
	$(RUN_IN_FF) vendor/bin/pint'

ddev-phpstan: ## [DDEV] Run static analysis via aries DDEV
	$(RUN_IN_FF) vendor/bin/phpstan analyse'

ddev-test: ## [DDEV] Run tests via aries DDEV
	$(RUN_IN_FF) vendor/bin/phpunit'

ddev-check: ddev-lint ddev-phpstan ## [DDEV] Run lint + static analysis via aries DDEV

ddev-check-all: ddev-lint ddev-phpstan ddev-test ## [DDEV] Run all checks via aries DDEV
