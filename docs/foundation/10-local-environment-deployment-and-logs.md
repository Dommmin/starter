# Lokalne środowisko, bezprzerwowy deploy i logi

Status: lokalny setup Docker Compose jest zaimplementowany. 2026-09-10 dodano `deploy.php` oraz ręczny workflow GitHub Actions: buduje on testowany artefakt z manifestem SHA-256 i przekazuje go Deployerowi. 2026-09-27 (P0-C) dodano Horizon, `/health/ready`, alerty i logi JSON, skrypty backup/restore z próbą odtworzenia, szablony produkcyjne w `deploy/` oraz readiness z jednorazowym automatycznym rollbackiem w recepcie — szczegóły i podział „gotowe w repo / wymaga operatora” w sekcji [Stan P0-C](#stan-p0-c-co-jest-w-repo-a-co-wymaga-operatora). Produkcyjny host, instalacja szablonów systemd/Nginx/FPM, TLS, DNS, monitoring zewnętrzny, sekrety i GitHub Environment nadal wymagają administratora; nie są deklarowane jako wdrożone przez samą receptę. Produkcja nadal używa natywnego Nginx/PHP-FPM; ta zmiana dotyczy developmentu.

## ADR-021: środowisko developerskie Docker Compose

Decyzja właściciela z 2026-09-10 zastępuje wcześniejszy plan natywnego developmentu. Jedyny wspierany lokalny workflow to Docker Compose sterowany przez Makefile. Instrukcja instalacji, komendy, dane, ograniczenia i rozwiązywanie problemów: [README](../../README.md).

Manifest usług to `compose.yaml`, runtime jest w `docker/local`, a konfiguracja lokalna powstaje z `.env.example`. PHP 8.5 FPM/CLI (z GD: JPEG/PNG/WebP/AVIF i `exif` dla wariantów DAM), Node 24, PostgreSQL 18, Redis 8.2, Nginx, Mailpit i ClamAV (`clamd`, skan uploadów DAM, dostępny tylko w sieci Compose jako `clamav:3310`; pierwszy start pobiera sygnatury do wolumenu `clamav`) działają w kontenerach. Kolejkę obsługuje Horizon (`laravel/horizon` 5.x, serwis `queue`, healthcheck `horizon:status`, dashboard `/horizon` tylko dla administratora); `make doctor` sprawdza jego status i `/health/ready`. Jeden `schedule:work` obsługuje scheduler. Vite zapewnia development SSR Inertia v3.

Kontenery mają własne wolumeny danych i zależności; `.env` jest wspólnym, lokalnym źródłem konfiguracji dla aplikacji i Compose, tworzonym z `.env.example` tylko gdy go brakuje. `make setup` instaluje lockfile, generuje klucz tylko gdy go brakuje, uruchamia migracje w lokalnym PostgreSQL i startuje usługi. Nie seeduje kont automatycznie. `make down` zachowuje wszystkie wolumeny. Nie ma automatycznego resetu ani kasowania danych.

Obrazy mają przypięte linie wersji, a nie digesty; `make build` pobiera aktualizacje tych linii. Lockfile przypinają zależności aplikacji. Zgodność produkcyjna, TLS, docelowe biblioteki konwersji mediów, skaner i testy zbudowanego SSR nadal wymagają kwalifikacji na stagingu.

## ADR-022: Deployer z kontrolowanym automatycznym rollbackiem

Zero-downtime oznacza brak planowego wyłączenia HTTP podczas kompatybilnej aktualizacji. Nie oznacza HA jednej VM ani gwarancji braku błędów wadliwego release'u w czasie wykrywania regresji. Recepta jest własnym, testowanym rozszerzeniem Deployer: samo użycie bazowej recepty i atomowego symlinka nie zapewnia całego poniższego kontraktu.

Produkcja: natywne Nginx, PHP-FPM, PostgreSQL, Redis, Node SSR i Horizon pod systemd; jeden scheduler. Deployer ma osobne konto bez nieograniczonego sudo. Współdzielone dane/env/logi poza releases, bootstrap/config cache lokalny dla konkretnego release'u. Pięć ukończonych release'ów obejmuje bieżący; dodatkowo nie usuwać wersji używanej przez żywy proces lub wskazanej jako cel rollbacku.

CI buduje archiwum kodu, vendor oraz bundle klienta/SSR z lockfile na zgodnym Linux/CPU. Manifest zawiera commit SHA, sumę SHA-256 archiwum, wersje runtime i lockfile, migracje oraz poprzedni zgodny release. Staging i produkcja otrzymują to samo archiwum; config cache powstaje osobno na hoście z jego env. Deployer weryfikuje sumę, a nie pobiera ruchomy branch lub przebudowuje zależności. Artefakt nie zawiera sekretów, lokalnych danych ani debug toolingu.

### Przepływ wydania

1. Człowiek zatwierdza manifest wraz z zakresem automatycznego cofnięcia; deploy zakłada lock i trwale zapisuje poprzedni release oraz fazę operacji. To autoryzacja deterministycznej recepty, nie autonomicznego agenta.
2. Przygotowanie nowego katalogu, weryfikacja zasobów i uprawnień, cache konfiguracji dla tego katalogu. Dopuszczalne tylko migracje expand zgodne z poprzednim kodem, ograniczone czasem oczekiwania na lock. Nie uruchamiać maintenance ani `migrate:rollback`.
3. Nowy Node SSR startuje obok starego na innym porcie loopback. Konfiguracja PHP każdego release'u wskazuje jego konkretną instancję SSR; jedna wspólna zmienna zmieniana w locie jest niedopuszczalna. Bundle i port muszą być zgodne z przypiętą wersją adaptera Inertia.
4. Kandydat przechodzi wewnętrzny HTTP smoke przez izolowany vhost dostępny tylko z loopback: release ID, DB, cache, prawdziwy rendering SSR treści/meta i assety. Sam status procesu lub `/up` nie wystarcza. Testy nie wysyłają maili ani nie zmieniają danych klienta.
5. Atomowe przełączenie `current`; konfiguracja FastCGI z `$realpath_root` wiąże request z rzeczywistym katalogiem. Stary SSR pozostaje dostępny dla trwających requestów i rollbacku. Nginx nie wymaga restartu przy zwykłym przełączeniu symlinka.
6. Kontrole po przełączeniu: poprawny release ID i HTML SSR, login, statyczne assety. Propozycja: timeout pojedynczej próby 5 s, trzy nieudane próby co 5 s uruchamiają rollback; cała automatyczna obserwacja 120 s. Błąd zewnętrznego mailera nie jest samodzielnym powodem cofania kodu. Progi kwalifikować na stagingu.
7. Kontrolowane zakończenie Horizon po bieżących jobach i uruchomienie nowej wersji; potwierdzenie SHA workera oraz heartbeat. Stop timeout większy od limitu joba, limit joba krótszy od retry_after. Payloady muszą działać ze starym i nowym kodem. Scheduler przechodzi na nowy kod bez podwójnego wykonywania; trwające zadania są uwzględnione w drenażu.
8. Dopiero po sukcesie zapis manifestu jako healthy i cleanup. Stary SSR wyłączyć po oknie rollbacku i zakończeniu starych requestów. Dłuższy ręczny rollback najpierw uruchamia SSR poprzedniej wersji. Zachować hashowane assety starych wersji w osobnym append-only katalogu; propozycja retencji 30 dni, aby otwarta karta nie traciła lazy-loaded JS. Retencja mediów to oddzielna polityka.

### Cache i kompresja statycznych assetów (Nginx)

Polityka jest wspólna dla lokalnego Dockera i produkcyjnego Nginx; wzorcem jest `docker/local/nginx.conf`, a szablonem produkcyjnego vhosta `deploy/nginx/starter.conf` (instaluje go administrator, recepta Deployer go nie zmienia). `/build/assets/*` (nazwy z hashem Vite) → `Cache-Control: public, max-age=31536000, immutable`; `/build/manifest.json` i `fonts-manifest.json` → `no-cache`; niehashowane pliki z `public/` (favicon, obrazy, fonty) → `max-age=86400, stale-while-revalidate=604800`; HTML i odpowiedzi Inertia zachowują nagłówki Laravel (`no-cache, private`). Gzip (`gzip_vary on`) dla HTML, CSS, JS, JSON, SVG i XML. Brotli tylko, jeśli moduł jest dostępny w produkcyjnym Nginx — oficjalny obraz lokalny go nie ma. Budżet bundla pilnuje `npm run check:budget` (`bundle-budget.json`) w CI.

### Granice automatycznego cofnięcia

| Miejsce awarii                                       | Reakcja recepty                                                                                           |
| ---------------------------------------------------- | --------------------------------------------------------------------------------------------------------- |
| Przed przełączeniem current                          | Przerwij kandydata; aktywna wersja zostaje. Nie uruchamiaj ślepo rollbacku, który cofnąłby zdrową wersję  |
| Po przełączeniu, w oknie kontroli                    | Jedna próba powrotu do zapisanego healthy release'u, jego SSR i workerów, następnie ponowny smoke i alert |
| Poprzednia wersja niezdrowa lub brak kompatybilności | Zablokuj kolejne deploye, zachowaj dowody i alarmuj operatora; bez pętli przełączania wersji              |
| Awaria migracji lub zewnętrzny skutek uboczny        | Bez cofania danych, maili, publikacji i operacji dostawców; naprawa według runbooka                       |
| Awaria po oknie automatycznej obserwacji             | Alarm i decyzja operatora; brak nieograniczonego automatycznego cofania na podstawie dowolnego 500        |

Stan deployu i niezależny watchdog na hoście muszą pozwalać dokończyć sprawdzenie/cofnięcie przy zerwanym SSH lub przerwanym runnerze CI. Watchdog nie zależy od Laravel/Redis i działa pod tym samym lockiem; sprawdza ID operacji i bieżący symlink przed mutacją. Niedostępność całej VM wymaga operatora/DR.

Pierwsze wydanie nie ma poprzednika: kandydat przechodzi kontrole przed udostępnieniem, ale automatyczny rollback nie jest dostępny. Wydanie z niekompatybilną migracją nie kwalifikuje się do tej ścieżki; trzeba rozbić zmianę na expand/contract, zamiast wyłączać ochronę po cichu.

## ADR-023: trzy rodzaje logów i dwa poziomy dostępu

P0: pliki JSON Laravel/Monolog, logi Nginx oraz systemd journal dla PHP-FPM, SSR, Horizon, deployu i skanera. Rotację plików obsługuje jeden mechanizm logrotate, journal ma osobny limit. Wyjście usług powinno zawierać czas UTC, service, environment, release ID i correlation ID tam, gdzie istnieje. Nginx tworzy zaufany request ID przekazywany do PHP; aplikacja wiąże go z jobami i SSR. Nie ufamy dowolnie długiemu ID od klienta.

| Dane                         | Miejsce i odbiorca                                                                                                                                           |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Diagnostyka techniczna       | Pliki/journal dostępne operatorowi przez ograniczony SSH; stack trace i komunikaty wyjątków po redakcji                                                      |
| Audit działań                | Osobny zapis w DB: kto/co/kiedy/zasób/wynik, zgodnie z polityką audytu; bez pełnych treści i sekretów                                                        |
| Zdarzenia dla administratora | Mała jawna projekcja operacyjna w DB, np. failed delivery, błąd przetwarzania pliku, publikacja, release failed/rolled back; bez kopiowania wszystkich logów |

Administrator aplikacji otrzymuje ekran „System → Zdarzenia”: czas, ważność, usługa, bezpieczny opis, status, licznik powtórzeń i ID do kontaktu z supportem. Filtry czasu/poziomu/usługi/statusu oraz paginacja po stronie serwera. Drugi widok pokazuje ostatnio zmierzony stan usług i czas pomiaru; przeterminowany heartbeat oznacza „brak aktualnych danych”, nie zielony status.

Widoki wymagają MFA i osobnego uprawnienia `system.diagnostics.read`, przyznanego domyślnie wyłącznie administratorowi. Editor i gość otrzymują odmowę także z bezpośredniego endpointu. Odpowiedzi są private/no-store. Tekst jest escapowany, dostęp i próby odmowy audytowane. P0 nie udostępnia arbitralnych ścieżek plików, tail, surowych stack trace, SQL, eksportu logów ani przycisku uruchamiającego shell/retry/rollback. Techniczny operator korzysta z osobnego kanału dostępu.

Projekcja zapisuje tylko typowane, znane zdarzenia, z limitem rozmiaru i deduplikacją; nie analizuje plików logów przy każdym żądaniu. Nie zapisujemy każdego requestu do PostgreSQL. Nieudany zapis diagnostyki nie wywołuje rekurencyjnego logowania i nie blokuje obsługi błędu; krytyczny audit biznesowy zachowuje osobny kontrakt transakcyjny. Stan deployu/awarii zbierany poza aplikacją jest importowany po jej powrocie. Niedziałająca aplikacja nie może być jedynym miejscem informowania o jej awarii.

### Retencja, transport i alarmy

Propozycja P0: logi techniczne 7 dni lokalnie, łączny budżet dysku 1 GB do dopasowania do VM; archiwum poza hostem 30 dni; projekcja zdarzeń 30 dni; audit 90 dni jako osobna decyzja właściciela danych. Próg pojemności może skrócić lokalną retencję — alarmujemy o utracie pokrycia. Redakcja przed zapisem i wysyłką obejmuje nagłówki auth/cookie, hasła, tokeny, request body, query string, payload jobów i argumenty stack trace; test obejmuje też Nginx oraz zewnętrzny SDK.

Minimalna propozycja bez osobnego klastra logowego: systemowy timer co godzinę wysyła zamknięte, zredagowane segmenty logów i eksport journal do szyfrowanego storage backupowego w UE. Manifest z checksumą/cursorem zapobiega pomijaniu i umożliwia deduplikację; usunięcie lokalnego segmentu dopiero po potwierdzeniu lub kontrolowanym przekroczeniu limitu z alarmem. Transfer ma retry/backoff i ograniczony spool. Możliwa utrata ostatniej godziny przy utracie hosta jest jawnym ograniczeniem, nie mechanizmem alertowania.

Laravel 13 nie dostarcza wbudowanego, szyfrującego kanału logów. `SESSION_ENCRYPT` chroni wyłącznie dane sesji, a `env:encrypt` pliki środowiskowe; żaden z tych mechanizmów nie zastępuje redakcji ani szyfrowania logów. Logi redagujemy przed zapisem, transportujemy przez TLS i przechowujemy w szyfrowanym storage. Ewentualny własny handler Monologa wymaga osobnej decyzji, przeglądu kluczy i testu odczytu po rotacji.

Alarmy P0 działają oddzielnie: zewnętrzny uptime oraz systemowy monitor heartbeat/backup/dysku i krytycznych błędów wysyła deduplikowane powiadomienia do operatora przez wybrany kanał niezależny od aplikacyjnej kolejki. Sam monitor ma zewnętrzny dead-man heartbeat. Właściciel kanału, test dostarczenia i zasady quiet hours są bramką stagingu.

Alternatywa przy potrzebie przeszukiwania logów na żywo: jeden zarządzany centralny system logów/APM po akceptacji regionu, kosztu i retencji; zastępuje odpowiednią część zbierania/alertów. Nightwatch pozostaje kandydatem z rejestru 07. Własny Loki/Grafana/ELK na tej samej małej VM zwiększa koszt utrzymania i nie usuwa wspólnego punktu awarii, więc nie jest baseline P0. Konkretny storage i kanał powiadomień wybieramy wraz z dostawcą VM; nie są jeszcze skonfigurowane.

## Stan P0-C: co jest w repo, a co wymaga operatora

Stan na 2026-09-27. „Gotowe w repo” oznacza kod, konfigurację i testy lokalne; nic z tego nie zostało uruchomione na stagingu ani produkcji.

### Kolejka i Horizon

- `config/horizon.php`: jeden supervisor `supervisor-default` na kolejce `default` (wszystkie joby: `ScanMediaAsset`, `GenerateImageVariants`, `SendContactMessage`). Łańcuch limitów: najdłuższy job 80 s < timeout supervisora 85 s < `REDIS_QUEUE_RETRY_AFTER` 90 s < stop 100 s (Compose `stop_grace_period`, systemd `TimeoutStopSec`); test `tests/ai` pilnuje kolejności. Procesy: local 2, staging 2, production `HORIZON_MAX_PROCESSES` (domyślnie 4 — do pomiaru na VM).
- Dashboard `/horizon`: middleware panelu (`auth`, `verified`, `EnsureCanAccessAdminPanel` z wymogiem MFA admina) i gate `viewHorizon` → `User::isAdmin()` we wszystkich środowiskach (lokalnie, jak reszta panelu, każdy zalogowany jest administratorem; gość nie ma dostępu także lokalnie). `X-Robots-Tag: noindex, nofollow`. Testy odmowy: gość, editor, użytkownik bez roli.
- Scheduler: `horizon:snapshot` co 5 min, `ops:heartbeat` co minutę, `ops:check-backup` co godzinę (wszystkie `onOneServer`).

### Readiness `/health/ready`

- Liveness pozostaje na `/up`. `/health/ready` jest bezstanowy (bez grupy `web`: brak sesji, cookies, CSRF), `Cache-Control: no-store`, `noindex`, limit `HEALTH_RATE_LIMIT`/min na IP. Ta sama logika działa z CLI: `php artisan ops:readiness [--strict]`.
- Krytyczne (HTTP 503 `fail`): baza (`select 1`, `DB_CONNECT_TIMEOUT` 5 s), Redis (`PING`, `REDIS_TIMEOUT` 2 s), zapis/odczyt/usunięcie pliku na dysku `local`. Tła (HTTP 200 `degraded`): Horizon (reguła `horizon:status`), heartbeat schedulera starszy niż `OPS_HEARTBEAT_MAX_AGE` (180 s), opcjonalnie clamd `PING` (`HEALTH_CHECK_SCANNER=true`). Restart Horizon podczas deployu nie wyłącza więc węzła webowego.
- Anonimowo tylko `{"status": ...}`; wyniki per check wyłącznie z nagłówkiem `X-Health-Token` równym `HEALTH_TOKEN`. Brak nazw hostów, wersji i treści wyjątków.

### Alerty i logi

- `App\Services\Ops\OpsAlerter`: każdy alert to wpis `critical` `ops.*` (bez PII: klasy, nazwy kolejek, liczniki). Przy `OPS_ALERT_EMAIL` dodatkowo deduplikowany (`OPS_ALERT_DEDUPE_MINUTES`) mail tekstowy wysyłany synchronicznie, nie przez kolejkę. Błąd maila nie przerywa wywołującego i nie tworzy pętli logowania.
- Źródła: nieudany job (`JobFailed` → `ops.queue.job_failed`), długie oczekiwanie Horizon (`LongWaitDetected`, próg `HORIZON_WAIT_THRESHOLD` 300 s → `ops.queue.long_wait`), brak lub przeterminowany backup (`ops:check-backup` → `ops.backup.missing|stale`), nieudany backup (skrypt: JSON `critical` na stderr + `logger -p user.crit`; unit: `OnFailure=starter-alert@`), nieudana readiness/rollback deployu (`logger -t starter-deploy`), awaria unitu systemd (`starter-alert@`, opcjonalny hook `/etc/starter/alert-hook`).
- Logi: kanał `json` (plik `storage/logs/laravel.json.log`, rotacja `deploy/logrotate/starter`, 7 dni) i `json_stderr` (journal). Produkcja: `LOG_CHANNEL=stack`, `LOG_STACK=json` (opcjonalnie `json,json_stderr`), `LOG_LEVEL=info`. Każdy rekord ma `service` (`LOG_SERVICE` z unitu systemd), `environment`, `release` (plik `RELEASE` zapisywany przez receptę, utrwalany w `config:cache`) i `request_id` z Nginx (`fastcgi_param REQUEST_ID $request_id`, tylko 32 znaki hex; nagłówek klienta jest ignorowany). Access log Nginx w JSON bez query stringu i cookies.

### Backup i restore

- `scripts/backup/backup.sh`: `pg_dump -Fc` + `tar.gz` katalogów `media` i `public` z `storage/app` + `manifest.json` (SHA-256 i rozmiar plików, liczby wierszy tabel, liczba plików storage). Zapis do `*.partial` i atomowe przemianowanie, rotacja `BACKUP_KEEP` kopii, znacznik `BACKUP_STATUS_FILE`. Opcjonalne szyfrowanie `BACKUP_ENCRYPT=age|gpg` — przy braku narzędzia backup kończy się błędem zamiast zapisać jawną kopię. Konfiguracja wyłącznie przez env (libpq `PG*`); przykład `deploy/backup/backup.env.example`.
- `scripts/backup/restore.sh --drill [BACKUP]`: weryfikuje sumy, odtwarza dump do nowej, tymczasowej bazy, porównuje liczbę tabel i dokładne liczby wierszy z manifestem, rozpakowuje storage do katalogu tymczasowego i liczy pliki, mierzy czas, zawsze usuwa bazę tymczasową; wynik JSON trafia do `BACKUP/drill-*.json`. `--restore BACKUP --target-db NAZWA --storage-target KATALOG` odtwarza wyłącznie do nieistniejącej bazy i pustego katalogu — przełączenie aplikacji to decyzja i krok operatora (zatrzymanie zapisów, ocena utraty danych od backupu, zgoda właściciela danych, ponowne zastosowanie żądań usunięcia według 02).
- Lokalnie: `make backup` i `make restore-drill` (serwis Compose `backup`, profil `ops`, klient PostgreSQL 18, storage tylko do odczytu, wynik w `./.backups`, poza Git). Produkcja: `deploy/systemd/starter-backup.{service,timer}` codziennie 02:30 UTC (+ do 15 min), `Persistent=true`, niezależnie od Laravel.
- Próba 2026-09-27 (lokalna baza developerska z danymi syntetycznymi, 17 tabel, 31 wierszy, 13 plików storage): backup 1 s (44 KB dump, 3 KB storage), drill `passed` — sumy zgodne, 17/17 tabel, brak różnic wierszy, 13/13 plików, odtworzenie DB 384 ms, cały drill 490 ms. Negatywne: zmodyfikowane archiwum → `checksum mismatch`, drill przerwany; niedostępny host DB → backup kończy się kodem ≠0, logiem `ops.backup.failed`, bez pozostawionego `*.partial`. Czas nie jest miarą RTO produkcji — należy go powtórzyć na danych o docelowym rozmiarze.

### Deploy (Deployer) i szablony hosta

- `deploy.php` definiuje jawny przepływ zamiast domyślnego z `recipe/laravel.php`. Poprawka: domyślny przepływ uruchamiał `artisan:migrate` bez względu na `DEPLOY_ALLOW_MIGRATIONS`; teraz migracje wykonuje wyłącznie `deploy:migrate` przy `DEPLOY_ALLOW_MIGRATIONS=true` (polityka bez zmian, tylko egzekwowana).
- Kolejność: `deploy:check_blocked` → prepare (weryfikacja SHA-256 artefaktu, zapis `RELEASE`) → `storage:link` → `config/route/view/event:cache` → `deploy:migrate` → `deploy:smoke` (manifest Vite, `about`, `ops:readiness` kandydata przed przełączeniem) → `deploy:remember_healthy` (`.dep/healthy_release`) → atomowy symlink → `deploy:restart_workers` (`horizon:terminate`, `schedule:interrupt`, `inertia:stop-ssr`; systemd uruchamia procesy z nowego `current`) → `deploy:smoke:live` → cleanup.
- `deploy:smoke:live`: `/health/ready` 3 próby × 5 s timeoutu co 5 s (przez `curl --resolve DEPLOY_SMOKE_HOST:443:127.0.0.1`, więc TrustHosts i TLS działają jak dla ruchu). Porażka → alert, jednokrotny powrót symlinka do zapamiętanego zdrowego release'u, oznaczenie `BAD_RELEASE`, restart workerów, ponowna readiness, alert z wynikiem i nieudany deploy. Jeśli poprzednia wersja też nie przechodzi — `.dep/deploy_blocked` blokuje kolejne deploye do decyzji operatora. Pierwszy deploy nie ma celu rollbacku. Schemat nigdy nie jest cofany.
- Szablony w `deploy/`: `nginx/starter.conf` + `nginx/security-headers.conf` (HTTP→HTTPS, ACME, TLS 1.2/1.3, HSTS bez `includeSubDomains`, nosniff/frame/referrer/permissions policy, `client_max_body_size 52m`, immutable cache `/build/assets` i `/storage/media`, gzip, brotli zakomentowany, większe bufory FastCGI, `$realpath_root`, blokada plików ukrytych, JSON access log; `nginx -t` przechodzi w `nginx:1.28-alpine` z testowym certyfikatem), `php-fpm/starter.conf`, `systemd/starter-{horizon,ssr,scheduler,backup}.service`, timery schedulera (co minutę) i backupu, `starter-alert@.service`, `logrotate/starter`.

### Wymaga operatora (poza repo)

- Hosting/VM w UE, DNS, certyfikat TLS i jego odnowienie (certbot lub dostawca), decyzja o HSTS `includeSubDomains`/preload, CSP (wymaga nonce dla Inertia/SSR — osobna decyzja).
- Instalacja i dopasowanie szablonów (ścieżki, użytkownicy, rozmiar puli FPM, `HORIZON_MAX_PROCESSES`), `systemctl enable --now` dla unitów i timerów; `systemd-analyze verify` na docelowym hoście (nie wykonano lokalnie).
- `shared/.env` produkcji: `LOG_CHANNEL`/`LOG_STACK`, `OPS_ALERT_EMAIL`, `HEALTH_TOKEN`, `BACKUP_STATUS_FILE`, `HEALTH_CHECK_SCANNER`; zmienna GitHub Environment `DEPLOY_SMOKE_HOST`.
- ClamAV na hoście: `clamd` + `freshclam` (aktualizacja sygnatur), limity 52 MB jak w Compose, monitoring świeżości sygnatur.
- Backup poza hostem: szyfrowany storage w UE z osobnymi poświadczeniami (np. rclone/restic/replikacja obiektowa katalogu `BACKUP_DIR`), klucz prywatny `age` przechowywany poza VM, retencja 30 dni, miesięczny restore drill na kopii poza hostem, pomiar RTO/RPO na danych docelowego rozmiaru.
- Kanał alertów (Q7): zewnętrzny uptime `/health/ready` i `/up`, monitor journal/`logger` z deduplikacją, dead-man heartbeat monitora, test dostarczenia alertu; skrypt `/etc/starter/alert-hook` po wyborze kanału.
- Równoległy SSR per release na osobnym porcie (ADR-022 krok 3), watchdog niezależny od runnera CI, kontrola SSR/logowania w smoke po przełączeniu, retencja starych assetów — nadal niezaimplementowane; obecny restart SSR może na chwilę przełączyć rendering na klienta.
- `opcache.validate_timestamps=off` w puli: pamięć opcache rośnie z kolejnymi release'ami — okresowy `reload` PHP-FPM w oknie serwisowym lub monitoring zapełnienia.

## Dowody wymagane przed produkcją

- Nowy developer przechodzi onboarding; doctor wykrywa rozbieżność CLI/FPM i zajęty port; reset odmawia pracy na innym środowisku.
- Ciągłe żądania podczas deployu i rollbacku na stagingu: brak deploy-induced 5xx, pustego SSR, błędnej wersji rendererów i 404 starych assetów; aktywna sesja i formularz pozostają poprawne.
- Awaria przed symlinkiem nie cofa zdrowej wersji; awaria po symlinku powoduje dokładnie jeden rollback i alert. Test obejmuje zerwane SSH, wadliwy SSR, worker i niedziałający poprzedni release.
- Poprzedni kod działa po migracji i przetwarza nowe payloady kolejki; bez automatycznego cofania DB. Cache release'ów nie miesza schematów i deploy nie wykonuje globalnego flush Redis.
- Gość/editor nie czyta zdarzeń; administrator widzi tylko dozwolone pola. Sekrety kontrolne nie trafiają do żadnego sinka, a złośliwy tekst nie wykonuje HTML/JS.
- Niedostępność DB, kolejki, odbiorcy logów i pełny dysk nie tworzą pętli loggera; zewnętrzny alarm dociera, rotacja/retencja działa, archiwum da się odczytać po utracie aplikacji.

## Źródła i zakres weryfikacji

Zweryfikowano dokumentację 2026-09-09: [Deployer Laravel recipe](https://deployer.org/docs/8.x/recipe/laravel), [rollback recipe](https://deployer.org/docs/8.x/recipe/deploy/rollback), [FastCGI realpath](https://deployer.org/docs/8.x/avoid-php-fpm-reloading), [Inertia SSR](https://inertiajs.com/docs/v3/advanced/server-side-rendering), [Laravel logging](https://laravel.com/framework/docs/13.x/logging), [Nginx lifecycle](https://nginx.org/en/docs/control.html). Wieloetapowa recepta, watchdog, projekcja zdarzeń i limity są projektem tego startera, nie deklaracją funkcji gotowej paczki. Ich wykonalność trzeba potwierdzić na przypiętych wersjach i wybranej VM.
