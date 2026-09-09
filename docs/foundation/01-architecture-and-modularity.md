# Architektura i modułowość

## Cel

Utrzymać krótką ścieżkę od wymagania do kodu, a jednocześnie ograniczyć sprzężenie modułów. Stan zastany: brak aplikacji. Poniższe nazwy katalogów i kontraktów są planem, nie istniejącą strukturą.

## Decyzje i uzasadnienie

### ADR-006: granice przez przypadki użycia, standardowy Laravel

Status: proponowany. `Identity` odpowiada za tożsamość; `Content` za strony, artykuły i publikację; `Media` za adminowy DAM; `Contact` za zgłoszenia i dostarczenie wiadomości. `Settings` i konkretne moduły biznesowe powstają dopiero przy potrzebie. Na start standardowe `app/Models`, `app/Policies`, `app/Http` oraz grupowane `app/Actions/Content`, `app/Actions/Media`, `app/Queries/Content`; frontend `resources/js/pages/content` i `components`. Nie kopiować szkieletu DDD z pustymi warstwami.

| Element | Odpowiedzialność | Zakaz |
| --- | --- | --- |
| Controller | Autoryzacja wejścia, wywołanie przypadku użycia, odpowiedź | Wieloetapowe procesy biznesowe w controllerze |
| FormRequest | Walidacja danych wejściowych, uprawnienie do operacji | Uznawanie walidacji za pełną ochronę zasobów |
| Policy | Dostęp do zasobu i operacji | Autoryzacja wyłącznie po stronie React |
| Action | Przypadek użycia, transakcja, niezmienniki | Zależność od HTTP/React lub globalnego requestu |
| Query | Jawny scope, projekcja, paginacja i eager loading | Nielimitowane eksporty/listy i N+1 |
| Eloquent model | Relacje, casts, małe reguły lokalne | Procesy integracyjne w observerach |
| Resource / readonly DTO | Jawny kontrakt danych | Serializacja całego modelu użytkownika |
| Job | Asynchroniczne wywołanie procesu z retry | Założenie, że wykona się dokładnie raz |

Przepływ: route → middleware → FormRequest/policy → Action/Query → jawna projekcja → Inertia SSR/React. Każdy zasób ma właściciela; inny moduł nie zapisuje jego tabel bezpośrednio. W P0 dopuszczalne relacje Eloquent do użytkownika i jawne odczyty, udokumentowane w review. Przy rzeczywistych zależnościach między modułami wyodrębnić metodę przypadku użycia lub kontrakt. `Shared` tylko dla stabilnych technicznych typów, nie dla przypadkowej logiki domenowej.

Alternatywa: pełne `app/Modules/<Name>/{Domain,Application,Infrastructure}`. Odłożona, bo początkowo zwiększa liczbę plików bez izolacji biznesowej. Przejście dopiero, gdy kilka procesów ma własnych właścicieli i granice dają wymierną korzyść. Nie używać generic repository nad Eloquent ani bazowego CRUD service.

### ADR-007: jeden backend, brak osobnego API dla własnego panelu

Status: proponowany. Inertia używa routingu i sesji Laravel; nie dodawać REST API ani tokenów tylko po to, by zasilić React. Dla zewnętrznego konsumenta: `/api/v1`, jawne Resources, paginacja, limits, kontrakt OpenAPI i osobno wybrany model uwierzytelnienia. API token nie zastępuje policy. Versioning potrzebny dla niezależnego konsumenta, nie dla każdego wewnętrznego DTO.

P0: ręczne, niewielkie typy props z testami kluczy i serializacji. Wayfinder, jeśli zatwierdzony z wybranym starterem, generuje **trasy i metody**, nie schematy odpowiedzi. P1: Laravel Data + TypeScript Transformer przy powtarzających się DTO; alternatywa to readonly PHP DTO bez paczki i generacja klienta z OpenAPI przy publicznym API. Pieniądze: integer w najmniejszej jednostce + waluta; daty ISO 8601 UTC; duże identyfikatory jako string; enum i nullable jednakowe po obu stronach. Nie nazywać rzutowania TS walidacją runtime.

Generowane pliki mają jedno źródło, deterministyczną komendę i kontrolę driftu w CI. Nie poprawiać ich ręcznie. FormRequest pozostaje źródłem walidacji wejścia; DTO nie może wprowadzać drugiego, rozbieżnego zestawu reguł. Wspólne typy nie mogą ujawniać pól prywatnych ani tworzyć zależności frontendu od całego modelu DB.

