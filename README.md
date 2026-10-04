# Starter — lokalny development

Całe środowisko uruchamia **Makefile + Docker Compose**. Na hoście potrzebujesz Git, Make oraz uruchomionego Docker Engine z Compose v2.20+ (lub Docker Desktop). macOS: Docker Desktop; Linux: Engine i plugin Compose; Windows: checkout i komendy w WSL2. PHP, Composer, Node, PostgreSQL i Redis są w kontenerach.

## Pierwsze uruchomienie

```sh
make setup
make doctor
```

`setup` tworzy `.env` tylko przy jego braku, buduje obraz, instaluje `composer.lock` i `package-lock.json`, generuje brakujący APP_KEY, wykonuje migracje, wgrywa idempotentne dane demo (`db:seed`: konto `test@example.com` / `password`, konta, media, strony, artykuły, FAQ, wiadomości, menu, sekcje strony głównej) i uruchamia cały stack. Kolejne wykonanie zachowuje klucz i dane, nie duplikując demo. Pierwsze pobranie obrazów wymaga internetu i może potrwać kilka minut. Setup nie importuje starego SQLite.

### Konta i dane demo (tylko `APP_ENV=local`)

Wszystkie konta demo mają lokalne hasło `password`; adresy są syntetyczne (`example.com`) i działają tylko w lokalnym stacku.

| Konto | Rola | Do czego |
| --- | --- | --- |
| `test@example.com` | admin | Pełny panel, ustawienia, użytkownicy |
| `mfa@example.com` | admin z 2FA | Logowanie z kodem TOTP: sekret `JBSWY3DPEHPK3PXP` (np. `oathtool --totp -b JBSWY3DPEHPK3PXP`) lub kod odzyskiwania `demo-recovery-01`…`04` |
| `editor@example.com` | editor | Treści bez zarządzania użytkownikami i ustawieniami |
| `user@example.com` | bez roli | Ustawienia konta, brak dostępu do panelu |
| `piotr.zak@example.com` | bez roli, niezweryfikowane | Ekran weryfikacji e-maila |

Seed wypełnia każdy ekran: co najmniej 16 rekordów na listach panelu (2 strony), statusy szkic/zaplanowany/opublikowany, media quarantine/clean/rejected, wiadomości pending/sent/failed, treści en/pl/de z brakującymi tłumaczeniami, długie tytuły i znaki wielobajtowe, przekierowania 301 starych slugów (`PageSeeder::REDIRECTS`, `ArticleSeeder::REDIRECTS`), menu z podmenu w trzech językach, sekcje strony głównej w innej kolejności (en) i z ukrytymi sekcjami (de) oraz dziennik zdarzeń. Sekret 2FA i kody są syntetyczne; `app:init-project --remove-demo` usuwa nieedytowane dane demo.

Domyślne adresy: aplikacja `http://localhost:8080`, Vite/HMR `http://localhost:5173`, skrzynka Mailpit `http://localhost:8025`. Używaj `localhost`, aby origin HMR i cookies były spójne. Lokalny HTTP na localhost obsługuje secure-context APIs przeglądarki; certyfikaty i domeny Herda nie są potrzebne.

## Start nowego projektu

```sh
make init-project ARGS='--dry-run'
make init-project ARGS='--name="Acme" --locales=pl,en --default-locale=pl --admin-email=owner@example.test --admin-name="Owner" --remove-demo --write-env'
```

`app:init-project` (tylko `local`/`testing`) pyta o brakujące wartości, a z `--no-interaction` kończy się błędem bez zmian. Kolejno: nazwa strony trafia do ustawień aplikacji (bez zapisu `APP_NAME`), `APP_PUBLIC_LOCALES/DEFAULT/FALLBACK` są wypisywane do wklejenia albo — z `--write-env` i potwierdzeniem (`--force` je pomija) — zapisywane w `.env` po kopii `.env.backup-*` (0600); następnie `make restart`. Pierwszy administrator dostaje zaproszenie mailem (kolejka), `--remove-demo` usuwa nieedytowane treści przykładowe i konto `test@example.com`. Kolor akcentu (`--accent=default|blue|violet|rose|green`) trafia do `.env` jako `APP_ACCENT` i działa po `make restart` oraz przebudowie assetów; presety (tokeny light/dark obu powierzchni) żyją w `resources/css/app.css`, a `tests/Unit/AccentContrastTest.php` pilnuje kontrastu AA. Samodzielna rejestracja użytkowników jest domyślnie wyłączana (`APP_REGISTRATION_ENABLED=false` trafia do tych samych linii `.env`; `--enable-registration` zostawia ją włączoną): bez roli konto nie ma dostępu do panelu, więc strona firmowa nie powinna jej oferować. `--dry-run` pokazuje plan bez żadnych zapisów; ponowne uruchomienie pomija wykonane kroki.

