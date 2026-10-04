# Architektura i modułowość

## Cel

Utrzymać krótką ścieżkę od wymagania do kodu, a jednocześnie ograniczyć sprzężenie modułów. Aplikacja już istnieje. Kontrakt warstw i typowania poniżej zaakceptowano 2026-09-10; opisuje standard docelowy, nie potwierdzenie migracji istniejącego kodu ani instalacji paczek. Katalogi powstają wraz z rzeczywistą funkcjonalnością.

## Decyzje i uzasadnienie

### ADR-006: granice przez przypadki użycia, standardowy Laravel

Status: kontrakt warstw zaakceptowany 2026-09-10; wymienione moduły pozostają docelowym podziałem. `Identity` odpowiada za tożsamość; `Content` za strony, artykuły i publikację; `Media` za adminowy DAM; `Contact` za zgłoszenia i dostarczenie wiadomości. `Settings` i konkretne moduły biznesowe powstają dopiero przy potrzebie. Na start standardowe `app/Models`, `app/Policies`, `app/Http` oraz grupowane `app/Actions/Content`, `app/Actions/Media`, `app/Repositories/Content`, `app/Data/Content`, `app/Enums`; frontend `resources/js/pages/content` i `components`. Nie kopiować szkieletu DDD z pustymi warstwami.

| Element                 | Odpowiedzialność                                           | Zakaz                                           |
| ----------------------- | ---------------------------------------------------------- | ----------------------------------------------- |
| Controller              | Autoryzacja wejścia, wywołanie przypadku użycia, odpowiedź | Wieloetapowe procesy biznesowe w controllerze   |
| FormRequest             | Walidacja danych wejściowych, uprawnienie do operacji      | Uznawanie walidacji za pełną ochronę zasobów    |
| Policy                  | Dostęp do zasobu i operacji                                | Autoryzacja wyłącznie po stronie React          |
| Action                  | Przypadek użycia, transakcja, niezmienniki                 | Zależność od HTTP/React lub globalnego requestu |
| Repository / opcjonalny Query | Zapytania aplikacyjne, projekcja, paginacja i eager loading | Globalny request, nielimitowane listy i N+1 |
| Service | Spójna współdzielona funkcjonalność lub integracja | Przekazywanie wszystkich wywołań 1:1 do Repository |
| Eloquent model          | Relacje, casts, małe reguły lokalne                        | Procesy integracyjne w observerach              |
| Laravel Data / API Resource | Jawny kontrakt danych; Data dla Inertia | Serializacja całych modeli i dublowanie walidacji |
| Job                     | Asynchroniczne wywołanie procesu z retry                   | Założenie, że wykona się dokładnie raz          |

Zapis: route → middleware → FormRequest/policy → Controller → Action → Repository; odpowiedź przez Data → Inertia/React. Odczyt: Controller → Repository (opcjonalnie wyspecjalizowany Query) → Data. Service dołączamy tylko, gdy wnosi współdzieloną funkcjonalność. Każdy zasób ma właściciela; inny moduł nie zapisuje jego tabel bezpośrednio. W P0 dopuszczalne relacje Eloquent do użytkownika i jawne odczyty, udokumentowane w review. Przy rzeczywistych zależnościach między modułami wyodrębnić metodę przypadku użycia lub kontrakt. `Shared` tylko dla stabilnych technicznych typów, nie dla przypadkowej logiki domenowej.

Alternatywa: pełne `app/Modules/<Name>/{Domain,Application,Infrastructure}`. Odłożona, bo początkowo zwiększa liczbę plików bez izolacji biznesowej. Przejście dopiero, gdy kilka procesów ma własnych właścicieli i granice dają wymierną korzyść. Nie używać generic repository nad Eloquent ani bazowego CRUD service.

### ADR-007: jeden backend, brak osobnego API dla własnego panelu

Status: proponowany. Inertia używa routingu i sesji Laravel; nie dodawać REST API ani tokenów tylko po to, by zasilić React. Dla zewnętrznego konsumenta: `/api/v1`, jawne Resources, paginacja, limits, kontrakt OpenAPI i osobno wybrany model uwierzytelnienia. API token nie zastępuje policy. Versioning potrzebny dla niezależnego konsumenta, nie dla każdego wewnętrznego DTO.

