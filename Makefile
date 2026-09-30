SHELL := /bin/sh
.DEFAULT_GOAL := help
.NOTPARALLEL:
export LOCAL_UID := $(shell id -u)
export LOCAL_GID := $(shell id -g)
COMPOSE := docker compose
E2E_COMPOSE := $(COMPOSE) -f compose.yaml -f compose.e2e.yaml --profile e2e
RUN := $(COMPOSE) run --rm --no-deps app
ARGS ?=
SERVICE ?=
CONFIRM ?=
TEST_PROCESSES ?= 4

.PHONY: help env local-guard setup seed fresh up down stop restart build deps hooks hook-check logs ps doctor test test-parallel test-setup generator-smoke check assets artisan composer npm shell db config backup restore-drill e2e init-project

help: ## Lista komend
	@awk 'BEGIN {FS = ":.*## "} /^[a-z-]+:.*## / {printf "  make %-12s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

env: ## Utwórz lokalny .env bez nadpisywania istniejącej konfiguracji
	@test -f .env || (umask 077; cp .env.example .env)

local-guard: env
	@grep -qx 'APP_ENV=local' .env || { echo 'Ta komenda wymaga APP_ENV=local w .env'; exit 1; }
	@grep -qx 'DB_HOST=postgres' .env || { echo 'Ta komenda wymaga lokalnego DB_HOST=postgres'; exit 1; }

setup: local-guard ## Pierwsza instalacja: obraz, zależności, klucz, migracje, dane demo, start
	$(COMPOSE) build app
	$(MAKE) deps
	$(COMPOSE) up -d --wait postgres redis mailpit
	$(RUN) sh -ec 'php artisan config:clear; if ! grep -Eq "^APP_KEY=.+" .env; then php artisan key:generate --no-interaction; fi; php artisan migrate --no-interaction; if [ ! -L public/storage ] && [ ! -e public/storage ]; then php artisan storage:link --no-interaction; fi; php artisan db:seed --no-interaction'
	$(MAKE) up

seed: local-guard ## Dane demo (idempotentnie, tylko APP_ENV=local)
	$(COMPOSE) up -d --wait postgres redis
	$(RUN) php artisan db:seed --no-interaction

fresh: local-guard ## Usuń lokalną bazę i odtwórz ją z danymi demo (pyta o potwierdzenie; CONFIRM=1 pomija)
	@echo "make fresh usunie WSZYSTKIE tabele i dane lokalnej bazy PostgreSQL projektu '$$(grep -E '^COMPOSE_PROJECT_NAME=' .env | cut -d= -f2)' (konta, treści, media w bazie, audit log) i wgra dane demo."
	@if [ "$(CONFIRM)" != "1" ]; then printf 'Kontynuować? [y/N] '; read answer; case "$$answer" in y|Y|yes|tak) ;; *) echo 'Przerwano, baza bez zmian.'; exit 1;; esac; fi
	$(COMPOSE) up -d --wait postgres redis
	$(RUN) php artisan migrate:fresh --seed --no-interaction

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

generator-smoke: ## Smoke generatora CRUD w tymczasowej kopii repo (bez zmian w drzewie roboczym)
	$(RUN) sh scripts/generator-smoke.sh

check: ## Pełne istniejące kontrole jakości projektu
	$(RUN) composer ci:check

assets: ## Zbuduj assety klienta i SSR
	$(COMPOSE) stop vite
	@rm -f public/hot
	$(RUN) npm run build:ssr

e2e: env ## E2E Playwright na buildzie produkcyjnym + SSR, APP_ENV=e2e (zatrzymuje Vite); ARGS='--grep nazwa'
	$(COMPOSE) stop vite
	@rm -f public/hot
	$(RUN) npm run build:ssr
	$(E2E_COMPOSE) up -d --wait --wait-timeout 180 web queue ssr playwright
	@E2E_PASSWORD="$${E2E_PASSWORD:-$$(od -An -tx1 -N18 /dev/urandom | tr -d ' \n')}"; export E2E_PASSWORD; \
	$(COMPOSE) run --rm --no-deps -e E2E_PASSWORD app php artisan app:e2e-prepare --client-host=playwright --no-interaction \
	&& $(E2E_COMPOSE) exec -T -e E2E_PASSWORD playwright npx playwright test $(ARGS); \
	status=$$?; $(E2E_COMPOSE) rm --stop --force ssr playwright; \
	$(COMPOSE) up -d --wait --wait-timeout 180 web queue; \
	echo 'Usługi wróciły do APP_ENV=local; serwer Vite dev pozostaje zatrzymany (`make up`).'; exit $$status

init-project: env ## Start nowego projektu: marka, języki, admin, demo; ARGS='--dry-run' (local)
	$(COMPOSE) up -d --wait postgres redis
	$(RUN) php artisan app:init-project $(ARGS)

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

backup: env ## Lokalny backup DB + storage do ./.backups (manifest SHA-256, rotacja BACKUP_KEEP)
	@mkdir -p .backups
	$(COMPOSE) --profile ops run --rm backup /opt/backup/backup.sh

restore-drill: env ## Próba odtworzenia ostatniego backupu do tymczasowej bazy (weryfikacja + czas)
	$(COMPOSE) --profile ops run --rm backup /opt/backup/restore.sh --drill $(ARGS)