## Codzienna praca

| Komenda                                  | Działanie                                                       |
| ---------------------------------------- | --------------------------------------------------------------- |
| `make help`                              | Wszystkie dostępne komendy                                      |
| `make up`                                | Start usług, oczekiwanie na healthchecki                        |
| `make seed`                              | Dane demo ponownie (idempotentnie, tylko `APP_ENV=local`)       |
| `make fresh`                             | `migrate:fresh --seed` po potwierdzeniu `[y/N]` (`CONFIRM=1` pomija): kasuje lokalną bazę i wgrywa demo |
| `make stop` / `make down`                | Zatrzymanie / usunięcie kontenerów; dane zostają                |
| `make restart`                           | Odtworzenie usług po zmianie env lub kodu workera               |
| `make ps`                                | Status, także zakończone procesy                                |
| `make logs SERVICE=queue`                | Logi wybranej usługi; bez SERVICE — wszystkich                  |
| `make doctor`                            | Wersje, rozszerzenia, DB, Redis, SMTP, Horizon, HTTP, readiness, Vite |
| `make deps`                              | Zatrzymanie usług aplikacji i instalacja zależności z lockfile  |
| `make build`                             | Przebudowa runtime z aktualizacją obrazów bazowych              |
| `make test`                              | Pest, SQLite w pamięci zgodnie z `.env.testing`                 |
| `make test-parallel`                     | Pest w 4 procesach; zmień przez `TEST_PROCESSES=8`              |
| `make test-pgsql`                        | Pest na PostgreSQL z compose w osobnej bazie `starter_testing`  |
| `make test ARGS='--filter=registration'` | Wybrane testy                                                   |
| `make check`                             | Istniejący zestaw format/lint, TypeScript, Pint, PHPStan i Pest |
| `make test-setup`                        | Regresje bootstrappingu Makefile                                |
| `make assets`                            | Stop Vite, build klienta i SSR; `make up` przywraca HMR         |
| `make artisan ARGS='migrate:status'`     | Dowolna komenda Artisan                                         |
| `make composer ARGS='show --direct'`     | Composer                                                        |
| `make npm ARGS='run types:check'`        | npm                                                             |
| `make shell`                             | Powłoka użytkownika aplikacji                                   |
| `make db`                                | Konsola lokalnego PostgreSQL                                    |
| `make config`                            | Walidacja Compose bez wypisywania konfiguracji z hasłami        |
| `make hooks`                             | Włącza wersjonowane hooki po instalacji hostowego Gitleaks      |
| `make backup`                            | Backup lokalnej DB + storage do `./.backups` (manifest SHA-256) |
| `make restore-drill`                     | Odtworzenie ostatniego backupu do tymczasowej bazy + weryfikacja |

Nie uruchamiaj równolegle starego `composer dev`, Herda ani hostowego Vite dla tego checkoutu. Zwykłe zmiany PHP/React są widoczne przez bind mount i HMR. Worker (Horizon) wymaga `make restart` lub `make artisan ARGS='horizon:terminate'` po zmianie kodu. Po zmianie Dockerfile: `make build`, następnie `make restart`. Po zmianie lockfile: `make deps` i `make up`; instalacja zatrzymuje usługi aplikacji, aby nie pracowały na częściowo wymienionych zależnościach. `make assets` buduje SSR, ale nie uruchamia osobnego serwera SSR ze zbudowanego bundle; development SSR zapewnia Vite.

## Commity, CI i deploy

