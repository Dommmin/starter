# Starter — lokalny development

Całe środowisko uruchamia **Makefile + Docker Compose**. Na hoście potrzebujesz Git, Make oraz uruchomionego Docker Engine z Compose v2.20+ (lub Docker Desktop). macOS: Docker Desktop; Linux: Engine i plugin Compose; Windows: checkout i komendy w WSL2. PHP, Composer, Node, PostgreSQL i Redis są w kontenerach.

## Pierwsze uruchomienie

```sh
make setup
make doctor
```

`setup` tworzy `.env.docker` tylko przy jego braku, buduje obraz, instaluje `composer.lock` i `package-lock.json`, generuje brakujący APP_KEY, wykonuje migracje i uruchamia cały stack. Kolejne wykonanie zachowuje klucz i dane. Pierwsze pobranie obrazów wymaga internetu i może potrwać kilka minut. Setup nie tworzy użytkowników ani nie importuje starego SQLite.

Domyślne adresy: aplikacja `http://localhost:8080`, Vite/HMR `http://localhost:5173`, skrzynka Mailpit `http://localhost:8025`. Używaj `localhost`, aby origin HMR i cookies były spójne. Lokalny HTTP na localhost obsługuje secure-context APIs przeglądarki; certyfikaty i domeny Herda nie są potrzebne.

## Codzienna praca

| Komenda                                  | Działanie                                                       |
| ---------------------------------------- | --------------------------------------------------------------- |
| `make help`                              | Wszystkie dostępne komendy                                      |
| `make up`                                | Start usług, oczekiwanie na healthchecki                        |
| `make stop` / `make down`                | Zatrzymanie / usunięcie kontenerów; dane zostają                |
| `make restart`                           | Odtworzenie usług po zmianie env lub kodu workera               |
| `make ps`                                | Status, także zakończone procesy                                |
| `make logs SERVICE=queue`                | Logi wybranej usługi; bez SERVICE — wszystkich                  |
| `make doctor`                            | Wersje, rozszerzenia, zależności, DB, Redis, SMTP, HTTP, Vite   |
| `make deps`                              | Zatrzymanie usług aplikacji i instalacja zależności z lockfile  |
| `make build`                             | Przebudowa runtime z aktualizacją obrazów bazowych              |
| `make test`                              | Pest, SQLite w pamięci zgodnie z phpunit.xml                    |
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

Nie uruchamiaj równolegle starego `composer dev`, Herda ani hostowego Vite dla tego checkoutu. Zwykłe zmiany PHP/React są widoczne przez bind mount i HMR. Worker wymaga `make restart` po zmianie kodu. Po zmianie Dockerfile: `make build`, następnie `make restart`. Po zmianie lockfile: `make deps` i `make up`; instalacja zatrzymuje usługi aplikacji, aby nie pracowały na częściowo wymienionych zależnościach. `make assets` buduje SSR, ale nie uruchamia osobnego serwera SSR ze zbudowanego bundle; development SSR zapewnia Vite.

## Commity, CI i deploy

