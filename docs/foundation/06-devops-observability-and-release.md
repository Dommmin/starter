# DevOps, obserwowalność i release

## Cel

Powtarzalne lokalne środowisko, kontrolowany artefakt i bezpieczny release małej aplikacji. Obecnie nie ma Docker, CI/CD, GHCR ani hostingu do oceny. Plan nie zmienia żadnego środowiska.

## Decyzje i uzasadnienie

### ADR-014: natywne usługi i Deployer z automatycznym rollbackiem

Status: wymaganie użytkownika dla produkcji. Nginx + PHP-FPM bez Dockera na jednej VM w UE. Deployer publikuje zweryfikowane archiwum, utrzymuje pięć ukończonych release’ów i atomowo przełącza `current`. FastCGI używa `$realpath_root`. Nowy SSR startuje obok starego przed przełączeniem; po kontroli następuje drenaż starych procesów. [Deployer: PHP-FPM](https://deployer.org/docs/8.x/avoid-php-fpm-reloading).

Recepta automatycznie cofa kod do zapisanego healthy release’u po nieudanych kontrolach w oknie deployu, odtwarza właściwy SSR/workery i alarmuje operatora. Nie cofa bazy ani efektów zewnętrznych. Przed symlinkiem jedynie przerywa kandydata. Wydanie nadal zatwierdza człowiek; ograniczony rollback jest częścią jego autoryzacji. Pełna sekwencja, timeouty, watchdog i testy: [ADR-022](10-local-environment-deployment-and-logs.md). Zero-downtime dotyczy kompatybilnej aktualizacji HTTP; jedna VM nie zapewnia HA.

### ADR-015: backup i alerty są obowiązkowe, konkretne paczki nie

Status: proponowany. P0: logi JSON, error alert, zewnętrzny uptime, heartbeat queue/scheduler i backup poza hostem z testem restore. Wariant monitoring: zintegrowany Nightwatch po akceptacji danych i kosztu; alternatywa alerty hostingu z centralnych logów. Pulse/Horizon/Telescope nie zastępują niezależnego monitora awarii aplikacji. Nie uruchamiać wszystkich narzędzi równocześnie.

## Lokalne środowisko i artefakt

Domyślna propozycja: natywne PHP 8.5 CLI/FPM, Nginx, PostgreSQL, Redis/Horizon, scheduler, Node 24 LTS i lokalny SMTP; Linux referencyjny, macOS z adapterem albo Linux VM. Docker/Compose/Sail nie są wymagane. Wersje, izolację projektów, onboarding, TLS i przyszłe komendy doctor/setup/start/stop/verify opisuje [ADR-021](10-local-environment-deployment-and-logs.md).

Artefakt P0 to archiwum o zweryfikowanej sumie SHA-256 z kodem/vendor/bundle klienta i SSR, zbudowane przez CI na zgodnym Linux/CPU. Ten sam artefakt trafia na staging i produkcję; config cache powstaje na hoście w nowym katalogu release’u. Bez sekretów w archiwum, bez ponownego rozwiązywania zależności na produkcji. Natywne usługi mają ograniczone konta i prawa; zapis tylko do wymaganych katalogów. Aktualizacje runtime wymagają osobnej kwalifikacji.

Nginx serwuje hashowane assety z `public, max-age=31536000, immutable`; zachowujemy stare assety dla otwartych kart zgodnie z retencją ADR-022. Publiczne media mają wersjonowane URL. HTML SSR, Inertia i odpowiedzi sesyjne bez współdzielonego proxy cache. Readiness bada prawdziwy SSR; pusty fallback klientowy nie spełnia bramki.

## GitHub Actions i Deployer

1. PR: format/static/types/tests/build/security z ograniczonym tokenem i syntetycznymi danymi; bez publikacji releasu i prod secrets.
2. Człowiek merge’uje PR po wymaganych checks i review. Zmiany workflow mają osobne review właściciela.
3. Po chronionym commicie CI buduje archiwum i zapisuje commit SHA, sumę SHA-256, lockfile, wyniki testów oraz manifest release’u. P0 nie wymaga obrazów ani GHCR.
4. Staging dostaje to samo zatwierdzone archiwum; migrator jest pojedynczy i kontrolowany, następnie smoke/UAT oraz próba kompatybilności schematu.
5. Człowiek zatwierdza manifest release’u (commit, migracje, wyniki, rollback) i inicjuje `dep deploy`. Recepta przygotowuje nowy SSR przed przełączeniem, wykonuje readiness/smoke oraz kontrolowane przeładowanie Horizon; nieudane kontrole uruchamiają ograniczony automatyczny rollback według ADR-022. Credentials SSH do produkcji są dostępne wyłącznie w tej ścieżce.
6. Serializacja produkcyjnych wdrożeń; nie przerywać aktywnej migracji automatycznym cancel. Re-run również wymaga właściwych bramek.

Actions przypięte pełnym SHA, default `contents:read`, minimalne uprawnienia per job. Zewnętrzne inputy traktować jako dane. Klucz SSH Deployer nie służy do interaktywnego logowania ani innych kont; host i fingerprint są przypięte. Jeśli P1 wprowadzi obrazy, ich provenance musi wiązać obraz z repo, workflow i commitem, nie tylko z faktem istnienia podpisu. [GitHub secure use](https://docs.github.com/en/actions/reference/security/secure-use).

Approval dla private repo sprawdzić w planie GitHub. Required reviewers mogą wymagać innego planu niż używany; jedno wskazane konto może wystarczyć do approval, więc nie zakładać quorum. Włączyć prevent self-review, ograniczyć ref i bypass. Jeśli mechanizm niedostępny: niezależna bramka hostingu albo manualne wdrożenie człowieka, bez credentiali produkcji w CI dostępnym agentowi. Brak wykonalnej bramki blokuje pierwsze wydanie. [Environments](https://docs.github.com/en/actions/how-tos/deploy/configure-and-manage-deployments/manage-environments).

## Migracje i release bez utraty danych

### ADR-016: expand/contract, rollback aplikacji oddzielny od danych

Status: proponowany. Nowe nullable kolumny/tabele i indeksy dodajemy przed kodem zależnym; backfill oddzielnym idempotentnym jobem z limitem; nowy i poprzedni kod działają ze schematem rozszerzonym. Usunięcie starego pola dopiero w późniejszym release po okresie obserwacji. Nie edytować migracji już wykonanej na współdzielonym środowisku. Każda zmiana migracji wymaga wyraźnej zgody człowieka.

Przed ryzykowną migracją: pomiar locków/czasu na reprezentatywnych syntetycznych danych, miejsce na dysku, świeży backup i możliwość restore. Długie backfille poza krytyczną ścieżką deployu. Backup nie jest pozwoleniem na niekompatybilną zmianę. Standardowe wydanie musi spełnić kontrakt zero-downtime. Niekompatybilną zmianę należy rozbić na expand/contract; odrębna operacja maintenance wymaga jawnej zmiany zakresu i nie jest nazywana bezprzerwowym deployem.

Rollback aplikacji w oknie deployu wykonuje automatycznie zatwierdzona recepta, najwyżej raz, do zapisanego zgodnego release’u. Poza tym oknem operator uruchamia ręczny rollback z kontrolą SSR, schematu, payloadów kolejki, workerów i smoke. Sam bazowy `dep rollback` nie zastępuje tej procedury. Stare joby muszą być kompatybilne lub queue wcześniej opróżniona zgodnie z planem. Nie wykonywać automatycznie `migrate:rollback`. Schemat naprawiać forward fix; restore bazy tylko po ocenie utraty zapisów od backupu, zatrzymaniu zapisów i akceptacji właściciela danych. Przywrócenie bazy i storage musi być spójne.

## Backup i disaster recovery

P0 propozycja do akceptacji: RPO ≤24 h, RTO ≤4 h dla strony firmowej. Aplikacja transakcyjna może wymagać PITR i niższego RPO przed pierwszym klientem. Codzienna kopia DB oraz mediów, szyfrowana, poza hostem i najlepiej poza tożsamością produkcyjnego runtime; retention roboczo 30 dni, alarm po nieudanym backupie lub przekroczeniu wieku 26 h. Konfiguracja infrastruktury i dostęp do kluczy uwzględnione w runbooku.

Przed produkcją odtworzyć kopię do izolowanej bazy/storage: sprawdzić liczby rekordów, integralność, login testowy i stronę; zmierzyć czas. P1 miesięczny restore drill, P0 ponowić po zmianie sposobu backupu. Odtwarzanie nie wysyła prawdziwych maili/webhooków. Operator musi odzyskać dane również przy niedziałającym Laravel/schedulerze — dlatego preferowany backup infrastruktury nad samą paczką w aplikacji.

## Obserwowalność i reakcja

Szczegółowy kontrakt zbierania, redakcji, rotacji, archiwizacji poza hostem i widoku administratora: [ADR-023](10-local-environment-deployment-and-logs.md). P0: pliki JSON + journal, osobny audit i ograniczona projekcja zdarzeń w panelu. Operator ma pełną diagnostykę; administrator bez surowych plików i stack trace. Archiwizacja co godzinę nie zastępuje natychmiastowych alarmów i zewnętrznego uptime.

Log JSON: UTC timestamp, level, service/environment, release ID/SHA, request ID, route name, duration, bezpieczny kod błędu i opcjonalny pseudonim aktora. Bez request body, cookies, query z PII, sekretów i pełnego SQL z parametrami. Jobs przejmują correlation ID; support otrzymuje tylko losowy ID błędu. Rozdzielić log techniczny i audit.

Liveness: proces żyje; readiness: minimalne zależności gotowe z krótkim timeoutem. Laravel `/up` traktować jako bazę, nie dowód działania maila/queue/DB. Szczegółowy health chroniony, publiczny endpoint bez nazw usług i sekretów. Nie restartować całej floty na chwilową awarię zewnętrznego mailera. Worker i scheduler mają osobne heartbeat; health weba nie wykrywa ich zatrzymania.

| Sygnał | Próg roboczy / wykrycie | Reakcja operatora |
| --- | --- | --- |
| Uptime | 2 nieudane próby zewnętrzne co 1 min | Zweryfikuj hosting, readiness i ostatni release |
| Błędy | Nowa regresja 500; >1% i ≥10 błędów/5 min | Korelacja z release, decyzja rollback/naprawa |
| Performance | p95 >1 s przez 10 min przy dostatecznym ruchu | Slow queries, zasoby, rozmiar payloadu |
| Queue | Najstarszy kontakt pending >5 min lub failed job | Worker, mail provider, bezpieczny replay |
| Scheduler | Brak oczekiwanego heartbeat przez 5 min | Sprawdź jedną aktywną instancję i blokady |
| Backup/dysk | Backup starszy niż 26 h; dysk >80% | Napraw backup/retencję i uniknij utraty zapisów |
| TLS | Wygaśnięcie za <14 dni | Sprawdź automatyczne odnowienie |

Wszystkie progi wymagają pomiaru i uzgodnienia godzin dyżuru. Alert musi mieć odbiorcę, link do runbooka, środowisko i release; brak alarmu bez właściciela. Test P0: kontrolowany błąd, zatrzymanie testowego workera i potwierdzenie dostarczenia alertu. APM dodatkowy dopiero gdy logi/metryki nie wyjaśniają problemu.

## Checklista przed wdrożeniem

- [ ] Człowiek zatwierdził manifest/sumę archiwum, migracje i automatyczny plan cofnięcia; approval nadal dotyczy tego artefaktu.
- [ ] Staging przeszedł CI, smoke, UAT i kontrolę braku sekretów w archiwum.
- [ ] Backup aktualny, restore sprawdzony; dla ryzykownej migracji wykonano dodatkową kopię.
- [ ] Poprzedni release i kompatybilność schematu/jobów sprawdzone.
- [ ] Operator, odbiorcy alertów, okno i kryterium rollbacku ustalone.
- [ ] TLS, cookies, debug off, storage, queue, scheduler i limity produkcyjne sprawdzone.

## Checklista po wdrożeniu

- [ ] Wdrożony manifest i suma archiwum zgadza się z zaakceptowanym; worker korzysta z nowej wersji.
- [ ] Publiczne strony/login/readiness działają; bez prywatnego debug.
- [ ] Kontrolowany smoke zapisu w dedykowanym kontekście lub test read-only zgodnie z runbookiem.
- [ ] Heartbeat, logi, mail i alerty działają; brak regresji podczas 30 minut obserwacji.
- [ ] Release notes i wynik kontroli zapisane; człowiek ogłasza sukces lub uruchamia rollback.

## Ryzyka

Pojedyncza VM oznacza przerwę przy jej awarii; kopia na tym samym dysku nie jest DR. Approval bez ochrony workflow jest pozorne. APM może przesyłać dane klienta i generować koszt. Nieznany hosting uniemożliwia zatwierdzenie konkretnych komend deployu i SLA.

## Otwarte pytania

Jaki hosting, region, plan GitHub i budżet? Kto jest operatorem i zastępcą? Czy VM ma zasoby dla dwóch instancji SSR podczas przełączenia? Jakie RTO/RPO, godziny wsparcia i retencja? Czy monitoring SaaS ma zgodę administratora danych? Jakie credentials i tożsamości wspiera dostawca?
