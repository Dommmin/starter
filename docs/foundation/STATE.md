# Stan bieżący startera

Stan na 2026-10-12, gałąź `chore/foundation-hygiene-wave2` (develop + scalony main + generyczne zmiany z `feat/sh-showcase`). Źródło prawdy to manifesty, lockfile i kod; ten dokument je streszcza i trzeba go aktualizować przy zmianie stacku lub modułów. Plan i decyzje: [master plan](00-master-plan.md), [plan po przeglądzie](15-review-plan-2026-10-12.md).

## Stack (wersje zainstalowane z lockfile)

| Warstwa | Wersje |
| --- | --- |
| Runtime lokalny | Docker Compose + `Makefile`; PHP 8.5 FPM (bookworm), Node 24, PostgreSQL 18, Redis 8.2, Nginx 1.28, Mailpit 1.27, ClamAV 1.5.4 (opcjonalny), Playwright 1.63 |
| Backend | Laravel 13.31, Inertia Laravel 3.3, Fortify 1.39 (2FA, passkeys), Horizon 5.50, Wayfinder 0.1.21, spatie/laravel-data 4.23, Intervention Image 3.11, tiptap-php 2.2; `composer.json` wymaga PHP ^8.3 |
| Frontend | React 19.3, @inertiajs/react 3.7, TypeScript 5.9, Vite 8.2 (vite-plus 0.3), Tailwind CSS 4.3, Radix (pojedyncze pakiety), TipTap 3.31, lucide-react 0.475 |
| Jakość | Pest 5.1, Larastan 3.11, Pint, vitest 4.1 (przez vite-plus), Playwright + axe, Lefthook, commitlint, gitleaks, Boost 2.8 |
| Deploy | Deployer 8.0, artefakt z manifestem i SHA-256, automatyczny rollback w oknie deployu (ADR-022) |

## Moduły w kodzie

- Treści: strony (`Page`, tłumaczenia, przekierowania slugów), artykuły (`Article`, tłumaczenia, przekierowania), FAQ, sekcje strony głównej (`HomeSection`: m.in. hero ze statystykami, portfolio, zespół, cennik, opinie, FAQ, najnowsze artykuły, CTA, kontakt), menu (`MenuItem`), ustawienia strony (`SiteSetting`: marka, kontakt, NIP, godziny otwarcia, link do mapy, akcent, preset typografii, domyślny motyw, favicon, token Search Console), szablony stron prawnych (`LegalPageSeeder`, wszystkie środowiska).
- Kontakt: konfigurowalne formularze (`ContactForm`: `contact`, `quote`, pola text/textarea/select/checkbox), do 5 odbiorców, auto-odpowiedź, podpisany webhook z `.env`, kolejka wysyłki z ponowieniami, lista z eksportem CSV, retencja (`contact:prune`).
- Media: DAM z kwarantanną, opcjonalny skan ClamAV (`MEDIA_SCANNER_ENABLED`), warianty obrazów.
- Konta: Fortify z 2FA i passkeys, role statyczne (`UserRole`: admin, editor), zarządzanie użytkownikami, lokalny dev-login tylko przy `APP_ENV=local`.
- Panel: dashboard z wykresami i stroną System (przełączniki „coming soon” i `noindex`, checklista startu, testowy e-mail), ResourceTable/ResourceForm, bulk actions, filtry, ⌘K, audit log, generator `make:admin-resource`.
- Publicznie: SSR, i18n PL/EN/DE z prefiksem języka, sitemap, robots, cookie consent (Plausible tylko po zgodzie), `/_design-system`.
- Operacje: `/health/ready`, heartbeat schedulera, kontrola świeżości backupu, alerty `ops.*` mailem.

Trasy według plików w `routes/`: `admin`, `auth`, `content`, `design-system`, `dev-login`, `front`, `health`, `seo`, `settings`, `web`. Pełna lista: `make artisan ARGS='route:list --except-vendor'`.

## Komendy

`make setup`, `make up`, `make doctor`, `make test`, `make test-pgsql`, `make check`, `make e2e`, `make generator-smoke`, `make init-project`, `make backup`, `make restore-drill`; wrappery `make artisan/composer/npm ARGS='…'`. Skrypty: `composer lint`, `composer types:check`, `npm run check` (format, lint, vitest), `npm run types:check`, `npm run check:ui-contract`, `npm run check:budget`. Testy konfiguracji agentów: `python3 -m unittest discover -s tests/ai`.

CI (`.github/workflows/ci.yml`): `commit-convention`, `secrets`, `quality`, `tests-pgsql`, `generator-smoke`, `e2e`; job `audit` dochodzi z fali 1. `deploy.yml` buduje artefakt i wdraża po zatwierdzeniu człowieka.

## Co jest `verified`

- Komponenty DS: statusy `implemented`/`verified` z dowodem prowadzi [rejestr komponentów](13-component-registry.md); `verified` oznacza test i akceptację wizualną właściciela.
- Kontrakt UI (ADR-019): `ui-contract` blokuje commit i CI; 7 wyjątków z terminem 2027-01-31.
- Testy na 2026-10-12: 76 plików Pest (841 testów, SQLite i PostgreSQL), 67 plików vitest (419 testów), E2E Playwright z axe w CI.
- Nie jest zweryfikowane: ochrona gałęzi i required checks, restore drill na hoście docelowym, zewnętrzny uptime monitor (checklista ops w [06](06-devops-observability-and-release.md#checklista-ops-dla-człowieka-d12)).

## Znane rozbieżności z dokumentami

- CSP i bramka `composer audit`/`npm audit` są na gałęzi fali 1, jeszcze nie w `develop`.
- Wsparcie MySQL istnieje tylko jako niezacommitowana praca na `feat/sh-showcase` (D8); ADR-004 pozostaje przy samym PostgreSQL.