Przed pierwszym commitem zainstaluj hostowy [Gitleaks 8.30.1](https://github.com/gitleaks/gitleaks/releases/tag/v8.30.1), następnie uruchom `make hooks`. Hooki są wersjonowane w `.githooks`, a PHP/Node uruchamiają w kontenerze; nie wykonują automatycznego formatowania ani stagingu. Commit i tytuł PR mają format `type(scope): opis`, z małymi literami typu i limitem 72 znaków. Dozwolone typy: `feat`, `fix`, `refactor`, `perf`, `test`, `docs`, `build`, `ci`, `chore`, `style`, `revert`.

Push pozostaje ręczny. GitHub Actions uruchamia CI dla pushy i PR do `develop` oraz `main`; wymagane checki to `commit-convention`, `secrets` i `quality`. Workflow **Deploy** uruchamia wyłącznie człowiek ręcznie. Buduje, testuje i przekazuje do Deployer jeden artefakt z manifestem SHA-256. Nie dodawaj do niego sekretów.

Przed pierwszym deployem administrator GitHub tworzy środowiska `staging` i `production`. Dla obu dodaje zmienne `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PATH` oraz sekrety `DEPLOY_SSH_KEY` i `DEPLOY_KNOWN_HOSTS`; produkcja wymaga reviewera, blokady self-review, braku bypassu administratora i polityki brancha `main`. Staging dopuszcza tylko `develop`, produkcja tylko `main`. Włączenie migracji w ręcznym workflow wymaga osobno zatwierdzonej, kompatybilnej zmiany expand. Recepta [deploy.php](deploy.php) nie wykonuje `migrate:rollback`.

## Układ i odpowiedzialności

| Usługa      | Rola                                                               |
| ----------- | ------------------------------------------------------------------ |
| `app`       | PHP 8.5 FPM; ten sam obraz zawiera CLI, Composer 2 i Node 24       |
| `web`       | Nginx 1.28; public/ i FastCGI, tylko index.php wykonywany jako PHP |
| `vite`      | Vite Plus, HMR i development SSR Inertia; PHP dla Wayfinder        |
| `postgres`  | PostgreSQL 18, trwała baza developerska                            |
| `redis`     | Redis 8.2 z AOF; cache, sesje, kolejka                             |
| `queue`     | Jeden queue:work, 3 próby, timeout 60 s, 75 s na zatrzymanie       |
| `scheduler` | Jeden schedule:work                                                |
| `mailpit`   | Mailpit 1.27, lokalny SMTP i podgląd wiadomości                    |

Horizon nie jest jeszcze zainstalowany; aktualny worker używa wbudowanej kolejki Laravel. SMTP nie ma fallbacku do realnego dostawcy. PostgreSQL, Redis, SMTP i FPM nie publikują portów hosta. Web, Vite i Mailpit nasłuchują tylko na loopback.

`compose.yaml` opisuje usługi i wolumeny, `docker/local/` zawiera obraz, konfigurację PHP/Nginx i entrypoint. `.dockerignore` ogranicza kontekst budowy do plików runtime — kod, dane i sekrety nie trafiają do obrazu. Runtime jest developerski, nie służy do publikowania produkcji.

Vite współdzieli namespace sieciowy `app`: adres localhost zapisany w `public/hot` działa także dla requestów SSR wykonywanych przez PHP. Restart przez Makefile odtwarza obie usługi razem. Workery PHP-FPM oraz procesy CLI/Node działają jako użytkownik dopasowany do UID/GID hosta. Entrypoint przygotowuje uprawnienia jako root; proces nadrzędny FPM pozostaje root i otwiera logi przed uruchomieniem workerów z ograniczonymi uprawnieniami.

## Konfiguracja, dane i wiele checkoutów

`.env.docker` jest ignorowany przez Git i montowany jako `.env` wyłącznie w kontenerach. Hostowy `.env`, SQLite i dane Herda pozostają bez zmian. Nie kopiuj do pliku Docker sekretów produkcyjnych. Hasło w przykładzie służy wyłącznie izolowanej bazie lokalnej. Zmiana hasła PostgreSQL w env nie zmienia hasła już zainicjowanego użytkownika — wymaga osobnej zmiany w bazie.

Wolumeny Compose przechowują PostgreSQL, Redis, storage, bootstrap/cache, vendor i node_modules. Zależności Linux nie mieszają się z macOS; IDE na hoście może nie widzieć nowych paczek z kontenerów. Kod, generowane trasy Wayfinder i public/build pozostają w checkoutcie. Hostowy public/storage jest symlinkiem do storage kontenera, poprawnie rozwiązywanym przez Nginx. Po zatrzymaniu Makefile usuwa public/hot, aby Laravel nie wskazywał na nieczynny Vite.

Drugi checkout: przed setup uruchom `make env`, ustaw w `.env.docker` unikalne `COMPOSE_PROJECT_NAME`, `APP_PORT`, `VITE_PORT`, `MAILPIT_PORT` oraz zgodne `APP_URL`. Potem `make setup`. Nazwa projektu izoluje sieć i wolumeny; porty muszą być wolne. Nie zmieniaj nazwy istniejącego projektu bez wcześniejszego `make down`, bo stare kontenery pozostaną uruchomione.

Nie ma komendy automatycznie kasującej wolumeny. `down` nie usuwa DB, uploadów ani zależności. Migracja danych SQLite i reset bazy są osobnymi świadomymi operacjami. Testy Pest używają SQLite w pamięci i nie potwierdzają wszystkich zachowań PostgreSQL; `doctor` i setup sprawdzają rzeczywiste połączenie i migracje PostgreSQL.

## Problemy

- **Cannot connect / permission denied Docker**: uruchom Docker Desktop/Engine i sprawdź dostęp swojego użytkownika do demona.
- **Port is already allocated**: zmień port i powiązany APP_URL w `.env.docker`, następnie `make restart`.
- **Setup przerwany podczas pobierania**: ponów `make setup`; dane i klucz zostaną zachowane.
- **500 lub unhealthy**: `make logs SERVICE=app`, `make logs SERVICE=web`, `make doctor`. Po brakujących zależnościach/migracjach ponów setup.
- **Brak HMR/SSR**: `make logs SERVICE=vite`; używaj localhost; sprawdź zgodność VITE_PORT i restart obu usług. Przy pracy WSL trzymaj repo w linuksowym systemie plików.
- **Brak zmian w workerze**: `make restart`.
- **npm zgłasza brak binariów Linux**: `make deps`; nie kopiuj hostowego node_modules do kontenera.

Obrazy mają przypięte linie wersji, nie digesty. Aktualizacje tych linii pobiera `make build`; zależności aplikacji są instalowane z lockfile. Utrzymanie środowiska obejmuje okresowe przebudowy i `make check`.

Źródła: [Compose — kolejność i gotowość usług](https://docs.docker.com/compose/how-tos/startup-order/), [oficjalny obraz PHP](https://hub.docker.com/_/php), [Vite — opcje serwera](https://vite.dev/config/server-options). Plan produkcji: [środowisko, deployment i logi](docs/foundation/10-local-environment-deployment-and-logs.md).