P0 — wymaganie zaakceptowane 2026-09-10: Laravel Data + TypeScript Transformer są obowiązkowym standardem kontraktów Inertia, zamiast wcześniejszej opcji P1. Obejmuje propsy całych stron, współdzielone payloady, struktury zagnieżdżone i enumy kontraktu. Nie przekazujemy całych modeli Eloquent. Typy lokalnego stanu UI pozostają ręczne. Wayfinder generuje trasy i metody, nie schematy odpowiedzi. Dla publicznego API Resources/OpenAPI mogą mieć własny kontrakt; nie dublować mechanicznie Data i Resource dla tego samego payloadu.

Generowane pliki mają jedno źródło, deterministyczną komendę i kontrolę driftu w CI oraz typecheck po generacji. Nie poprawiać ich ręcznie. FormRequest pozostaje źródłem walidacji wejścia; DTO nie wprowadza drugiego zestawu reguł. Złożone wejście do Action przekazujemy przez Data ze zwalidowanych pól; prosty model i pojedynczy argument nie wymagają osobnego DTO. Jawne mapowanie stosujemy przy transformacji lub zależności od kontekstu. Serializacja nie uruchamia ukrytych zapytań. Generacja typów nie zastępuje walidacji runtime ani testów serializacji (null/optional, daty, enumy, paginacja i pola prywatne).

### Szczegółowy kontrakt implementacji — zaakceptowany 2026-09-10

- **FormRequests i autoryzacja:** własny Request dla wejścia wymagającego walidacji, także filtrów, sortowania i paginacji. Bez pustych Requestów dla stron bez wejścia. `authorize()` świadomie deleguje do Policy/Gate albo dopuszcza operację publiczną; bez mechanicznego `true`. Policy chroni zasób, walidacja sprawdza dane. Wywołania poza HTTP również muszą przechodzić autoryzację w jawnie ustalonym punkcie wejścia.
- **Actions:** jeden przypadek użycia, niezmienniki i granica transakcji. Bez zależności od HTTP, Inertia i globalnego requestu. Efekty uboczne wymagające trwałego zapisu uruchamiamy po commit; operacje ponawiane są idempotentne. Integralność chronią też constrainty DB; sama walidacja nie zabezpiecza współbieżności.
- **Repositories:** konkretne zapytania aplikacyjne i nazwane operacje dostępu do danych; parametry filtrów, strony, użytkownika i języka przekazujemy jawnie. Bez generic CRUD wrappera i bez globalnego `request()`. Model zachowuje relacje, casts, małe lokalne scopes i reguły. Query wydzielamy dla złożonego odczytu, bez równoległego dublowania metod Repository. Cache tylko z uzasadnieniem i jawną invalidacją.
- **Services i zależności:** spójna współdzielona funkcjonalność lub integracja. Bez automatycznego Service dla każdego modelu i pustej delegacji 1:1. Zależności wstrzykujemy; interfejsy na granicach lub dla rzeczywistej wymienności, nie do każdej klasy.
- **SOLID, DRY, KISS, YAGNI:** odpowiedzialności i zależności mają być czytelne. Wspólna reguła biznesowa ma jedno źródło, ale podobne linie z różnych procesów nie wymagają wspólnej abstrakcji. Przed nową warstwą rozważ korzyść, koszt i prostszą alternatywę; drobna decyzja nie wymaga osobnego ADR.
- **Kompozycja i wzorce:** preferuj współpracujące małe klasy zamiast własnych hierarchii BaseEverything; dziedziczenie frameworkowe jest naturalnym wyjątkiem. Strategy, Adapter i inne wzorce dobieraj do konkretnego problemu. Value Objects stosuj dla wartości z niezmiennikami, nie dla każdego stringa.
- **Enumy:** preferuj PHP enum dla zamkniętych zestawów wartości domenowych (status, typ, tryb) zamiast rozproszonych magicznych stringów/liczb. Persistowane wartości mają stabilne backed values; zmiana wartości wymaga uwzględnienia danych i kontraktów. Frontend korzysta z wygenerowanego kontraktu, a etykiety są tłumaczone osobno. Nie zamieniaj dowolnego tekstu, identyfikatorów, flag boolean ani edytowalnych słowników z DB na enum. Stałe techniczne i config pozostają właściwe dla innych wartości.
- **Casty i typy:** jawnie definiuj semantyczne casts Eloquent: pole boolean zapisane jako int `0/1` → boolean, status → enum, liczby → właściwy typ, daty → preferowane immutable date/datetime, JSON → array/obiekt według kontraktu. Zachowuj nullable; nie zamieniaj braku wartości na false lub zero. DTO nie naprawia brakującego castu modelu. Cast nie zastępuje walidacji ani constraintów; nieprawidłowe historyczne wartości wymagają jawnej obsługi. Pieniądze: integer w najmniejszej jednostce + waluta; bez float dla kwot wymagających dokładności. Decimal zachowuje precyzję i jawny format kontraktu. Daty serializuj ISO 8601 UTC, duże identyfikatory jako string. Testuj ważne konwersje i rzeczywisty JSON, zwłaszcza `0/1`, enum i null.