Przed pierwszym commitem zainstaluj hostowy [Gitleaks 8.30.1](https://github.com/gitleaks/gitleaks/releases/tag/v8.30.1), następnie uruchom `make hooks`. Hooki są wersjonowane w `.githooks`, a PHP/Node uruchamiają w kontenerze; nie wykonują automatycznego formatowania ani stagingu. Commit i tytuł PR mają format `type(scope): opis`, z małymi literami typu i limitem 72 znaków. Dozwolone typy: `feat`, `fix`, `refactor`, `perf`, `test`, `docs`, `build`, `ci`, `chore`, `style`, `revert`.

Push pozostaje ręczny. GitHub Actions uruchamia CI dla pushy i PR do `develop` oraz `main`; wymagane checki to `commit-convention`, `secrets` i `quality`. Workflow **Deploy** uruchamia wyłącznie człowiek ręcznie. Buduje, testuje i przekazuje do Deployer jeden artefakt z manifestem SHA-256. Nie dodawaj do niego sekretów.

Przed pierwszym deployem administrator GitHub tworzy środowiska `staging` i `production`. Dla obu dodaje zmienne `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PATH` oraz sekrety `DEPLOY_SSH_KEY` i `DEPLOY_KNOWN_HOSTS`; produkcja wymaga reviewera, blokady self-review, braku bypassu administratora i polityki brancha `main`. Staging dopuszcza tylko `develop`, produkcja tylko `main`. Włączenie migracji w ręcznym workflow wymaga osobno zatwierdzonej, kompatybilnej zmiany expand. Recepta [deploy.php](deploy.php) nie wykonuje `migrate:rollback`.

### GitHub Environment i PhpStorm

W GitHub utwórz Environments `staging` i `production`. W każdym z nich ustaw następujące wartości:

| Typ | Nazwa | Znaczenie |
| --- | --- | --- |
| Variable | `DEPLOY_HOST` | Zweryfikowany hostname albo adres IP serwera. |
| Variable | `DEPLOY_USER` | Dedykowane konto SSH używane wyłącznie przez Deployer. |
| Variable | `DEPLOY_PATH` | Katalog aplikacji na serwerze, np. `/var/www/starter`. |
| Secret | `DEPLOY_SSH_KEY` | Prywatny klucz SSH dla `DEPLOY_USER`; nie używaj klucza osobistego. |
| Secret | `DEPLOY_KNOWN_HOSTS` | Zweryfikowany wpis `known_hosts` dla `DEPLOY_HOST`. |
| Variable | `DEPLOY_SMOKE_HOST` | Publiczny host z `APP_URL`; smoke `/health/ready` po przełączeniu łączy się z lokalnym Nginx przez `curl --resolve`. |

Workflow sam ustawia `DEPLOY_ENVIRONMENT`, `DEPLOY_ARTIFACT`, `DEPLOY_ARTIFACT_SHA256` i `DEPLOY_ALLOW_MIGRATIONS` — nie dodawaj ich w GitHub. Przed pierwszym wydaniem administrator przygotowuje też plik `DEPLOY_PATH/shared/.env` na serwerze; nie trafia on ani do repozytorium, ani do artefaktu.

Zależności PHP i JavaScript żyją w nazwanych wolumenach Docker Compose, dlatego nie uruchamiaj hostowego `composer install` ani `npm install` tylko po to, aby zadowolić PhpStorm. Po zmianie lockfile uruchom `make deps`.

W PhpStorm skonfiguruj zdalne runtime'y zamiast lokalnych:

1. W **Settings → PHP → CLI Interpreter** dodaj interpreter **Docker Compose** dla [compose.yaml](compose.yaml), usługi `app`, z PHP pod ścieżką `php`; po `make up` wybierz połączenie z istniejącym kontenerem.
2. W **Settings → PHP → Composer** wybierz ten interpreter i Composer z kontenera (`/usr/local/bin/composer`), następnie zsynchronizuj zależności. Dzięki temu IDE widzi Deployer i funkcje użyte w `deploy.php`.
3. W **Settings → JavaScript Runtime** ustaw zdalny runtime Docker Compose dla usługi `vite` oraz npm z kontenera. Nie klikaj proponowanego hostowego `npm install`.

Jeżeli po konfiguracji IDE nadal wyświetla jedynie powiadomienie o brakującym `vendor` lub `node_modules`, wybierz w nim **Don't show again for this project**. To poprawne dla tego repozytorium: katalogi istnieją w kontenerze, a nie na hoście. W razie zmiany konfiguracji możesz przywrócić takie powiadomienia w **Settings → Appearance & Behavior → Notifications**.

## Układ i odpowiedzialności

| Usługa      | Rola                                                               |
| ----------- | ------------------------------------------------------------------ |
| `app`       | PHP 8.5 FPM; ten sam obraz zawiera CLI, Composer 2 i Node 24       |
| `web`       | Nginx 1.28; public/ i FastCGI, tylko index.php wykonywany jako PHP |
| `vite`      | Vite Plus, HMR i development SSR Inertia; PHP dla Wayfinder        |
| `postgres`  | PostgreSQL 18, trwała baza developerska                            |
| `redis`     | Redis 8.2 z AOF; cache, sesje, kolejka                             |
| `queue`     | Horizon (`config/horizon.php`), timeout 85 s, 100 s na zatrzymanie |
| `scheduler` | Jeden schedule:work                                                |
| `mailpit`   | Mailpit 1.27, lokalny SMTP i podgląd wiadomości                    |

Dashboard Horizon: `http://localhost:8080/horizon` (lokalnie każdy zalogowany użytkownik, poza local tylko administrator). Readiness: `/health/ready`. SMTP nie ma fallbacku do realnego dostawcy. PostgreSQL, Redis, SMTP i FPM nie publikują portów hosta. Web, Vite i Mailpit nasłuchują tylko na loopback.

`compose.yaml` opisuje usługi i wolumeny, `docker/local/` zawiera obraz, konfigurację PHP/Nginx i entrypoint. `.dockerignore` ogranicza kontekst budowy do plików runtime — kod, dane i sekrety nie trafiają do obrazu. Runtime jest developerski, nie służy do publikowania produkcji.

Vite współdzieli namespace sieciowy `app`: adres localhost zapisany w `public/hot` działa także dla requestów SSR wykonywanych przez PHP. Restart przez Makefile odtwarza obie usługi razem. Workery PHP-FPM oraz procesy CLI/Node działają jako użytkownik dopasowany do UID/GID hosta. Entrypoint przygotowuje uprawnienia jako root; proces nadrzędny FPM pozostaje root i otwiera logi przed uruchomieniem workerów z ograniczonymi uprawnieniami.

## Konfiguracja, dane i wiele checkoutów

`.env` jest jedyną lokalną konfiguracją aplikacji i Docker Compose; jest ignorowany przez Git. Lokalnie `ADMIN_REQUIRE_TWO_FACTOR=false` upraszcza dostęp do panelu, ale produkcja musi jawnie używać `ADMIN_REQUIRE_TWO_FACTOR=true`. `.env.testing` zawiera bezpieczne, wersjonowane ustawienia testów (SQLite w pamięci, array cache/session i synchroniczna kolejka). Nie kopiuj sekretów produkcyjnych do `.env`. Hasło w przykładzie służy wyłącznie izolowanej bazie lokalnej. Zmiana hasła PostgreSQL w env nie zmienia hasła już zainicjowanego użytkownika — wymaga osobnej zmiany w bazie.

Wolumeny Compose przechowują PostgreSQL, Redis, storage, bootstrap/cache, vendor i node_modules. Zależności Linux nie mieszają się z macOS; IDE na hoście może nie widzieć nowych paczek z kontenerów. Kod, generowane trasy Wayfinder i public/build pozostają w checkoutcie. Hostowy public/storage jest symlinkiem do storage kontenera, poprawnie rozwiązywanym przez Nginx. Po zatrzymaniu Makefile usuwa public/hot, aby Laravel nie wskazywał na nieczynny Vite.

Drugi checkout: przed setup uruchom `make env`, ustaw w `.env` unikalne `COMPOSE_PROJECT_NAME`, `APP_PORT`, `VITE_PORT`, `MAILPIT_PORT` oraz zgodne `APP_URL`. Potem `make setup`. Nazwa projektu izoluje sieć i wolumeny; porty muszą być wolne. Nie zmieniaj nazwy istniejącego projektu bez wcześniejszego `make down`, bo stare kontenery pozostaną uruchomione.

Nie ma komendy automatycznie kasującej wolumeny. `down` nie usuwa DB, uploadów ani zależności. Migracja danych SQLite i reset bazy są osobnymi świadomymi operacjami. `make test` używa SQLite w pamięci i nie potwierdza wszystkich zachowań PostgreSQL — do tego służy `make test-pgsql` (i job CI `tests-pgsql`); `doctor` i setup sprawdzają rzeczywiste połączenie i migracje PostgreSQL.

## Problemy

- **Cannot connect / permission denied Docker**: uruchom Docker Desktop/Engine i sprawdź dostęp swojego użytkownika do demona.
- **Port is already allocated**: zmień port i powiązany APP_URL w `.env`, następnie `make restart`.
- **Setup przerwany podczas pobierania**: ponów `make setup`; dane i klucz zostaną zachowane.
- **500 lub unhealthy**: `make logs SERVICE=app`, `make logs SERVICE=web`, `make doctor`. Po brakujących zależnościach/migracjach ponów setup.
- **Brak HMR/SSR**: `make logs SERVICE=vite`; używaj localhost; sprawdź zgodność VITE_PORT i restart obu usług. Przy pracy WSL trzymaj repo w linuksowym systemie plików.
- **Brak zmian w workerze**: `make restart`.
- **npm zgłasza brak binariów Linux**: `make deps`; nie kopiuj hostowego node_modules do kontenera.

Obrazy mają przypięte linie wersji, nie digesty. Aktualizacje tych linii pobiera `make build`; zależności aplikacji są instalowane z lockfile. Utrzymanie środowiska obejmuje okresowe przebudowy i `make check`.

Źródła: [Compose — kolejność i gotowość usług](https://docs.docker.com/compose/how-tos/startup-order/), [oficjalny obraz PHP](https://hub.docker.com/_/php), [Vite — opcje serwera](https://vite.dev/config/server-options). Plan produkcji: [środowisko, deployment i logi](docs/foundation/10-local-environment-deployment-and-logs.md).
