# One command surface for both environments:
#   - Deploy (Docker): make up / down / logs / migrate / shell / smoke / backup …
#   - Host (Herd):     make test / pint / dev / assets / serve / crawl …
# `make` with no target prints this list; every target carries a `##` doc.
#
# No `make` on the machine? ./run.sh is the exact same set of commands:
#   ./run.sh up   ·   ./run.sh test   ·   ./run.sh artisan migrate --force

SHELL := /bin/bash
.SHELLFLAGS := -eu -o pipefail -c
.ONESHELL:
.DEFAULT_GOAL := help

COMPOSE ?= docker compose
APP     := $(COMPOSE) exec app
# One-shot container for the commands that must not compete with php-fpm for the
# app service's memory budget. Its dependencies come up with it, because a
# migration or a seed has to reach MySQL.
RUN     := $(COMPOSE) run --rm -T app

.PHONY: help env validate up build down destroy ps logs logs-web logs-redis logs-mysql db restart \
	shell artisan supervisor migrate seed fresh cache smoke backup restore realtime \
	test pint check dev assets serve crawl

help: ## Show this help
	@grep -hE '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

## Deploy (Docker)

env: ## Create .env from .env.example and generate APP_KEY (idempotent)
	./run.sh env

validate: ## Validate docker-compose.yml without starting anything
	$(COMPOSE) config -q
	echo "docker-compose.yml is valid"

up: env ## Build and start the stack (web, app, mysql, redis)
	$(COMPOSE) up -d --build

build: ## Rebuild images without starting
	$(COMPOSE) build

down: ## Stop and remove containers (keeps content, uploads, redis data)
	$(COMPOSE) down

destroy: ## Stop and remove containers AND volumes — deletes content (asked first, see: make backup)
	./run.sh destroy

ps: ## Show container status
	$(COMPOSE) ps

logs: ## Tail the app container (php-fpm, queue, scheduler)
	$(COMPOSE) logs -f app

logs-web: ## Tail the web (nginx) container
	$(COMPOSE) logs -f web

logs-redis: ## Tail the redis container
	$(COMPOSE) logs -f redis

logs-mysql: ## Tail the MySQL container (its bootstrap log is here)
	$(COMPOSE) logs -f mysql

db: ## A mysql prompt inside the database container, as the app's own user
	$(COMPOSE) exec mysql sh -c 'exec mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'

restart: ## Restart the app container (opcache and config are reloaded)
	$(COMPOSE) restart app

shell: ## Open a shell in the app container
	$(APP) sh

artisan: ## Run artisan in the app container: make artisan c="migrate --force"
	if [[ -z "$(c)" ]]; then
	    echo 'usage: make artisan c="artisan arguments"' >&2
	    exit 1
	fi
	$(APP) php artisan $(c)

supervisor: ## Show supervisord program status (php-fpm, queue, scheduler)
	$(APP) supervisorctl status

migrate: ## Run pending migrations (one-shot app container, verbose)
	$(RUN) php artisan migrate --force -v

seed: ## Seed without wiping anything (one-shot app container)
	$(RUN) php artisan db:seed --force

fresh: ## Drop all tables, re-migrate and re-seed — destroys the content in MySQL
	./run.sh fresh

cache: ## Rebuild the production caches inside the running container
	$(APP) sh -c 'php artisan config:cache && php artisan route:cache && php artisan event:cache && php artisan view:cache && php artisan filament:optimize'

smoke: ## Check the deployed site answers: /up, home, admin, sitemap
	./run.sh smoke

backup: ## Dump the live database to a gzipped SQL file: make backup to=ruma.sql.gz
	@set -eu; \
	to="$${to:-$$(date +ruma-backup-%%Y%%m%%d-%%H%%M).sql.gz}"; \
	$(COMPOSE) exec -T mysql sh -c 'exec mysqldump -uroot -p"$$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers --databases "$$MYSQL_DATABASE"' | gzip -9 > "$$to"; \
	ls -lh "$$to"

restore: ## Load a dump into the live database: make restore from=ruma-backup.sql.gz
	@if [[ -z "$(from)" ]]; then echo 'usage: make restore from=<backup.sql[.gz]>' >&2; exit 1; fi
	$(COMPOSE) stop app
	@case "$(from)" in \
	    *.gz) gunzip -c "$(from)";; \
	    *) cat "$(from)";; \
	esac | $(COMPOSE) exec -T mysql sh -c 'exec mysql -uroot -p"$$MYSQL_ROOT_PASSWORD"'
	$(COMPOSE) up -d app
	@echo "restored $(from) — run 'make migrate' if the code has newer migrations"

realtime: ## Start the stack plus the Reverb chat server (needs REVERB_* keys + rebuild)
	$(COMPOSE) --profile realtime up -d --build

## Host (Herd, PHP 8.4)

test: ## Run the test suite
	php artisan test --compact

pint: ## Format dirty PHP files
	./vendor/bin/pint --dirty --format agent

check: ## Everything CI would check on this working copy
	./vendor/bin/pint --test
	php artisan test --compact

dev: ## Vite dev server
	npm run dev

assets: ## Production asset build
	npm run build

serve: ## Full local dev stack (server, queue, logs, Vite, Reverb)
	php artisan dev

crawl: ## Crawl every page in every language and report dead links
	php crawl.php $${base:-http://127.0.0.1:8123}
