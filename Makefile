#!/usr/bin/make
# Makefile readme (ru): <http://linux.yaroslavl.ru/docs/prog/gnu_make_3-79_russian_manual.html>
# Makefile readme (en): <https://www.gnu.org/software/make/manual/html_node/index.html#SEC_Contents>

SHELL = /bin/sh
APP_CONTAINER_NAME := app

docker_bin := $(shell command -v docker 2> /dev/null)
docker_compose_bin := $(shell command -v docker-compose 2> /dev/null)

.PHONY : help test \
         up down restart shell install
.DEFAULT_GOAL := help

# This will output the help for each task. thanks to https://marmelab.com/blog/2016/02/29/auto-documented-makefile.html
help: ## Show this help
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

# --- [ Development tasks ] -------------------------------------------------------------------------------------------

---------------: ## ---------------

up: ## Start all containers (in background) for development
    ifeq ($(OS), Windows_NT)
	    sudo sysctl -w vm.max_map_count=262144
    endif
	$(docker_compose_bin) up -d

down: ## Stop all started for development containers
	$(docker_compose_bin) down

restart: up ## Restart all started for development containers
	$(docker_compose_bin) restart

shell: up ## Start shell into application container
	$(docker_compose_bin) exec "$(APP_CONTAINER_NAME)" /bin/sh

install: up ## Install application dependencies into application container
	$(docker_compose_bin) exec "$(APP_CONTAINER_NAME)" composer install --no-interaction --ansi

test: up ## Execute application tests
	$(docker_compose_bin) exec "$(APP_CONTAINER_NAME)" ./vendor/bin/phpstan analyze --memory-limit=4000M
	$(docker_compose_bin) exec "$(APP_CONTAINER_NAME)" ./vendor/bin/phpunit --testdox --stop-on-failure

test-coverage: up ## Execute application tests and generate report
	$(docker_compose_bin) exec "$(APP_CONTAINER_NAME)" ./vendor/bin/phpstan analyze
	$(docker_compose_bin) exec "$(APP_CONTAINER_NAME)" ./vendor/bin/phpunit  --coverage-html build/coverage-report

test-filter:
	$(docker_compose_bin) exec "$(APP_CONTAINER_NAME)" ./vendor/bin/phpunit --filter=$(filter) --testdox
