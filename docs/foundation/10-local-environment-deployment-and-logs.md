# Lokalne środowisko, bezprzerwowy deploy i logi

Status: lokalny setup Docker Compose jest zaimplementowany. 2026-09-10 dodano `deploy.php` oraz ręczny workflow GitHub Actions: buduje on testowany artefakt z manifestem SHA-256 i przekazuje go Deployerowi. Produkcyjny host, systemd/SSR, Nginx, monitoring, sekrety i GitHub Environment nadal wymagają konfiguracji administratora; nie są deklarowane jako wdrożone przez samą receptę. Produkcja nadal używa natywnego Nginx/PHP-FPM; ta zmiana dotyczy developmentu.

## ADR-021: środowisko developerskie Docker Compose

Decyzja właściciela z 2026-09-10 zastępuje wcześniejszy plan natywnego developmentu. Jedyny wspierany lokalny workflow to Docker Compose sterowany przez Makefile. Instrukcja instalacji, komendy, dane, ograniczenia i rozwiązywanie problemów: [README](../../README.md).

Manifest usług to `compose.yaml`, runtime jest w `docker/local`, a konfiguracja lokalna powstaje z `.env.example`. PHP 8.5 FPM/CLI, Node 24, PostgreSQL 18, Redis 8.2, Nginx i Mailpit działają w kontenerach. Kolejkę obsługuje `queue:work`; Horizon pozostaje planowaną paczką. Jeden `schedule:work` obsługuje scheduler. Vite zapewnia development SSR Inertia v3.

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

## Dowody wymagane przed produkcją

- Nowy developer przechodzi onboarding; doctor wykrywa rozbieżność CLI/FPM i zajęty port; reset odmawia pracy na innym środowisku.
- Ciągłe żądania podczas deployu i rollbacku na stagingu: brak deploy-induced 5xx, pustego SSR, błędnej wersji rendererów i 404 starych assetów; aktywna sesja i formularz pozostają poprawne.
- Awaria przed symlinkiem nie cofa zdrowej wersji; awaria po symlinku powoduje dokładnie jeden rollback i alert. Test obejmuje zerwane SSH, wadliwy SSR, worker i niedziałający poprzedni release.
- Poprzedni kod działa po migracji i przetwarza nowe payloady kolejki; bez automatycznego cofania DB. Cache release'ów nie miesza schematów i deploy nie wykonuje globalnego flush Redis.
- Gość/editor nie czyta zdarzeń; administrator widzi tylko dozwolone pola. Sekrety kontrolne nie trafiają do żadnego sinka, a złośliwy tekst nie wykonuje HTML/JS.
- Niedostępność DB, kolejki, odbiorcy logów i pełny dysk nie tworzą pętli loggera; zewnętrzny alarm dociera, rotacja/retencja działa, archiwum da się odczytać po utracie aplikacji.

## Źródła i zakres weryfikacji

Zweryfikowano dokumentację 2026-09-09: [Deployer Laravel recipe](https://deployer.org/docs/8.x/recipe/laravel), [rollback recipe](https://deployer.org/docs/8.x/recipe/deploy/rollback), [FastCGI realpath](https://deployer.org/docs/8.x/avoid-php-fpm-reloading), [Inertia SSR](https://inertiajs.com/docs/v3/advanced/server-side-rendering), [Laravel logging](https://laravel.com/framework/docs/13.x/logging), [Nginx lifecycle](https://nginx.org/en/docs/control.html). Wieloetapowa recepta, watchdog, projekcja zdarzeń i limity są projektem tego startera, nie deklaracją funkcji gotowej paczki. Ich wykonalność trzeba potwierdzić na przypiętych wersjach i wybranej VM.