## Publiczny SSR i SEO

P0: React/Inertia SSR dla wszystkich publicznych tras. Semantyczny HTML, treść i metadane muszą być obecne w pierwszej odpowiedzi bez wykonania JavaScriptu. Node SSR jest osobnym procesem z health checkiem, monitoringiem, restartem przy deployu i kontrolą błędów; nie dodawać Next.js tylko dla SEO.

Zasady: unikalny title/description; canonical z kontrolowanego origin, nie dowolnego Host; OG; poprawny język dokumentu; sitemap wyłącznie opublikowanych, kanonicznych URL z rzeczywistym lastmod; robots jako wskazówka dla robotów, nigdy autoryzacja. Draft i preview chronione policy, cache-control private/no-store i noindex. Staging za auth oraz noindex. Hreflang tylko przy realnych wersjach językowych. Structured data wyłącznie zgodne z widoczną treścią i typem działalności. [Oficjalne zasady Google](https://developers.google.com/search/docs/fundamentals/seo-starter-guide).

Zmiana sluga: transakcyjny zapis nowego sluga + unikalność + rejestr starego → nowy, 301 bez łańcuchów i pętli. Cel przekierowania lokalny i walidowany; bez open redirects. 404 dla braku zasobu, 410 dla trwale usuniętego, jeśli uzasadnione SEO. Publikacja/unpublish aktualizuje sitemap i cache po commit. Strona bez publikacji nie może wyciec przez wyszukiwarkę panelu dostępną gościowi.

## Błędy i spójność danych

| Przypadek | Odpowiedź | Obsługa |
| --- | --- | --- |
| Niepoprawne dane | API 422 + errors; Inertia redirect z error bag | Komunikat przy polu, zachowanie bezpiecznych danych |
| Brak sesji/uprawnień | API 401/403; web login/403 | 404 zamiast ujawnienia istnienia zasobu tam, gdzie wymagane |
| Konflikt stanu/wersji | 409 dla API, komunikat formularza dla web | Przeładuj aktualne dane, nie nadpisuj cudzej edycji |
| Limit | 429 i Retry-After | UI komunikuje czas oczekiwania |
| Awaria zależności | Kontrolowane 503 lub stan pending | Timeout, retry tylko dla operacji bezpiecznych |
| Nieoczekiwany błąd | 500 i identyfikator błędu | Stack tylko w chronionym monitoringu |

Błąd domenowy ma stabilny kod, np. `page_already_published`, i bezpieczny komunikat. API: `code`, `message`, `errors` gdy walidacja, `request_id`; nie wycieka SQL, nazwa bucketa, ścieżka pliku ani sekret. ID generowane po stronie serwera, propagowane do jobów i logów; wejściowy correlation ID sprawdzany co do długości i formatu. Komunikat dla supportu nie służy do publicznego odczytu logów.

Transakcja obejmuje zapis i jego wymagany audit. Zewnętrznych wywołań HTTP nie trzymać wewnątrz długiej transakcji. Walidacja biznesowa wewnątrz Action ponownie sprawdza stan podatny na wyścig. Unikalność gwarantuje DB, nie tylko request. Przy edycji sprawdzać wersję lub `updated_at`; dla konkurencyjnych operacji użyć locka DB w transakcji.

## Dostęp i audit

Stałe role P0: admin i editor; capability `pages.view`, `pages.update`, `pages.publish` definiowane jawnie przez policies. Editor nie zarządza rolami ani bezpieczeństwem; do rozstrzygnięcia, czy publikuje. Nie wprowadzać edytora dowolnych uprawnień w P0. UI otrzymuje flagi `can`, ale backend za każdym razem sprawdza dostęp. Audit: aktor, akcja, zasób, wynik, timestamp UTC, request ID, zredagowana lista zmian. Szczegóły i wymagane approval w [02](02-security-and-access.md).

## Cache, kolejki, scheduler i integracje

P0: Redis oraz Horizon dla kolejki i kontrolowanego przeładowania workerów, scheduler do czyszczenia danych i Redis cache wyłącznie dla jawnie bezpiecznych odczytów. Cache nigdy nie stanowi źródła prawdy. Klucze uwzględniają projekt, środowisko, język i kontekst dostępu; jawne TTL i invalidacja po publikacji, zmianie sluga lub usunięciu. Odpowiedzi Inertia i HTML SSR nie dostają współdzielonego response cache'u domyślnie; można go dodać wyłącznie dla anonimowej, opublikowanej treści po teście braku personalizacji i poprawnym `Vary`.

Kolejka: dispatch po commit; retry z backoff np. 10/60/300 s, maksymalnie 3 próby dla maila, timeout np. 30 s i `retry_after` dłuższe niż timeout. To wartości startowe do testu. Failed jobs mają alarm i runbook replay. Worker restartowany po zmianie obrazu. `onOneServer`/`withoutOverlapping` wymaga odpowiedniego współdzielonego locka; w P0 jeden scheduler. Brak równoległych migratorów.

Idempotencja: unikalny klucz operacji i stan przetwarzania w DB, nie sama blokada cache. Dla kontaktu najpierw zapisz zgłoszenie i status dostarczenia, potem job. Scheduler odnajduje pending niedispatchowane po awarii. Crash po dostarczeniu maila, przed zapisem statusu nadal może dać duplikat: w P0 udokumentować możliwość, w integracjach krytycznych użyć idempotency key dostawcy i outboxa. Ponowne żądanie nie może tworzyć drugiego zgłoszenia z tym samym kluczem. Dead-letter/replay nie może omijać aktualnych policies i retencji.

Webhook P1: podpis na surowym body, timestamp i okno replay, deduplikacja ID zdarzenia w DB, zaakceptowanie dopiero po trwałym zapisie, asynchroniczna obsługa i bezpieczny retry. Wywołania wychodzące: limity czasu, allowlista hostów, brak dowolnych URL od użytkownika, kontrola redirectów i adresów prywatnych przeciw SSRF.

## Media i strategia CRUD

P0 zawiera wyłącznie adminowy DAM. `MediaAsset` ma właściciela, status kwarantanny/clean/rejected, metadane, policy i audit; storage jest poza katalogami release'ów na lokalnej VM oraz objęty backupem. Asset jest prywatny do chwili świadomego podpięcia do opublikowanej treści, kiedy może dostać publiczny wariant. Limit wynosi 50 MB; każdy plik przechodzi sprawdzenie typu po zawartości i skan, a niedostępny skaner blokuje publikację. Tylko obrazy są transformowane asynchronicznie do WebP/AVIF; inne pliki nie dostają automatycznego preview lub konwersji. Uploady zwykłych użytkowników są osobnym przyszłym modułem, z własnym storage, modelem własności i policies, bez dostępu do DAM.

Wzorzec CRUD obejmuje listę z paginacją, allowlistę sortowania, create/edit, walidację, policy, audit, confirmation dla usunięcia i test odmowy. Pierwszy CRUD napisać jawnie. Po drugim porównać powtarzalność; generator ma emitować zwykłe pliki i testy, bez runtime metaprogramowania, klas BaseEverything i automatycznego nadpisywania ręcznych zmian. Bulk actions, eksporty i soft-delete są osobnymi przypadkami użycia. Nie zakładać, że wszystkie modele mają te same uprawnienia i cykl życia.

## Ryzyka

- Pozorna modularność folderów bez reguł zapisu tabel; kontrolować zależności w review.
- Wysyłka maila nie jest atomowa z DB; projektować recovery, nie obiecywać exactly-once.
- Generacja typów może ukryć różnicę między typem PHP a serializowanym JSON.
- Cache może ujawnić dane użytkownika, a SSR zwiększyć powierzchnię operacyjną.

## Checklista

- [ ] Moduł ma opis właściciela danych, proces i jawne zależności.
- [ ] Controller nie wykonuje długiego procesu; policy chroni każdą operację.
- [ ] Test kontraktu obejmuje null, enum, datę, paginację i pola wrażliwe.
- [ ] Zapisy mają transakcję, obsługę konkurencji oraz audit tam, gdzie potrzebny.
- [ ] Job ma limit, retry, deduplikację i ścieżkę naprawy.
- [ ] Draft, sesja i token CSRF nie trafiają do współdzielonego cache.

## Otwarte pytania

Czy editor może publikować? Jak długo utrzymywać stare URL? Czy zaakceptowana jest sporadyczna podwójna wiadomość kontaktowa? Który skaner malware i sanitizer rich textu przechodzą kwalifikację? Decyzje przed odpowiednim modułem, nie blokują napisania planu.