Zakres obecnej decyzji to dokumentacja i reguły AI. Instalacja zgodnych wersji Data/Transformera, generacja, kontrola CI oraz migracja istniejących endpointów pozostają zadaniem wdrożeniowym; nie oznaczamy ich jako wykonanych.

## Publiczny SSR i SEO

P0: React/Inertia SSR dla wszystkich publicznych tras. Semantyczny HTML, treść i metadane muszą być obecne w pierwszej odpowiedzi bez wykonania JavaScriptu. Node SSR jest osobnym procesem z health checkiem, monitoringiem, restartem przy deployu i kontrolą błędów; nie dodawać Next.js tylko dla SEO.

Zasady: unikalny title/description; canonical z kontrolowanego origin, nie dowolnego Host; OG; poprawny język dokumentu; sitemap wyłącznie opublikowanych, kanonicznych URL z rzeczywistym lastmod; robots jako wskazówka dla robotów, nigdy autoryzacja. Draft i preview chronione policy, cache-control private/no-store i noindex. Staging za auth oraz noindex. Hreflang tylko przy realnych wersjach językowych. Structured data wyłącznie zgodne z widoczną treścią i typem działalności. [Oficjalne zasady Google](https://developers.google.com/search/docs/fundamentals/seo-starter-guide).

Zmiana sluga: transakcyjny zapis nowego sluga + unikalność + rejestr starego → nowy, 301 bez łańcuchów i pętli. Cel przekierowania lokalny i walidowany; bez open redirects. 404 dla braku zasobu, 410 dla trwale usuniętego, jeśli uzasadnione SEO. Publikacja/unpublish aktualizuje sitemap i cache po commit. Strona bez publikacji nie może wyciec przez wyszukiwarkę panelu dostępną gościowi.

## Wielojęzyczność i adresy URL — P0

Wymaganie użytkownika z 2026-09-10: i18n jest fundamentem od pierwszego ekranu, również gdy klient zaczyna od jednego języka. Wspólny rejestr opisuje locale, ich nazwy własne i kierunek tekstu, ale konfiguracja rozdziela aktywne języki i domyślne locale części publicznej oraz panelu. Język domyślny publiczny wybiera klient; panel ma zawsze domyślny i awaryjny angielski (`en`). Polski nie jest wymagany w żadnej części aplikacji. Listy języków publicznych i administracyjnych są niezależne; język dostępny w panelu nie tworzy publicznej wersji strony. Backend, frontend, routing i SEO korzystają ze zgodnej konfiguracji. Nowy język nie wymaga osobnych warunków w komponentach, ręcznego kopiowania tras ani kolumn typu `title_pl`, `title_en`.

### Wybór adresów przed publikacją

| Strategia | Przykład dla EN jako domyślnego, bez PL | Konsekwencje |
| --- | --- | --- |
| Domyślny bez prefiksu — rekomendacja dla startera | `/`, `/contact`; `/de/`, `/de/kontakt` | Krótkie adresy; dodanie DE zachowuje istniejące angielskie URL-e. Język pod `/` pozostaje stały. |
| Prefiks każdego języka — wariant do wyboru przed startem | `/en/`, `/de/` | Symetryczne adresy; `/` wymaga jawnego przeznaczenia, np. neutralnej strony wyboru języka. |

Google zaleca odrębne URL-e wersji językowych; nie wymaga prefiksu języka domyślnego. Rekomendacja bez prefiksu jest decyzją projektową, nie obietnicą przewagi rankingowej. Nie używać `?lang=` jako docelowej strategii publicznych adresów ani różnych języków pod jednym URL zależnie od cookies. [Google: serwisy wielojęzyczne](https://developers.google.com/search/docs/specialty/international/managing-multi-regional-sites).

Klient przed pierwszą publikacją poznaje obie strategie i otrzymuje mapę przykładowych adresów dla strony głównej i podstron. Konfiguracja jest decyzją wdrożeniową, nie przełącznikiem zmieniającym opublikowane adresy bez migracji. Zmiana strategii, domyślnego języka lub opublikowanego sluga wymaga mapowania starych URL do właściwych językowo następców, trwałych przekierowań i aktualizacji linków, canonical, hreflang i sitemap.

### Kontrakt routingu i SEO

- Publiczny URL jednoznacznie ustala język, także dla walidacji formularza. Preferencja konta/cookie nie nadpisuje języka publicznego URL. Bez automatycznych przekierowań na podstawie IP lub `Accept-Language`; można zaproponować zmianę przez zwykły link.
- Przy domyślnym EN alias `/en/contact`, jeżeli jest obsługiwany, przekierowuje 301 bezpośrednio do `/contact`; nie publikuje drugiej kopii. Nieznany lub nieaktywny prefiks daje 404. Zarezerwować przestrzeń prefiksów locale, aby nowe języki nie kolidowały ze slugami i trasami systemowymi.
- Jeden mechanizm generuje adresy na podstawie locale i tożsamości treści. Linki frontendowe korzystają z Wayfinder; nie doklejać prefiksów ręcznie. Przełącznik języka prowadzi do opublikowanego tłumaczenia bieżącej strony, także przy innym slugu, i używa prawdziwych linków dostępnych dla robotów.
- Każda opublikowana wersja ma canonical do własnego kanonicznego URL, przetłumaczone title/description, treść, OG i właściwe `html lang` oraz `dir`. Treść i metadane są dostępne w pierwszym HTML SSR; hydracja zachowuje ten sam język. Nie kierować canonical tłumaczeń do wersji domyślnej.
- Zestaw `hreflang` wiąże równoważne, opublikowane strony, zawiera bieżącą wersję i wzajemne odwołania. Używa pełnych kanonicznych URL zwracających 200 oraz poprawnych kodów (`pl`, `en`, `de`; region tylko przy rzeczywistym targetowaniu, np. `en-GB`). Przyjąć HTML head jako źródło adnotacji; ewentualna kopia w sitemap musi być zgodna.
- `x-default` wskazuje odpowiednik w języku domyślnym, gdy jest opublikowany, albo rzeczywistą stronę wyboru języka. Nie wskazywać mechanicznie `/` dla każdej podstrony. Sitemap zawiera wyłącznie opublikowane, indeksowalne, kanoniczne wersje. [Google: lokalizowane wersje i hreflang](https://developers.google.com/search/docs/specialty/international/localized-versions).
- Brak tłumaczenia treści lub draft: 404 pod jej nieopublikowanym URL, brak w sitemap i hreflang; przełącznik pokazuje niedostępność. Nie serwować polskiej treści pod `/de/` jako indeksowalnego fallbacku. Tłumaczenie samej nawigacji nie tworzy gotowej wersji językowej strony.

### Organizacja plików routingu — decyzja 2026-09-11

`routes/web.php` rejestruje grupy i dołącza pliki; `front.php` definiuje publiczne strony jeden raz dla wszystkich aktywnych języków; `admin.php` zawiera wspólną grupę `/admin` i `admin.*` z dotychczasowymi zabezpieczeniami. `auth.php` oraz `settings.php` pozostają odrębne; ustawienia konta zachowują dotychczasowe URL poza prefiksem admin. Wzorzec pochodzi z projektu blog, ale default wynika z konfiguracji, a istniejące nazwy bazowe/`localized.*` zachowujemy. Nowa strona trafia tylko do `front.php`, nowy CRUD panelu do grupy w `admin.php`. Nie kopiować definicji per język ani budować własnego routera. Szczegóły i odbiór: [plan, sekcja 6](12-i18n-implementation-plan.md#6-routing-i-utrzymywalne-i18n--ustalenia-2026-09-11).

**Rozbieżność do rozstrzygnięcia:** kontrakt auth opisany poniżej jest wcześniejszym celem; kod roboczy ma także lokalizowane publiczne auth. Sam refaktor plików zachowuje działające URL i middleware. Zmiana zachowania wymaga konkretnej propozycji i akceptacji zgodnie z sekcją 6.4 planu.

### Panel administratora — niezależna preferencja

Panel używa stałych tras bez locale, np. `/admin` i `/admin/pages`; nie tworzyć `/de/admin` ani osobnych tras dla każdego języka. Angielski jest zawsze dostępny, domyślny i stanowi fallback, niezależnie od języka publicznej strony. Wszystkie teksty panelu nadal podlegają pełnemu tłumaczeniu.

Dla zalogowanego administratora źródłem preferencji jest profil użytkownika; przełącznik zapisuje wybór w profilu i synchronizuje sesję. Przed logowaniem wybór jest przechowywany w sesji. Kolejność rozstrzygania: aktywna preferencja profilu → aktywna preferencja sesji → `en`. Nieaktywną lub nieobsługiwaną wartość pominąć. Po zalogowaniu preferencja profilu ma pierwszeństwo; zmiana nie modyfikuje URL. Nie wybierać automatycznie języka panelu na podstawie języka strony publicznej lub przeglądarki.

Ta sama preferencja steruje ekranami logowania do panelu, walidacją, błędami, toastami, modalami i powiadomieniami administracyjnymi. Wiadomości asynchroniczne otrzymują jawne locale odbiorcy. Panel nie jest publikowany jako zestaw wersji SEO: bez administracyjnych URL w sitemap i bez hreflang; pozostaje chroniony i nieindeksowalny. Zmiana preferencji panelu nie zmienia publicznych adresów, języka witryny ani języka edytowanej treści.

### Treści i kontekst języka

Strona/artykuł ma wspólną tożsamość i rozszerzalny zbiór tłumaczeń powiązanych przez locale. Title, slug, excerpt, body, metadata SEO, opisy obrazów i stan publikacji należą do wersji językowej; publikacja PL nie publikuje automatycznie EN. Unikalność sluga obowiązuje w obrębie języka i przestrzeni routingu, a przekierowania przechowują kontekst języka. Model danych musi to uwzględniać od pierwszej implementacji Content.

Język panelu i język edytowanej treści to oddzielne wartości: angielski panel może edytować niemiecki artykuł. Tłumaczenia interfejsu mają stabilne klucze i katalogi, oddzielone od treści klienta. Decyzja 2026-09-11: UI korzysta docelowo z `i18next` + `react-i18next`, z kwalifikacją zgodnych wersji przed instalacją. Laravel pozostaje źródłem locale i tłumaczeń backendowych; wspólne komunikaty mają jedno źródło autorskie oraz adapter/generację zasobów React. Szczegóły migracji: [plan, sekcja 6](12-i18n-implementation-plan.md#6-routing-i-utrzymywalne-i18n--ustalenia-2026-09-11).

Locale jest jawnie przekazywane do SSR, walidacji i wiadomości/jobów; długowieczny worker i proces SSR nie mogą przenosić języka pomiędzy żądaniami lub zadaniami. Cache uwzględnia locale, a publikacja, wycofanie tłumaczenia i zmiana sluga odświeżają też powiązane zestawy hreflang i sitemap. Daty, liczby i liczby mnogie są formatowane według locale; język nie ustala automatycznie waluty ani strefy czasowej.

## Błędy i spójność danych

| Przypadek             | Odpowiedź                                      | Obsługa                                                     |
| --------------------- | ---------------------------------------------- | ----------------------------------------------------------- |
| Niepoprawne dane      | API 422 + errors; Inertia redirect z error bag | Komunikat przy polu, zachowanie bezpiecznych danych         |
| Brak sesji/uprawnień  | API 401/403; web login/403                     | 404 zamiast ujawnienia istnienia zasobu tam, gdzie wymagane |
| Konflikt stanu/wersji | 409 dla API, komunikat formularza dla web      | Przeładuj aktualne dane, nie nadpisuj cudzej edycji         |
| Limit                 | 429 i Retry-After                              | UI komunikuje czas oczekiwania                              |
| Awaria zależności     | Kontrolowane 503 lub stan pending              | Timeout, retry tylko dla operacji bezpiecznych              |
| Nieoczekiwany błąd    | 500 i identyfikator błędu                      | Stack tylko w chronionym monitoringu                        |

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

Wzorzec CRUD obejmuje listę z paginacją, allowlistę sortowania, create/edit, walidację, policy, audit, confirmation dla usunięcia i test odmowy. Pierwszy CRUD napisać jawnie na wspólnych klockach (`ListQuery`, `ResourceTable`, `ResourceForm`); decyzją z 2026-09-27 generator powstaje zaraz po nim, w P0-B, a nie dopiero po drugim CRUD-zie. Generator ma emitować zwykłe pliki i testy, bez runtime metaprogramowania, klas BaseEverything i automatycznego nadpisywania ręcznych zmian. Bulk actions, eksporty i soft-delete są osobnymi przypadkami użycia. Nie zakładać, że wszystkie modele mają te same uprawnienia i cykl życia.

### Generator zasobów

`make artisan ARGS='app:make-resource Product --fields="name:string:required,price:decimal,active:boolean,status:enum(draft|published)" --searchable=name --sortable=name,created_at --filters=status,active'` (alias `make:admin-resource`; `make:resource` jest zajęte przez Laravel). Typy pól: `string`, `text`, `integer`, `decimal`, `boolean`, `date`, `enum(a|b)`, `belongsTo(Model.kolumna_etykiety)`, `belongsToMany(Model.kolumna_etykiety)` (nazwa pola w liczbie mnogiej, np. `tags`), `image`, `richtext`; `:required` oznacza pole wymagane (boolean i enum są zawsze wymagane, domyślnie `false`/pierwsza wartość; `image` i `richtext` są zawsze opcjonalne). Wartość enuma może mieć ton badge'a z `BadgeProps['tone']` (`neutral`, `primary`, `success`, `danger`, `outline`), np. `status:enum(draft|published:success|archived:danger)`; bez tonu — `neutral`, nieznany ton blokuje generację. `--searchable` przyjmuje pola string/text, `--sortable` pola nie-tekstowe (bez relacji, obrazu i rich text) oraz `id`/`created_at`/`updated_at` (domyślnie `created_at`), `--filters` pola boolean/enum/belongsTo (select) oraz `date` (filtr zakresu `ResourceTable` `kind: 'dateRange'` + `ListQuery::dateRange()`, parametry `{pole}_from`/`{pole}_to`, oba dni włącznie, odwrócony zakres → 422). Każda lista dostaje akcje zbiorcze (`bulkActions`, widoczne przy `can.delete`): `DELETE /admin/{kebab}` (`admin.{kebab}.destroy-many`, `can:viewAny`), `Destroy{Plural}Request` (`ids`, lista, `distinct`, maks. 100), w kontrolerze transakcja z `lockForUpdate` i `authorize('delete')` każdego rekordu przed usunięciem (wszystko albo nic; brakujące id pomijane), toast `deletedMany`. `--export` dodaje eksport CSV. `--dry-run` wypisuje planowane pliki bez zapisu.

`belongsTo(Category.name)` na polu `category`: kolumna `category_id` (`foreignId()->index()->constrained()`, wymagane → `restrictOnDelete`, opcjonalne → `nullOnDelete`), relacja `category()`, `Rule::exists`, `Category::factory()` w factory, etykieta `categoryLabel` na liście i w CSV (eager `with('category:id,name')`), a w edytorze `categoryOptions` (`RecordOptionData`, maks. `RecordOptionData::LIMIT` = 500, uporządkowane po etykiecie, z flagą `categoryOptionsTruncated` i komunikatem). Generator sprawdza w źródle modelu (bez bazy, więc także w dry-run i testach), że `app/Models/Category.php` istnieje, używa `HasFactory`, nie ma własnej nazwy tabeli, a kolumna etykiety jest w `#[Fillable]`/`$fillable` lub `@property`. `User` jest zablokowany jako cel relacji (konta zarządza wyłącznie moduł użytkowników). `cover:image` → `cover_media_id` (nullable FK do `media_assets`, `nullOnDelete`), relacja `cover()`, walidacja `App\Rules\DamImage` (clean, `MediaAsset::IMAGE_MIMES`, warianty) i pole `image` z `useMediaImagePicker`. `body:richtext` → `json()->nullable()`, cast `array`, `nullable|array` + `RichTextDocument`, `RichTextRenderer::sanitize()` przed zapisem (`Store{Name}Request::sanitizeRichText()`), pole `richText` z etykietami `admin.richText.*` i wstawianiem obrazów z DAM; rich text i obraz nie trafiają na listę, do wyszukiwania, sortowania ani CSV.

`belongsToMany(Tag.name)` na polu `tags`: tabela pivot `product_tag` tworzona w migracji zasobu (usunięcie rekordu po dowolnej stronie usuwa tylko powiązanie), relacja `tags()` z jawną nazwą pivota, klucz żądania `tag_ids` (lista id), `Store{Name}Request::relationIds()` i `fieldValues()` bez `*_ids`; `store` tworzy rekord i robi `sync()` w jednej transakcji, `Update{Name}` synchronizuje w transakcji z optimistic lockingiem. Formularz: pole `multiSelect` (`MultiSelectField`) z `tagsOptions` jak przy `belongsTo`. Na liście, w filtrach, sortowaniu i eksporcie relacja nie występuje. Wygenerowane testy: synchronizacja przy create/update, payload edytora, odrzucenie nieznanych/powtórzonych id bez zmian w pivocie i kaskada pivota.

`--owned`: rekord należy do użytkownika, który go utworzył — kolumna `user_id` (`foreignId()->index()->constrained('users')->cascadeOnDelete()`; usunięcie konta usuwa jego rekordy), relacja `user()`, factory `User::factory()`. `user_id` nie jest w `#[Fillable]` ani w walidacji: `store` ustawia go przez `user()->associate($request->user())`, wartość z żądania jest ignorowana. Policy z `stubs/resource/policy.owned.stub`: `viewAny`/`create` dla ról panelu, `update`/`delete` wyłącznie dla właściciela — **także administrator nie ma wyjątku** (decyzja właściciela 2026-10-04). Lista i eksport są zawężone do `where('user_id', $request->user()?->id)`; `can.delete` na liście = dostęp do panelu (wszystkie widoczne rekordy są własne). Wygenerowane testy: lista tylko własnych rekordów, właściciel z sesji mimo podstawionego `user_id`, 403 na edit/update/delete cudzego rekordu dla edytora i administratora bez zmian w danych, usunięcie przez właściciela.

`--export`: trasa `GET /admin/{kebab}/export` (`admin.{kebab}.export`, `can:export`, `throttle:6,1`), policy `export()` tylko dla administratora, ten sam `ListQuery::apply()` co lista (wyszukiwanie, filtry, sort), strumień CSV z BOM UTF-8, nagłówkami przez `__()`, etykietami enumów i relacji zamiast wartości/ID oraz komórkami przez `App\Support\Csv\CsvCell::safe()` (apostrof przed `= + - @`, tabulatorem i CR; liczby bez prefiksu). Limit `{Name}Controller::EXPORT_MAX_ROWS` = 10 000 (nadpisuje `config('exports.{kebab}.max_rows')`); większy wynik kończy się 422 z komunikatem, bez uciętego pliku, a lista wyłącza przycisk i pokazuje komunikat. Każdy eksport zapisuje audyt `resource.exported` (moduł, liczba wierszy, filtry; fraza wyszukiwania tylko jako `redacted`).

Generuje zwykłe pliki z edytowalnych stubów `stubs/resource/*.stub`: migrację, model z castami i `#[Fillable]`, enum per pole enum, factory, seeder `database/seeders/{Name}Seeder.php` (10 rekordów z factory; nie jest podpinany do `DatabaseSeeder` — krok w „Next steps”), policy (admin/editor przez `canAccessAdminPanel()`, usuwanie tylko `isAdmin()`), controller, `List/Store/Update` FormRequesty (`ListQuery`), akcję `Update{Name}` z optimistic lockingiem po `updated_at`, klasy Data z `#[TypeScript]`, strony React na `ResourceTable`/`ResourceForm`, test Pest (lista, allowlisty, walidacja, konflikt, odmowy bez mutacji) i test vitest listy. Dopisuje trasy do `routes/admin.php` i klucze `admin.{camelPlural}.*` do `lang/{en,pl,de}/admin.php` (pl/de to generyczne teksty do przeglądu). Po zapisie formatuje nowe pliki Pintem i `vp fmt` (`--no-format` wyłącza).

Mapowanie typów pól (backend → Data → formularz):

| Typ | Migracja / cast | Walidacja (`Store{Name}Request::fieldRules()`) | Data (PHP → TS) | Pole `ResourceForm` |
| --- | --- | --- | --- | --- |
| `string` | `string` / — | `string`, `max:255` | `?string` | `text` |
| `text` | `text` / — | `string`, `max:20000` | `?string` (poza listą) | `textarea` |
| `integer` | `integer` / `integer` | `integer`, zakres int32 | `?int` → `number \| null` | `number`, `step: 1` (klawiatura `numeric`) |
| `decimal` | `decimal(12,2)` / `decimal:2` | `numeric`, `decimal:0,2`, zakres | `?string` (bez utraty precyzji) | `number`, `step: 0.01` (klawiatura `decimal`) |
| `boolean` | `boolean` default false / `boolean` | `boolean` | `bool` | `switch` |
| `date` | `date` / `date` | `date_format:Y-m-d` | `?string` `YYYY-MM-DD` | `date` (natywny `<input type="date">`) |
| `enum(a\|b)` | `string(32)` / enum | `Rule::enum` | enum → unia TS | `select` |
| `belongsTo(M.col)` | `foreignId` + FK / — | `integer`, `Rule::exists` | `?int` + `{rel}Label` na liście | `select` (opcje z edytora) |
| `belongsToMany(M.col)` | tabela pivot `{a}_{b}` (alfabetycznie, FK `cascadeOnDelete`, PK pary) w tej samej migracji / — | `{rel_singular}_ids`: `array`, `list`, `max:500`; `.*`: `integer`, `distinct`, `Rule::exists` | `list<int>` `{relSingular}Ids` (tylko formularz) | `multiSelect` (opcje z edytora, `common.multiSelect.*`) |
| `image` | `foreignId` → `media_assets` / — | `integer`, `DamImage` | `?int` | `image` (DAM) |
| `richtext` | `json` / `array` | `array`, `RichTextDocument` | `{ [key: string]: unknown } \| null` | `richText` |

Wartości formularza liczbowych pól i dat są na froncie stringami (`''` = brak); typ nadaje walidacja i cast modelu. Dla opcjonalnych pól `integer`/`decimal`/`date` requesty generują `prepareForValidation()` z `Store{Name}Request::blankOptionalInputsAsNull()` (pusty string → `null`, niezależnie od globalnego `ConvertEmptyStringsToNull`), a test Pest sprawdza to z wyłączonym middleware.

Czego nie robi: nie nadpisuje niczego i nie ma `--force` — istniejący plik, trasa, klucz katalogu lub migracja tabeli blokują całość (zapis atomowy z rollbackiem); nie tworzy tłumaczeń per locale, relacji innych niż `belongsTo`/`belongsToMany` (bez `hasMany` — wymagałby zmiany istniejącej tabeli lub modelu dziecka, a generator niczego nie nadpisuje; stronę dziecka pokrywa `belongsTo`), uploadów poza wyborem obrazu z DAM, soft-delete ani bulk actions; dopisuje wpis w grupie „Content” sidebaru panelu (`resources/js/layouts/admin-layout.tsx`, przed znacznikiem `// app:make-resource: new navigation items`; ikona `Boxes` do zmiany ręcznie, brak znacznika blokuje całość); nie uruchamia migracji ani generowania typów — wypisuje te kroki jako „Next steps”. Przykład: zasób `Faq`.

Dowód AC „moduł bez ręcznych poprawek”: `make generator-smoke` (w CI osobny job `generator-smoke`) kopiuje repozytorium do katalogu tymczasowego (bez `.env`, lokalnej bazy i `.git`), generuje trzy moduły (pełny zestaw pól z relacjami, obrazem, rich text, tonami i eksportem; relacja opcjonalna jako filtr; zasób prosty) i uruchamia na nich Pint, PHPStan, Pest (migracje na SQLite `:memory:`), bramkę i18n, `types:generate`, `wayfinder:generate`, `types:check`, `npm run check` i `check:ui-contract`.

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
