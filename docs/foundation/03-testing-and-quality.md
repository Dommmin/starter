# Testy i jakość

## Cel

Mały zestaw szybkich, wiarygodnych testów ma wykrywać błędy biznesowe i bezpieczeństwa przed klientem. Nie ma obecnie kodu ani testów; poniższe progi są proponowaną bramką wdrożenia, a nie raportem pokrycia.

## Decyzje i uzasadnienie

### ADR-010: feature-first, jeden runner PHP i jeden E2E

Status: proponowany. PHPUnit 12.5 jako punkt wyjścia zgodny z ocenionym oficjalnym starterem; PHP 8.5 spełnia jego wymagania. PHPUnit 13 jest alternatywą po teście integracji. Pest opcjonalny wybór zespołu, nie drugi zestaw testów. Feature/integration stanowią główną warstwę, jednostkowe dla reguł, a Playwright dla kilku podróży użytkownika. [PHPUnit support](https://phpunit.de/supported-versions.html), [Playwright](https://playwright.dev/docs/intro).

Nie mockować Eloquent po to, żeby testy były „unit”. Testować wynik biznesowy i odmowę dostępu. DB w CI tej samej rodziny i majora co produkcja; SQLite tylko dla testów, których semantyka nie zależy od SQL, nie jako substytut wszystkich integracji.

## Macierz testów

| Warstwa             | Zakres                                                      | Minimalna bramka P0                                                          |
| ------------------- | ----------------------------------------------------------- | ---------------------------------------------------------------------------- |
| Unit                | Reguły publikacji, wartości, mapowanie błędów, deduplikacja | Każda nietrywialna reguła: sukces, granica i odmowa                          |
| Feature/integration | Auth, MFA, policies, CRUD, transakcje, mail, queue          | Każdy endpoint zmieniający stan: sukces, zła walidacja, brak uprawnienia     |
| Contract            | Inertia props, jawne pola JSON, enum/null/data, paginator   | Każda projekcja panelu i publiczna odpowiedź P0; po dodaniu API także schema |
| E2E                 | Realna sesja, nawigacja, formularz, publikacja              | 5 podróży opisanych poniżej, Chromium na każdym PR                           |
| Smoke               | Artefakt i środowisko po wdrożeniu                          | Publiczne 200, login, readiness, job heartbeat, brak debug                   |
| Accessibility       | HTML, formularze, focus, nazwy elementów                    | Automat krytycznych stron + ręczna klawiatura                                |
| Visual regression   | Stałe elementy UI przy istotnych zmianach wyglądu           | Manualny odbiór P0; automatyczne baseline dopiero P1                         |

Pięć E2E P0: (1) admin login → TOTP → panel → logout; (2) jednorazowy reset przez testową skrzynkę i odrzucenie ponownego użycia tokenu; (3) editor tworzy draft, gość go nie widzi, uprawniony publikuje i strona pojawia się publicznie; (4) próba niedozwolonej operacji editor i weryfikacja braku zmiany; (5) kontakt: błędy formularza, poprawne zgłoszenie, dostarczenie w testowym środowisku. Recovery code i weryfikacja e-mail pokryte feature; krytyczne ścieżki można rozdzielić na niezależne testy, nie jeden zależny scenariusz.

## Minimalne progi startowe

| Bramka          | Próg                                                                                                     |
| --------------- | -------------------------------------------------------------------------------------------------------- |
| Testy           | 100% obowiązkowych testów przechodzi; brak quarantined testu krytycznego                                 |
| Autoryzacja     | 100% zidentyfikowanych uprzywilejowanych endpointów ma test odmowy, także bulk/export                    |
| Pokrycie        | Co najmniej 80% linii własnych Actions i Policies; raport całej aplikacji informacyjny                   |
| Static analysis | Larastan/PHPStan level 6, zero błędów i brak nowego baseline; docelowo 8 po P1                           |
| Frontend        | TS strict; zero błędów typecheck; `ui-contract` dla całości źródeł, zero naruszeń poza ważnymi wyjątkami |
| Formatowanie    | Pint i jeden wybrany formatter frontendowy przechodzą check bez mutowania CI                             |
| Security        | Zero sekretów; brak high/critical bez udokumentowanej dyspozycji człowieka                               |
| A11y            | Zero critical/serious z axe na kluczowych stronach; manualna klawiatura/focus                            |
| CI              | Cel p95 do 10 min dla PR; czas mierzyć i poprawiać bez usuwania bramek                                   |

Pokrycie nie jest dowodem poprawności. Nie pisać testu samego gettera, skopiowanego vendora, deklaracji koloru czy tekstowego odzwierciedlenia implementacji. Pliki generowane wyłączyć z mianownika, ale testować ich źródło i deterministyczną generację. Jeżeli denominator wynosi zero, wynik „nie dotyczy”, nie fikcyjne 100%.

## Testy LLM-safe UI contract — obowiązkowe P0

Kontrakt i proponowane nazwy reguł określa [ADR-019](05-design-system-and-frontend.md). To testy zabezpieczenia granic API, nie testy kopiujące wartości kolorów. `ui-contract` blokuje PR przy dowolnym naruszeniu poza zatwierdzonym, aktywnym wyjątkiem. Sprawdza całe TS/TSX i CSS; wyłączenie reguły, parser error lub zmiana globów nie może dawać pozornego PASS.

| Kontrola                | Pozytywny dowód                                                          | Negatywne przypadki wymagane w fixtures                                                                                                            |
| ----------------------- | ------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| Reguły AST / RuleTester | Zatwierdzony primitive i ekran składający publiczne komponenty           | Bezpośrednie i spread `className`/`style`, alias helpera, template literal utility, raw kolor, `dark:`, obejście przez relative import/re-export   |
| Publiczne typy TS       | Skończone props layoutu/typografii i dozwolone aria/events kompilują się | Nieznany wariant, dowolny gap, style/className, nieograniczony `asChild`; compile-fail fixtures sprawdzają oczekiwany diagnostic, nie dowolny błąd |
| CSS i SSR               | Definicje theme tokens oraz te same komponenty React w SSR               | Inline style, CSS module/import poza dozwoloną warstwą, lokalny selektor dark, różnica HTML/meta SSR względem oczekiwanego kontraktu               |
| Manifest tokenów        | Identyczne klucze light/dark, wszystkie referencje rozwiązane            | Brak wartości w dark, cykl aliasów, surowa paleta poza definicjami, nowy token/wariant bez rekordu decyzji                                         |
| Wyjątki                 | Marker, symbol i rejestr zgodne; review i termin ważne                   | Nieznane ID, expiry, nieużyty wpis, wildcard, zmieniony fingerprint, globalne disable                                                              |
| Egzekwowanie CI         | Reprezentatywny zestaw poprawnych fixtures przechodzi                    | Kontrolne wstrzyknięcie każdego rodzaju naruszenia powoduje non-zero status rzeczywistej komendy CI; testuj też nowy plik poza historycznym globem |
| Motywy w przeglądarce   | Te same prymitywy i krytyczne ekrany w light/dark, zmiana root theme     | Brak tokena, nieczytelna para tło/tekst, focus niewidoczny, błysk motywu; sprawdzić błędy/disabled/hover                                           |

Zestaw lint/typy/manifest/wyjątki na każdym PR, krótki smoke i axe dla obu motywów na loginie oraz formularzu strony. Pozostałe E2E nie muszą być podwajane. Manualny przegląd kontrastu/focus w obu motywach; brak pełnej automatycznej visual regression w P0 nie zwalnia z tych testów. Przy migracji ESLint→Oxlint identyczne fixtures muszą dać te same findings; brakujące reguły pozostają w referencyjnym runnerze. Nie używać `any`, suppress lub globalnego ignore do zaakceptowania negatywnego testu jako kodu aplikacji.

Fixtures celowo niepoprawne mają dokładnie wydzielony katalog i są uruchamiane przez harness, nie pomijane bez sprawdzenia wyniku. Testy runtime publicznych komponentów potwierdzają brak forwardowania niedozwolonych props do DOM. Wewnętrzne style bibliotek pozycjonowania sprawdzamy w ich zarejestrowanym adapterze, a nie zakazem dowolnego atrybutu style w całym wynikowym DOM.

## Konkretne przypadki regresji

- Auth: konto nieweryfikowane, wyłączone, brak MFA, błędny TOTP, zużyty recovery code, limity i próby enumeracji.
- Policies: gość/editor/admin i dostęp do innego zasobu; zmiana ID w URL i body; role nie mogą wejść przez mass assignment.
- Formularze: brak wartości, granice długości, nieznane enumy, XSS, błędny format, konflikt równoczesnej edycji.
- Błędy: bez stack trace i sekretów; request ID widoczny w komunikacie oraz logu; niewłaściwy input correlation ID odrzucony.
- Queue/mail: fake do sprawdzenia dispatch i treści, prawdziwy driver w teście retry/recovery; wyjątek po commit, timeout dostawcy, drugi job o tym samym kluczu.
- Transakcje: awaria audytu rollbackuje krytyczną mutację; constraint DB odrzuca duplikat mimo równoległych requestów.
- Pliki DAM P0: limit, fałszywe MIME, nieuprawnione pobranie, skaner down, plik w kwarantannie, usuwanie wariantów.
- Integracje P1: sandbox dostawcy, złe podpisy i replay webhooka, timeouty, zmiana schematu; testy PR bez zewnętrznego Internetu.
- SEO: raw HTML zawiera title/canonical/treść, draft nie ma w sitemap, stary slug 301 bez pętli, prawidłowe 404.

## Odbiór środowiska, deployu i logów

Obowiązkowe scenariusze operacyjne określa [dokument 10](10-local-environment-deployment-and-logs.md): onboarding/doctor, ciągły ruch podczas przełączenia i automatycznego rollbacku, zgodność SSR i starych assetów, zerwane SSH, wersja workerów, odmowy dostępu do diagnostyki, redakcja sekretów, rotacja i dostarczenie zewnętrznego alarmu. Testy uprawnień/redakcji uruchamiać przy zmianach tych kontraktów; pełną próbę deployu na stagingu przed pierwszą produkcją i po zmianach recepty/runtime/infrastruktury. Nie uruchamiać całego testu awarii infrastruktury dla zwykłej zmiany tekstu.

## Dane testowe i izolacja

Factories z jawnymi stanami `verified`, `admin`, `mfa_enabled`, `draft`, `published`. Seedery demonstracyjne wyłącznie local/testing, bez stałego hasła produkcyjnego. Syntetyczne dane, deterministyczny zegar i kontrolowane UUID tam, gdzie potrzebne. Osobna DB/schema per proces; prefiks cache, kolejki i storage per run; sprzątanie po E2E. Żaden test nie używa URL, credentials ani kopii DB produkcji.

E2E: testowy mail sink, automatyczne przygotowanie użytkownika bez omijania auth w testowanej podróży, sekrety TOTP wyłącznie syntetyczne. Zrzuty i trace mogą zawierać dane; krótka retencja i brak prawdziwych danych. Retry E2E maksymalnie raz diagnostycznie; test, który przeszedł dopiero za drugim razem, traktować jako flaky do naprawy, nie „zielony dowód” bez wyjaśnienia.

## Automatyczna jakość i pipeline

Etapy: walidacja manifestów/lockfile → restore zależności z lockfile → format/static/types → testy DB/kontraktów → build frontend → E2E → skany → wymagany zbiorczy wynik. Nie uruchamiać produkcyjnych migracji w CI testowym. Testy architektury P0 ograniczyć do importów zabronionych między modułami i braku HTTP w Actions; najpierw jawna reguła w review, dodatkowy framework architektoniczny tylko jeśli ręczny koszt rośnie.

Skanowanie: Composer audit, npm audit, Gitleaks CLI, skan zależności i zawartości finalnego archiwum (narzędzie zakwalifikowane w P0-A). Nowa zależność przechodzi rejestr 07. Awaria pobrania bazy podatności to brak wyniku, nie PASS. High/critical blokują; fałszywy alarm lub brak zastosowania wymaga dowodu i zatwierdzenia człowieka z datą wygaśnięcia. Dla aktywnie wykorzystywalnej luki dostępnej w produkcji — poprawka przed wydaniem. Pozostałe wyjątki mają właściciela i deadline, nie wieczne ignores. [Composer audit](https://getcomposer.org/doc/03-cli.md#audit), [npm audit](https://docs.npmjs.com/cli/v11/commands/npm-audit/).

Frontend tooling jest do potwierdzenia: oceniony upstream starter używa już Vite Plus; nie zakładać historycznego zestawu skryptów ESLint/Prettier. Po przypięciu wersji wybrać jeden zestaw format/lint i udokumentować realne komendy. Dla kontraktu DS referencyjny ESLint; gdy upstream używa Oxlint, pozostawić ESLint tylko dla brakujących kontroli zgodnie z ADR-019, bez dublowania pozostałych reguł. [Manifest upstream](https://raw.githubusercontent.com/laravel/react-starter-kit/main/package.json).

## Commity i Lefthook — kontrakt P0-A

Status: wdrożone lokalnie 2026-09-10: `lefthook`, `@commitlint/cli`, `@commitlint/config-conventional`, wersjonowane `.githooks` i Gitleaks 8.30.1. Repo zawiera dwa historyczne tytuły (`init`, `Feat: Install laravel`), więc poniższe zasady ustanawiają nową konwencję, a nie opisują utrwalonego wzorca. Nie przepisywać dotychczasowej historii. Zdalne required checks wymagają jeszcze konfiguracji administratora GitHub.

### Konwencja commitów

Przyjmujemy [Conventional Commits 1.0.0](https://www.conventionalcommits.org/en/v1.0.0/) z poniższymi ograniczeniami projektu:

- Format: `type(scope): opis`, opcjonalny scope i `!` przed dwukropkiem dla breaking change.
- Dozwolone typy, małymi literami: `feat`, `fix`, `refactor`, `perf`, `test`, `docs`, `build`, `ci`, `chore`, `style`, `revert`. `style` oznacza formatowanie bez zmiany zachowania; zmiana wyglądu/interakcji UI to odpowiednio `feat` albo `fix`.
- Tytuł do 72 znaków, bez końcowej kropki; opis po polsku, konkretny czasownik, np. `fix(auth): odrzuć ponowne użycie kodu odzyskiwania`. Identyfikatory i nazwy techniczne pozostają oryginalne. Język i sens ocenia reviewer, walidator sprawdza strukturę.
- Scope małymi literami, nazwany obszarem: np. `auth`, `pages`, `media`, `ui`, `deps`, `docker`, `agents`. To przykłady, nie zamknięty katalog; zmiana przekrojowa może nie mieć scope.
- Jeden commit obejmuje jeden cel, wraz z jego testami. Nie mieszać feature z porządkowaniem niezwiązanych plików. Przy nietrywialnej zmianie body wyjaśnia powód; wynik testów i ograniczenia zapisuje się w PR. `Refs: #123` tylko dla istniejącego zadania.
- Breaking change wymaga w projekcie zarówno `!`, jak i footera `BREAKING CHANGE: ...` z wpływem i ścieżką migracji. `revert` wskazuje pełny SHA cofanej zmiany w body; nie stanowi zgody na rollback produkcji.
- Tytuł PR ma ten sam format. Domyślnie squash merge wykonywany przez człowieka; końcowy tytuł squasha musi odpowiadać zweryfikowanemu tytułowi PR. Automatyczne releasy i wersjonowanie nie są częścią tej zmiany.
- Walidacja obejmuje nowe commity po wdrożeniu bramki i tytuł PR, bez retroaktywnej walidacji starej historii. Nie dopuszczać `WIP`, `fixup!` ani `squash!` w nowych commitach; poprawki dodawać jako kolejne poprawnie nazwane commity, a squash pozostawić maintainerowi.

### Podział kontroli

Lefthook jest lokalnym runnerem; [instaluje hooki Git](https://lefthook.dev/usage/commands/install/). Wersję kwalifikujemy i przypinamy w P0-A. Walidator: `@commitlint/cli` + `@commitlint/config-conventional` z jawnymi regułami projektu, ten sam lokalnie i w CI. Dodanie zależności wymaga autoryzacji wdrożenia. Wersjonowane `lefthook.yml` i skrypty projektu stanowią wspólny kontrakt.

| Miejsce | Obowiązkowe kontrole | Zachowanie |
| --- | --- | --- |
| `pre-commit` | Kontrola brancha, konfliktów, `git diff --cached --check`, staged secrets przez Gitleaks; format/lint dla zmienionych plików PHP/TS/TSX/CSS/JSON/Markdown; testy kontraktu agentów przy zmianie ich konfiguracji | Błąd blokuje commit; cel p95 ≤30 s na rozgrzanym środowisku, pomiar podczas pilotażu |
| `commit-msg` | Walidacja pełnej wiadomości z pliku przekazanego przez Git, w tym tytułu i breaking change | Bez przepisywania wiadomości; czytelny błąd z poprawnym przykładem |
| Przed przekazaniem PR | Adekwatne testy przez `make test ARGS='...'`, typy i analiza według diffu; pełne istniejące kontrole przez `make check` przed oznaczeniem PR jako gotowy | Raport rzeczywistych wyników; nie uruchamiać całego E2E i audytów sieciowych przy każdym commicie |
| Wymagane CI | Tytuł PR/nowe commity, pełne format/lint/types/static/testy, skan sekretów, kontrakty agentów i docelowo `ui-contract` według niniejszego dokumentu | Niezależne od lokalnych hooków, obowiązkowe również po ich pominięciu; brak wyniku nie jest PASS |

Nie dodajemy na start ciężkiego `pre-push` dublującego CI. Hooki niczego nie instalują ani nie pobierają z sieci podczas commita. Bootstrap/doctor mają sprawdzać instalację hooków i przypiętych narzędzi. Brak wymaganej binarki, runtime, timeout lub błąd konfiguracji kończy kontrolę niepowodzeniem z instrukcją naprawy, nigdy cichym pominięciem.

Git i launcher hooka działają na hoście; PHP/Node i narzędzia jakości przez kontrakt Docker/Makefile. W P0-A dodać jawne cele Makefile dla instalacji i kontroli hooków; nie zakładać, że obecny `make setup` je instaluje. Instalacja z kontenera nie może pozostawić hostowego hooka wymagającego nieistniejącego hostowego Node ani linuksowej binarki. Istniejący `core.hooksPath` lub obcy hook wymaga rozpoznania i uzgodnionej integracji; bez automatycznego nadpisania.

### Integralność indeksu i zakresu kontroli

Hooki działają wyłącznie w trybie check, bez `--fix`, automatycznego `git add`, stashowania i przywracania plików. Pozostawić `stage_fixed: false`: [włączenie tej opcji powoduje automatyczne dodanie plików do indeksu](https://lefthook.dev/configuration/stage_fixed/). Formatowanie wykonać jawnie przed stagingiem i ponownie obejrzeć diff.

Kontrole sekretów/konfliktów sprawdzają zawartość indeksu, nie tylko working tree. Lista [staged files](https://lefthook.dev/configuration/run/) wskazuje ścieżki, nie gwarantuje odczytu staged content. Dla lintowania pliku z jednoczesnymi zmianami staged i unstaged P0 przerywa commit z informacją o częściowym stagingu; nie dołącza automatycznie reszty pliku. Dozwolone jest przygotowanie mniejszego commita przez użytkownika. Późniejszy izolowany snapshot indeksu wymaga osobnych testów.

Obsłużyć bezpiecznie nazwy ze spacjami/Unicode, rename, delete, pierwszy commit i puste listy plików; bez interpolowania ścieżek lub wiadomości jako kodu powłoki. Pusta lista dla konkretnego lintera może być „nie dotyczy”, lecz nie wyłącza kontroli globalnych. Zmiana konfiguracji lintera, hooków lub ich skryptów uruchamia pełną odpowiednią kontrolę. Nie czytać ani nie wypisywać zawartości lokalnych `.env` dla diagnostyki; raport skanera redaguje znalezione wartości. Śledzone szablony `.env.example`/`.env.docker.example` również podlegają skanowi.

### Odbiór hooków i walidatora

- [ ] Poprawne `docs(agents): opisz zasady commitowania` przechodzi; błędny typ, pusty opis, tytuł ponad limit i breaking change bez footera są odrzucane. Sprawdzone scope, brak scope, `revert` i wiadomość wieloliniowa.
- [ ] Syntetyczny sekret, konflikt i błąd formatowania blokują rzeczywisty `git commit` w tymczasowym repo; poprawny commit przechodzi. Fixtures sekretów nie zawierają prawdziwych credentiali.
- [ ] Częściowy staging, rename/delete i nazwy ze spacjami/Unicode dają oczekiwany wynik; sukces i błąd hooka pozostawiają indeks oraz working tree bez zmian poza efektem samego poprawnego commita.
- [ ] Brak narzędzia/Dockera i timeout blokują commit; docs-only nie uruchamia DB/E2E. Hooki działają po świeżym klonie na wspieranych hostach.
- [ ] Syntetyczny PR z pominiętymi hookami nadal odpada w CI; zmiana samego tytułu PR ponawia jego walidację. Wiadomości/tytuły są przekazywane jako dane, nie kod shell.

## AI i definicje done

AI wyprowadza testy z zaakceptowanych kryteriów, nie jedynie z własnego kodu. Reviewer dopisuje lub wskazuje przypadek nadużycia pominięty przez implementera. Naprawa błędu ma reprodukcję przed poprawką. Zmniejszenie progu, usunięcie testu, snapshot update i suppress wymagają uzasadnienia i niezależnego review. Agent raportuje komendę, wynik, ograniczenia; nigdy nie nazywa nieuruchomionego testu „zaliczonym”.

**Feature done:** spełnione AC, test pozytywny i negatywny, autoryzacja, błędy/stany UI, dokumentacja i telemetryka adekwatne do zmiany. **PR done:** scope zgodny ze spec, CI zielone, diff i zależności przejrzane przez człowieka, approvals ryzykownych zmian dołączone. **Release done:** dokładne archiwum z manifestem i sumą SHA-256 przeszło staging, człowiek zaakceptował produkcję, smoke i monitoring po wdrożeniu zaliczone, rollback/restore dostępne, release notes zapisane.

Test automatyczny obowiązkowy dla auth, policies, migracji, walidacji, logiki, integracji i napraw regresji. Manualny wystarcza dla dokumentacji, prostego tekstu i małej korekty wyglądu bez zmiany zachowania; wynik i zakres zapisać w PR. Zmiana focus/navigation wymaga testu zachowania, a nie wyłącznie screenshotu.

## Ryzyka

Progi mogą zachęcać do testów pozornych; review powinno oceniać asercje. Mocki mogą ukryć różnice DB i kolejki. Skanery mają false positives i false negatives. Automatyczne a11y nie zastępuje manualnego sprawdzenia czytnika ekranu i klawiatury.

## Checklista

- [ ] Kryteria mają mapowanie na test lub jawne uzasadnienie manualnej weryfikacji.
- [ ] Negatywne testy sprawdzają również brak skutku ubocznego w DB/queue/mail.
- [ ] CI pracuje na produkcyjnej rodzinie DB i izolowanych danych.
- [ ] Znane flaky testy i wyjątki bezpieczeństwa mają właściciela i termin.
- [ ] Reviewer człowiek zaakceptował wynik niezależnie od agenta.

## Otwarte pytania

PHPUnit czy preferowany przez zespół Pest? Jaki runner CI i budżet minut? Czy klient wymaga konkretnych przeglądarek, poziomu dostępności lub formalnego pentestu? Jakie scenariusze pierwszego klienta zastąpią demonstracyjny CRUD?
