SHELL := /bin/sh
.DEFAULT_GOAL := help
.NOTPARALLEL:
export LOCAL_UID := $(shell id -u)
export LOCAL_GID := $(shell id -g)
COMPOSE := docker compose
RUN := $(COMPOSE) run --rm --no-deps app
ARGS ?=
SERVICE ?=
TEST_PROCESSES ?= 4

.PHONY: help env setup up down stop restart build deps hooks hook-check logs ps doctor test test-parallel test-setup check assets artisan composer npm shell db config

help: ## Lista komend
	@awk 'BEGIN {FS = ":.*## "} /^[a-z-]+:.*## / {printf "  make %-12s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

env: ## Utwórz lokalny .env bez nadpisywania istniejącej konfiguracji
	@test -f .env || (umask 077; cp .env.example .env)

setup: env ## Pierwsza instalacja: obraz, zależności, klucz, migracje, start
	@grep -qx 'APP_ENV=local' .env || { echo 'Setup wymaga APP_ENV=local w .env'; exit 1; }
	@grep -qx 'DB_HOST=postgres' .env || { echo 'Setup wymaga lokalnego DB_HOST=postgres'; exit 1; }
	$(COMPOSE) build app
	$(MAKE) deps
	$(COMPOSE) up -d --wait postgres redis mailpit
	$(RUN) sh -ec 'php artisan config:clear; if ! grep -Eq "^APP_KEY=.+" .env; then php artisan key:generate --no-interaction; fi; php artisan migrate --no-interaction; if [ ! -L public/storage ] && [ ! -e public/storage ]; then php artisan storage:link --no-interaction; fi'
	$(MAKE) up

deps: env ## Instaluj dokładnie zależności z lockfile (również po git pull)
	$(COMPOSE) stop vite queue scheduler web app
	$(RUN) composer install --no-interaction --prefer-dist
	$(RUN) npm ci

hooks: env ## Włącz wersjonowane hooki Git; wymagany jest hostowy gitleaks
	@test -x .githooks/pre-commit && test -x .githooks/commit-msg
	@git config core.hooksPath .githooks
	@command -v gitleaks >/dev/null || { echo 'Zainstaluj gitleaks, aby lokalny pre-commit mógł skanować sekrety.'; exit 1; }

hook-check: env ## Zweryfikuj instalację hooków i konfigurację Lefthook
	@test "$$(git config --get core.hooksPath)" = .githooks
	@if $(RUN) sh -ec 'message_file=$$(mktemp); trap "rm -f $$message_file" EXIT; printf "%s\\n" "niepoprawna wiadomosc" > "$$message_file"; npx lefthook run commit-msg "$$message_file"' >/dev/null 2>&1; then echo 'commit-msg powinien odrzucić niepoprawną wiadomość'; exit 1; fi

up: env ## Uruchom wszystkie usługi i poczekaj na gotowość
	$(COMPOSE) up -d --wait --wait-timeout 180

down: ## Usuń kontenery i sieć, zachowaj dane oraz zależności
	$(COMPOSE) down --remove-orphans
	@rm -f public/hot

stop: ## Zatrzymaj usługi, zachowaj kontenery i dane
	$(COMPOSE) stop
	@rm -f public/hot

restart: ## Odtwórz usługi po zmianie konfiguracji lub kodu workera
	$(MAKE) down
	$(MAKE) up

build: env ## Przebuduj obraz developerski z aktualnymi obrazami bazowymi
	$(COMPOSE) build --pull app

logs: ## Logi wszystkich usług lub SERVICE=queue
	$(COMPOSE) logs --tail=100 -f $(SERVICE)

ps: ## Stan usług
	$(COMPOSE) ps -a

doctor: ## Sprawdź konfigurację, runtime, DB, Redis, HTTP i Vite
	$(COMPOSE) config --quiet
	$(COMPOSE) exec --user www-data app node scripts/dev-doctor.mjs

test: ## Testy Pest; opcjonalnie ARGS='--filter=nazwa'
	$(RUN) php artisan test --compact $(ARGS)

test-parallel: ## Równoległe testy Pest; TEST_PROCESSES=4 domyślnie
	$(RUN) php artisan test --compact --parallel --processes=$(TEST_PROCESSES) $(ARGS)

test-setup: ## Testy bootstrappingu i ochrony konfiguracji
	$(RUN) node --test scripts/dev-environment.test.mjs

check: ## Pełne istniejące kontrole jakości projektu
	$(RUN) composer ci:check

assets: ## Zbuduj assety klienta i SSR
	$(COMPOSE) stop vite
	@rm -f public/hot
	$(RUN) npm run build:ssr

artisan: ## Artisan, np. ARGS='migrate:status'
	$(RUN) php artisan $(ARGS)

composer: ## Composer, np. ARGS='show --direct'
	$(RUN) composer $(ARGS)

npm: ## npm, np. ARGS='run types:check'
	$(RUN) npm $(ARGS)

shell: ## Bash w kontenerze jako użytkownik aplikacji
	$(RUN) bash

db: ## Konsola PostgreSQL (bez publikowania portu DB)
	$(COMPOSE) exec postgres sh -c 'exec psql -U "$$POSTGRES_USER" -d "$$POSTGRES_DB"'

config: env ## Sprawdź poprawność Compose bez wypisywania sekretów
	$(COMPOSE) config --quiet
