# Plan implementacji wielojęzyczności

Status: plan do realizacji, nie potwierdzenie wdrożenia. Opracowano 2026-09-10 na podstawie aktualnej dokumentacji i katalogu roboczego przy HEAD `ae94a4d94d285c879a6060d0bfe599ef68ddca49`.

## 1. Źródła i sposób pracy

Obowiązują kontrakty: [architektura i URL](01-architecture-and-modularity.md#wielojęzyczność-i-adresy-url--p0), [kompletność UI](05-design-system-and-frontend.md#tłumaczenia-całego-interfejsu--p0), [odbiór P0](03-testing-and-quality.md#odbiór-wielojęzyczności--obowiązkowe-p0) i [roadmapa](08-implementation-roadmap.md#wielojęzyczność--wymaganie-2026-09-10). Ten plan uszczegóławia kolejność, nie zastępuje tych wymagań.

Agent wykonuje po jednym etapie. Przed kolejnym sprawdza wynik poprzedniego i raportuje pliki, testy oraz ograniczenia. Nie uznaje samego istnienia katalogów za działające i18n. Nie implementuje całego CMS w ramach fundamentu.

Przed zmianami przeczytaj `AGENTS.md`, `.ai/rules/index.md` i pasujące reguły. Używaj Docker/Makefile oraz prefiksu `rtk`. Nie nadpisuj `.env`, bazy ani zastanych zmian. W katalogu roboczym są liczne zmiany staged i unstaged, także i18n i design systemu. Nie commituj ani nie pushuj w ramach tego planu.

## 2. Stan zastany i luki — aktualizacja 2026-09-11

Odczyt katalogu roboczego, nie tylko HEAD, wykazał:

- Istnieją `config/localization.php`, katalogi PHP, `LocalizationConfig`, `LocalizationManager`, `LocalizedUrlGenerator`, middleware i testy lokalizacji. Nie tworzyć ich ponownie.
- `resources/js/i18n/index.ts` implementuje własną interpolację i pluralizację przez `Intl.PluralRules`, ale zachowuje mutowalny globalny `activeTranslator`. To ryzyko izolacji SSR; nie uznawać go za docelowy wzorzec.
- `resources/js/lib/localized-routes.ts` zakłada domyślny `en`, mimo konfigurowalnego defaultu publicznego. Zmiana defaultu na DE wymaga testów rzeczywistych linków i formularzy, nie tylko konfiguracji PHP.
- Backend osobno składa fallback katalogów UI, a translator Laravel korzysta z własnego fallbacku. `LocalizationManager` przechowuje kontekst jako singleton i rozpoznaje obszar po listach ścieżek. Te mechanizmy wymagają uproszczenia.
- `LocalizedUrlGenerator` nie sprawdza aktywności docelowego języka publicznego. Preferencja panelu przekazana do linku resetowania hasła może prowadzić do nieistniejącego publicznego endpointu.
- Wydzielono `routes/admin.php`, dołączany przez `web.php`, z dotychczasową trasą panelu i dołączeniem `settings.php`. To podział plików, jeszcze nie docelowa wspólna grupa administracyjna. Publiczne definicje nadal nie mają jednego źródła dla defaultu i dodatkowych języków.
- Istnieją lokalizowane trasy auth. Koliduje to ze starszym założeniem dokumentacji o auth bez prefiksu; przed zmianą tego kontraktu potrzebna jest jawna decyzja, patrz sekcja 6.
- Inertia v3 korzysta z obecnego entrypointu i pluginu Vite. Brak osobnego `ssr.tsx` nie dowodzi braku SSR.
- Content pozostaje poza bieżącym refaktorem; nie zakładać gotowych modeli, sitemap ani resolvera slugów.

W poprzedniej sesji przeszły 34 testy / 128 asercji: `AdminIndexTest`, `PublicLocalizedRoutingTest`, `AdminLocalePreferenceTest`, `LocalizedAuthRoutingTest`. To dowód regresji po wydzieleniu pliku, nie odbiór pełnego i18n ani SSR. Przy rozpoczęciu pracy ponownie sprawdź kod i testy — użytkownik może równolegle rozwijać projekt.

## 3. Decyzje docelowe

### Ustalone w dokumentacji

1. Oddziel `publicLocale`, `adminLocale` i `contentLocale`. Ostatnie oznacza wersję edytowanego rekordu, nigdy język komunikatów panelu.
2. Backend jest źródłem konfiguracji: rejestr kodów, nazw własnych i `dir`; osobno aktywne języki strony oraz panelu. EN zawsze aktywne w panelu i zawsze jego fallbackiem. PL opcjonalne.
3. Publicznie język wynika wyłącznie z URL. Profil, cookie, sesja i `Accept-Language` go nie nadpisują. Także błędny POST formularza zachowuje język jego publicznego endpointu.
4. Panel: aktywny profil → aktywna sesja → EN. Wybór zalogowanego użytkownika zapisuje profil i synchronizuje sesję. Gość zapisuje sesję. Bez językowych prefiksów panelu/auth/settings.
5. Fallback komunikatów UI jest kontrolowany. Brak opublikowanego tłumaczenia treści oznacza 404, nigdy treść innego języka pod indeksem żądanego języka.
6. Cache, SSR i zadania asynchroniczne mają jawny kontekst języka. Brak mutowalnego translatora współdzielonego pomiędzy renderowaniami.

### Rekomendacje tego planu

- Wdrażaj publiczny język domyślny bez prefiksu. Nie buduj drugiej strategii routingu przed rzeczywistym wyborem klienta. Prefiksy wszystkich języków są dopuszczalną alternatywą z dokumentacji, ale nie obowiązkową funkcją startera.
- Decyzja 2026-09-11: docelowy silnik UI to `i18next` + `react-i18next`, aby zastąpić własny silnik i ograniczyć koszt utrzymania. Laravel pozostaje właścicielem locale, konfiguracji i tłumaczeń backendowych. Wspólne komunikaty mają jedno źródło autorskie; zasoby React powstają przez jawny adapter lub deterministyczną generację, bez ręcznie utrzymywanej kopii PHP/TS. Szczegóły migracji i kwalifikacji wersji: sekcja 6.
- Content przechowuj w relacyjnych tabelach tłumaczeń. JSON per pole ogranicza liczbę tabel, ale utrudnia constrainty slugów, zapytania i publikację per język; kolumny `title_pl`/`title_en` są zabronione.
- Dla katalogów użyj EN jako schematu kluczy; publiczny fallback UI niech będzie jawnie skonfigurowany i należeć do aktywnych języków publicznych, domyślnie równy publicznemu językowi domyślnemu. Ten fallback nie dotyczy treści redakcyjnej.

Przed implementacją zakresu high-risk przedstaw do akceptacji konkretną zmianę middleware/auth i migracji preferencji użytkownika. Przed modelem Content analogicznie przedstaw schemat danych. Wybór `i18next` i `react-i18next` został zaakceptowany jako kierunek tego refaktoru; przed instalacją zakwalifikuj zgodne wersje i lockfile. Inne nowe zależności wymagają odrębnej akceptacji. Przed publikacją klient wybiera języki, strategię URL i właściciela odbioru tłumaczeń. Do czasu tej decyzji używaj konfiguracji testowych, nie zgaduj konfiguracji produkcji.

## 4. Etapy wykonawcze

### E0 — kwalifikacja i granice zakresu

Odczytaj diff roboczy i staged, strukturę tras, model użytkownika, konfigurację Fortify, istniejące maile, testy i skrypty jakości. Sprawdź zainstalowane wersje przez `rtk make composer ARGS='show --direct'`, `package.json` oraz lockfile. Przed wyborem API wykonaj Boost `search-docs` dla zainstalowanych wersji Laravel, Inertia i Wayfinder; jeżeli narzędzie nie jest dostępne, skorzystaj z oficjalnej dokumentacji.

Zakwalifikuj sposób współdzielenia katalogu na kilku komunikatach: zwykły tekst, parametr, pluralizacja PL/EN/DE, błąd walidacji, tekst dostępności. Wybór musi obejmować format wiadomości, mapowanie parametrów Laravel/React, obsługę kategorii liczby mnogiej, generowanie typów i ładowanie katalogu w SSR. Nie pisz własnego parsera ICU. Niezgodność formatów rozwiąż w adapterze/generatorze, nie przy każdym wywołaniu `t()`.

**Odbiór:** jednoznacznie wskazany format i źródło katalogów, wybrane API potwierdzone dokumentacją, brak niezatwierdzonych zależności. Zakwalifikuj wybrane `i18next` i `react-i18next`; nie wracaj do własnego silnika bez wykazania konkretnej przeszkody.

### E1 — rejestr języków i kontrakt kontekstu

Proponowany nowy plik `config/localization.php`: rejestr, aktywne locale publiczne/panelowe, publiczny default i fallback, administracyjne EN. Nie twórz dodatkowego rejestru w TS. Waliduj konfigurację: nieznane kody, duplikaty, default poza aktywną listą, brak EN panelu i niepoprawne metadane muszą blokować uruchomienie/wdrożenie z czytelnym błędem.

Ustal jawny payload `i18n`: obszar (`public`/`admin`), efektywne locale, fallback, kierunek, dozwolone języki danego obszaru i katalog potrzebny ekranowi. Język treści pozostaje osobnym propsem formularza Content. Publiczny payload nie ma ujawniać katalogu panelu.

**Odbiór:** konfiguracje EN-only, publiczne EN+DE i publiczne DE+EN działają bez zależności od PL; listy panelu i strony są niezależne. To test konfiguracji, jeszcze nie deklaracja gotowego routingu.

### E2 — rozstrzyganie locale i preferencja panelu

Miejsca integracji: `bootstrap/app.php`, `app/Http/Middleware/`, `HandleInertiaRequests.php`, `routes/web.php`, `routes/settings.php`, konfiguracja/Provider Fortify oraz `app/Models/User.php`.

Przypisz obszar jawnie do grup tras; nie rozpoznawaj panelu wyłącznie przez `startsWith('/admin')`. Ustal kolejność middleware tak, aby sesja i użytkownik były dostępne, a locale ustawione przed walidacją, komunikatami auth i tworzeniem props. Uwzględnij błędy przed wejściem do controllera, w tym brak trasy i wygaśnięcie sesji.

Po akceptacji dodaj nullable preferencję `admin_locale` nową migracją; brak wartości oznacza rozstrzygnięcie kontraktem, nie backfill na PL. Endpoint aktualizacji używa FormRequest i allowlisty języków panelu, CSRF, metody stanowej oraz wyłącznie bieżącego użytkownika. Nie przyjmuje docelowego `user_id`. Przed logowaniem aktualizuje tylko sesję. Po loginie rozstrzyga pierwszeństwo profilu; po logout usuwa preferencję poprzedniego konta z sesji, aby kolejny użytkownik jej nie odziedziczył.

**Odbiór:** pierwszeństwo profilu/sesji/EN, ignorowanie nieaktywnych preferencji, trwałość po ponownym logowaniu, izolacja kont i brak zmiany URL. Test błędnej wartości potwierdza brak zapisu; test próby zmiany cudzego konta potwierdza brak mutacji. Publiczny URL wygrywa z każdą preferencją.

### E3 — katalogi, adaptery i izolacja SSR

Rozwiń/zastąp `resources/js/i18n/` zgodnie z E0, zachowując już przetłumaczone teksty. Własne komunikaty i katalogi Laravel mają stabilne klucze grupowane funkcjonalnie: wspólne UI, auth, settings, public, errors, validation, notifications. Nie używaj polskich zdań jako identyfikatorów.

Usuń założenia `'pl' | 'en'`, typowanie zależne od PL i domyślne globalne `t` z PL. Translator otrzymuje kontekst wywołania; hook React pobiera bieżące props także po nawigacji Inertia. SSR i klient otrzymują identyczne locale i katalog. HTML `lang`/`dir` poprawne w pierwszej odpowiedzi i po zmianie języka. Nie twórz przetłumaczonych tablic menu na poziomie modułu.

Brak klucza: w kontrolach/testach błąd; w produkcji bezpieczny fallback UI i diagnostyka bez danych osobowych, bez surowego klucza jako planowanego komunikatu dla użytkownika. Interpolacja bez dowolnego HTML. Daty/liczby mają jawne locale, walutę i strefę czasową niezależne od języka.

**Odbiór:** parametry i pluralizacja PL/EN/DE, fallback oraz brak klucza; kolejne i równoległe SSR różnych języków bez przecieku; hydracja bez zmiany języka. Sam test props nie zastępuje sprawdzenia pierwszego HTML.

### E4 — publiczne trasy i jeden generator URL

Zacznij od istniejącej strony głównej i kontraktu publicznych formularzy. Trasy statyczne oraz przyszły Content korzystają z jednego mechanizmu adresowania. Zdefiniuj nazwane trasy bez prefiksu defaultu i generowaną z konfiguracji grupę dla aktywnych języków dodatkowych; nazwy tras muszą pozostać unikalne i kompatybilne z generowaniem Wayfinder. Nie kopiuj tras dla DE/PL ręcznie.

Resolver przyjmuje tożsamość strony i docelowe locale, a nie przetłumaczony slug bieżącej wersji. Dla Content uzupełnimy go w E6. Backend rozstrzyga docelową trasę i parametry, frontend korzysta z wygenerowanych funkcji Wayfinder. Jeśli przekazywane są gotowe URL-e SEO, wszystkie muszą pochodzić z tego samego resolvera. Nie sklejaj `'/'+locale` w komponentach.

Zarezerwuj prefiksy rejestru języków oraz trasy systemowe, auth/settings/admin; catch-all treści ma najniższy priorytet. Rozstrzygnij alias defaultu konsekwentnie: rekomendacja obsługiwać go pojedynczym 301 do URL kanonicznego. Nieznane/nieaktywne prefiksy oraz `/de/admin` zwracają 404. Walidacja slugów nie może pozwalać przyszłej treści przejąć tych adresów.

**Odbiór:** tabela URL dla default EN i default DE, 404 dla nieaktywnych języków, pojedynczy redirect aliasu, brak kolizji z panelem i aktualne generowane funkcje tras. POST w języku dodatkowym zwraca walidację w tym języku.

### E5 — pełne pokrycie startera i wiadomości

Przejdź przez `resources/js/pages/{auth,settings,admin}/`, `welcome.tsx`, layouty, nawigację, współdzielone kontrolki, passkeys/2FA, błędy i komunikaty backendowe. Uwzględnij placeholdery, dialogi, loading/empty, a11y, toast, błędy sieci, nazwy pól, teksty bibliotek, reset hasła i weryfikację e-mail. Zachowaj obecne zasady dostępu i konfigurację rejestracji; tłumaczenie nie jest zgodą na zmianę auth.

Przełącznik panelu zapisuje preferencję, publiczny prowadzi prawdziwym linkiem do opublikowanego odpowiednika. Użyj istniejącego publicznego API design systemu, nazw własnych języków, bez flag jako jedynych etykiet. Przy jednym języku nie pokazuj zbędnego wyboru.

Walidacja podczas edycji i po zapisie używa tego samego locale i nazw pól; nie odtwarzaj samodzielnie złożonych reguł backendu w React. Asynchroniczne wiadomości otrzymują jawne locale odbiorcy/kontekstu w momencie zlecenia; wykonanie przywraca poprzedni kontekst także po wyjątku. Brak locale odbiorcy panelowego rozstrzyga się do EN.

**Odbiór:** kompletny scenariusz formularz → walidacja → zapis → toast oraz odmowa/modal, błąd sieci, strona błędu i e-mail w PL/EN; test EN/DE bez PL. Dwa kolejne joby różnych języków, również po błędzie pierwszego, nie mieszają komunikatów. RWD 360 px/tablet/desktop, zoom 200%, długie etykiety i klawiatura.

### E6 — Content od pierwszego modelu wielojęzyczny

Ten etap jest zależnością implementacji Content w P0-B, nie powodem tworzenia pustego CMS już w E1.

Proponuj wspólny rekord strony/artykułu oraz relację tłumaczeń. Tłumaczenie ma `locale`, title, slug, excerpt, body, SEO, lokalizowane opisy obrazów i własny stan publikacji. Constrainty DB: jedna wersja na `(content_id, locale)` i unikalny slug w obrębie `(locale, przestrzeń routingu, slug)`. Dokładne tabele/FK uzgodnij z rzeczywistym modelem Content przed migracją; unikaj pozornego constraintu, który nie obejmuje wspólnej przestrzeni stron i artykułów.

Publikacja sprawdza kompletność danej wersji. Query publiczne wymaga aktywnego języka i opublikowanej wersji. Panel przekazuje osobno `contentLocale`; komunikaty pozostają w `adminLocale`. Zmiana sluga rejestruje trwałe przekierowanie z kontekstem języka w tej samej transakcji; sprawdź konflikty, pętle i istniejące łańcuchy.

**Odbiór:** draft/nieistniejąca wersja 404, publikacja EN nie publikuje DE, dwa równoległe zapisy nie łamią unikalności, angielski panel edytuje niemiecką wersję. Dodanie DE wymaga konfiguracji, katalogu i danych, bez nowych kolumn, komponentów ani ręcznego kopiowania tras.

### E7 — SEO i spójne unieważnianie cache

Rozszerz resolver E4 o tłumaczenia E6. Z jednego zestawu opublikowanych odpowiedników buduj przełącznik, self-canonical, wzajemny hreflang z bieżącą wersją i sitemap. `x-default` wskazuje domyślny odpowiednik tylko gdy istnieje albo rzeczywistą stronę wyboru; w pozostałych przypadkach go pomiń. Nie wskazuj automatycznie strony głównej.

Pierwszy HTML zawiera lokalną treść, title, description, OG, lang/dir i meta SEO. Sitemap zawiera wyłącznie kanoniczne indeksowalne URL-e 200. Panel/auth/settings nie mają hreflang ani wpisów sitemap i pozostają nieindeksowalne.

Klucze cache uwzględniają tożsamość, locale i wersję danych. Publikacja, wycofanie oraz zmiana sluga unieważniają treść, zestaw odpowiedników i sitemap po udanym commit transakcji. Wycofana wersja nie może pozostać dostępna przez cache.

**Odbiór:** test HTML bez JS, wzajemności i self-canonical, różnych slugów, braku domyślnego tłumaczenia, wycofania publikacji oraz pojedynczego 301 po zmianie sluga. Dla wszystkich URL w wygenerowanych adnotacjach sprawdź wynik 200 i kanoniczność.

### E8 — automatyczne bramki i odbiór P0

Kontrolę katalogów wprowadź już w E3 i rozszerzaj przy E5, nie dopiero na końcu. Waliduje aktywne języki danego obszaru, brak/nadmiar kluczy, parametry i wymagane kategorie pluralizacji według języka — nie identyczną liczbę form dla PL i EN. EN-only nie wymaga plików PL. Wygenerowane typy/artefakty mają być deterministyczne i sprawdzane pod kątem aktualności.

Kontrola tekstów użytkowych obejmuje JSX, atrybuty a11y, konfiguracje menu, toasty/dialogi, PHP i szablony wiadomości. Preferuj analizę składniową; sam grep nie jest wystarczającym dowodem. Wyjątki wyłącznie precyzyjne dla tekstów technicznych, bez wyłączenia całych ekranów. Testy samej kontroli muszą udowodnić wykrywanie brakującego klucza, parametru, formy mnogiej i literalnego komunikatu. Integracja z CI/hookami wymaga review zgodnie z regułami jakości, bez osłabiania obecnych kontroli.

Macierz końcowa:

| Konfiguracja/scenariusz | Obowiązkowy wynik |
| --- | --- |
| Publiczne tylko EN; panel tylko EN | Brak wymogu PL i zbędnego przełącznika |
| Publiczne EN+DE, default EN | `/` EN; `/de/` DE |
| Publiczne DE+EN, default DE | `/` DE; `/en/` EN; panel bez wyboru EN |
| Publiczne EN; panel EN+DE | Wybór DE panelu nie tworzy `/de/` publicznie |
| PL/EN/DE w danych testowych | Parametry, pluralizacja i formaty poprawne |
| Panel EN, treść DE | Formularz/komunikaty EN, rekord DE |
| Brak/draft/wycofanie wersji | 404 i brak w SEO/cache/przełączniku jako aktywny link |
| SSR/job A → B oraz równoległy SSR | Brak przecieku języka |

## 5. Weryfikacja i przekazanie

Po każdym etapie uruchom najwęższe adekwatne testy przez `rtk make test ARGS='--filter=...'` (wstaw rzeczywistą nazwę testu). Istniejące testy auth/settings rozszerzaj zamiast tworzyć drugi równoległy zestaw bez potrzeby. Użyj skillu `testing-best-practices`; dla wdrażanych obszarów aktywuj odpowiednio Laravel, Fortify, Inertia, Wayfinder i skille UI.

Po zmianach PHP: `rtk docker compose exec app vendor/bin/pint --dirty --format agent`. Dla frontendu: `rtk make npm ARGS='run types:check'`, `rtk make npm ARGS='run check'`, `rtk make npm ARGS='run check:ui-contract'`. Sprawdź build klienta/SSR przez `rtk make npm ARGS='run build:ssr'`. Dodane kontrole i18n podłącz do istniejącego kontraktu `make check`, a następnie je uruchom. Testy przeglądarkowe obejmują język po nawigacji, SSR/hydrację i RWD; build sam ich nie zastępuje.

Na koniec wypełnij raport: ukończone etapy, zmienione pliki, faktyczne komendy i wyniki, niewykonane kontrole z przyczyną, decyzje klienta pozostające przed publikacją. Sprawdź HEAD, status i staged diff; nie zmieniaj cudzego indeksu. Zgodnie z regułą projektu poproś człowieka o pełny `rtk make test` po zaliczeniu testów celowanych.

Fundament P0-A jest gotowy po E1–E5 i działających bramkach właściwych dla tego zakresu. Pełne i18n Website Kit wymaga dodatkowo E6–E8 i odbioru wszystkich punktów dokumentu 03. Nie oznaczaj całej wielojęzyczności jako ukończonej po samym przetłumaczeniu landing page.

## 6. Routing i utrzymywalne i18n — ustalenia 2026-09-11

Ta sekcja doprecyzowuje E0–E5. Jest planem refaktoru, nie deklaracją wykonania. Bieżące zlecenie obejmuje dokumentację i przekazanie; kolejny agent realizuje zakres z otrzymanego promptu.

### 6.1. Wzorzec routingu z projektu blog

Porównano lokalnie `/Users/domin/projects/laravel/blog/routes/{web,localized,admin,auth,settings}.php`. W blogu `web.php` rejestruje grupy językowe, `localized.php` zwraca funkcję przyjmującą locale i definiuje strony jeden raz, a `admin.php` ma wspólny prefix `admin`, nazwy `admin.*` i middleware. To wzorzec organizacji do wykorzystania; nie kopiować kontrolerów, polityk dostępu ani zależności bloga.

Docelowy podział startera:

| Plik | Odpowiedzialność |
| --- | --- |
| `routes/web.php` | Rejestracja grup publicznych z konfiguracji, dołączenie pozostałych plików, świadomie wybrane trasy systemowe/aliasy |
| `routes/front.php` | Publiczne definicje stron i formularzy zapisane jeden raz; ładowane dla defaultu i dodatkowych języków |
| `routes/admin.php` | Wspólna grupa `/admin`, `admin.*`, dotychczasowe wymagania dostępu; definicje funkcji panelu |
| `routes/auth.php` | Jawny punkt organizacji auth, z uwzględnieniem routów rejestrowanych przez Fortify |
| `routes/settings.php` | Ustawienia konta z istniejącymi URL i middleware; dołączane obok admin, poza jego prefiksem |

Nową stronę dodajemy tylko w `front.php`, np. `Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index')`. Grupy zapewniają `/articles`, `/pl/articles`, `/de/articles` zgodnie z aktywnymi językami i defaultem. Nowy CRUD dodajemy wewnątrz grupy w `admin.php`, np. `Route::resource('articles', ArticleController::class)`: wynik to `/admin/articles` i `admin.articles.*`. To przykłady konwencji, nie polecenie tworzenia modułu Articles. Ogranicz resource do faktycznie zaimplementowanych akcji, a uprawnienia operacji sprawdzaj policies.

Domyślny publiczny język nie ma prefiksu. Zachowaj istniejące nazwy bazowe i `localized.*` dla dodatkowych języków; przejście na `en.*`/`pl.*`/`de.*` wymaga osobnej, uzasadnionej migracji wszystkich konsumentów. Nie kopiować z bloga stałego `pl`, powtarzanych pętli rejestracji grup, zbędnych map `resource()->names(...)` ani lokalizacji ustawień konta.

Dla wspólnych ścieżek wystarczy zwykły plik definicji ładowany w grupach. Funkcja przyjmująca locale jak w blogu jest uzasadniona, gdy faktycznie potrzebne są tłumaczone statyczne ścieżki (`/o-mnie`, `/en/about`). Nie budować routera ani obsługi wszystkich strategii URL na zapas. Dodanie języka nie wymaga kopiowania endpointów. Tłumaczone slugi Content należą do przyszłego modelu danych, nie do tablicy wszystkich rekordów w pliku routów.

### 6.2. Uproszczenie backendu

- Obszar `public/admin` oznaczaj jawnie przy rejestracji grup; usuń rozrastające się listy rozpoznawania ścieżek. Uwzględnij endpointy Fortify/passkeys rejestrowane poza własnymi plikami, kolejność middleware i odpowiedzi błędów.
- Locale i fallback ustawiaj spójnie dla translatora Laravel i payloadu Inertia. Zachowaj konfigurację osobnych języków publicznych i panelowych oraz walidację konfiguracji.
- Usuń redundantny, mutowalny kontekst requestu ze singletona. Preferuj jawny kontekst requestu; jeśli obiekt jest potrzebny, ogranicz jego czas życia do requestu/joba i sprawdź zależności singletonów. Nie zastępuj jednego managera zestawem pustych warstw.
- Wykorzystaj loader/translator Laravel do katalogów. Własny adapter niech wybiera dozwolone grupy UI i konwertuje uzgodniony format. Usuń dublowanie tych samych komunikatów z prefiksem grupy i bez niego, migrując użycia oraz typy.
- Zachowaj jeden generator publicznych URL i Wayfinder. Usuń założenie `locale === 'en'` z frontendu; default oraz aktywne języki pochodzą z backendu. Jawnie obsłuż nieaktywny język docelowy: generator publiczny nie emituje nieistniejącego URL; link wiadomości mapuje niedostępną preferencję panelu na aktywny default publiczny. Nie stosować tego fallbacku do brakującej treści redakcyjnej.
- W wiadomościach korzystaj z natywnego `HasLocalePreference`/locale powiadomienia i przywracania kontekstu oferowanego przez Laravel, gdy pokrywa dany przypadek. Własne middleware joba zachowuj tylko dla zadań, które go potrzebują; obsłuż wyjątki i aktywność preferencji.
- Przejrzyj lokalną kopię routów Fortify pod kątem driftu względem zainstalowanej wersji. Wybierz najmniejszą obsługiwaną integrację. Nie usuwać zabezpieczeń, signed URL, throttle, MFA ani funkcji auth w imię uproszczenia.

### 6.3. React: i18next i react-i18next

Cel: gotowy silnik interpolacji, pluralizacji i integracji React zamiast utrzymywania `createTranslator`. Biblioteka nie rozstrzyga routingu, języka publicznego ani preferencji panelu. Nie dodawaj automatycznego detektora języka przeglądarki ani dodatkowego endpointu katalogów, jeśli zasoby dostarcza Inertia.

Zachowaj jedno źródło autorskie wspólnych komunikatów. Przed migracją przygotuj mały przekrój katalogu: tekst, parametr Laravel `:name`, pluralizacja PL/EN/DE, walidacja i a11y. Zdecyduj o namespaces, składni parametrów i mapowaniu form liczby mnogiej; formaty Laravel i i18next nie są zamienne. Adapter/generator konwertuje format, nie implementuje ponownie silnika pluralizacji ani parsera ICU. Typy kluczy generuj z uzgodnionego schematu, bez uzależniania od obowiązkowego PL.

Instancja SSR należy do konkretnego renderowania i otrzymuje locale oraz zasoby z props. Provider React zastępuje globalny `activeTranslator`; kod poza komponentami otrzymuje translator jawnie. Nawigacja Inertia aktualizuje locale i katalog atomowo. Nie inicjalizować tłumaczonych tablic na poziomie modułu ani zmieniać globalnego języka w trakcie renderowania. Pierwszy HTML, hydracja i klient używają tego samego kontekstu.

Dokumentacja do kwalifikacji: [SSR i provider](https://react.i18next.com/latest/ssr), [pluralizacja](https://www.i18next.com/translation-function/plurals), [interpolacja](https://www.i18next.com/translation-function/interpolation). Sprawdź aktualne wersje, peer dependencies i zgodność z Inertia v3 przed instalacją; sam wybór bibliotek nie dowodzi gotowości integracji.

### 6.4. Konflikt kontraktu auth i granice migracji

Starsza sekcja 3 oraz dokument 01 przypisują auth panelu do preferencji administracyjnej i URL bez locale. Kod roboczy i testy zawierają też publiczne lokalizowane auth. Nie można jednocześnie uznać obu strategii za jeden obowiązujący kontrakt.

Refaktor organizacji zachowuje istniejące działające URL, nazwy i zasady dostępu. Agent ma zinwentaryzować endpointy, wskazać które logują do panelu, a które służą publicznym użytkownikom, oraz przygotować konkretną propozycję rozstrzygnięcia z wpływem na formularze, przekierowania, reset hasła i podpisane linki. Zmiana zachowania auth lub usunięcie adresów wymaga akceptacji tego konkretnego zakresu. Nie traktować zgody na wybór bibliotek jako zgody na migracje DB czy zmianę zasad dostępu. Niezależne prace kontynuować bez ponownego pytania o już uzgodniony podział plików i wybór i18next.

### 6.5. Kolejność i kryteria odbioru refaktoru

1. Inwentaryzacja obecnego diffu, tabeli routów, konsumentów Wayfinder i formatu katalogów; kwalifikacja wersji bibliotek i wskazanie konfliktu auth.
2. Wyodrębnienie `front.php`, wspólna grupa admin, jednorazowe dołączenie settings poza prefiksem admin. Test zgodności metod, URI, nazw, parametrów i middleware przed/po; sprawdzenie route cache na izolowanej konfiguracji testowej.
3. Uproszczenie kontekstu i fallbacku backendu, generatora URL oraz adaptera katalogów. Reprodukcja usterek przed poprawką.
4. Migracja na i18next/react-i18next, usunięcie starego silnika i globalnego translatora, odświeżenie wygenerowanych typów/Wayfinder.
5. Testy celowane, bramki katalogów, typecheck, lint, build SSR, rzeczywisty test SSR/hydracji, adekwatny review gotowego diffu według AGENTS.md i końcowy raport.

Wymagane scenariusze: default EN i DE; publiczne EN-only bez PL; publiczne EN i panel EN+DE; poprawne adresy formularzy GET/POST i walidacja; brak lokalizowanych tras panelu; nieaktywny język generatora i poprawny link resetu; zgodny fallback PHP/UI przy brakującym komunikacie; parametry i kategorie mnogie PL/EN/DE; pierwsza odpowiedź HTML; kolejne i równoległe SSR bez przecieku; przełączenie języka po nawigacji; kolejne joby także po wyjątku. Nie uznawać testów konfiguracji ani samego buildu za zastępstwo testów zachowania.

Nie obejmuje tworzenia Content, zmiany schematu DB, wdrożenia produkcyjnego, nowego RBAC, przebudowy design systemu ani automatycznego commita/pusha. Etapy E6–E8 w zakresie przyszłych funkcji CMS pozostają osobnym zadaniem. Na końcu uaktualnij tę dokumentację o faktycznie ukończony zakres i ograniczenia.

### 6.6. Raport wdrożenia refaktoru routingu i i18n — stan na 2026-09-11

Refaktor routingu oraz i18n został zrealizowany zgodnie z planem sekcji 6:

1. **Wydzielenie `routes/front.php`**:
   - Utworzono `routes/front.php` z definicją `home` (i miejscem na przyszłe publiczne strony/formularze).
   - `routes/web.php` rejestruje trasy publiczne jeden raz: dla domyślnego języka (bez prefiksu) oraz w pętli dla dodatkowych języków (`/{locale}`) z prefiksem nazw `localized.*`.
   - Nowe trasy publiczne dodaje się wyłącznie w `routes/front.php`.

2. **Uporządkowanie `routes/admin.php` i `routes/settings.php`**:
   - `routes/admin.php` posiada wspólną grupę z prefiksem `/admin`, prefiksem nazw `admin.` oraz middleware `['auth', 'verified', EnsureCanAccessAdminPanel::class]`.
   - Ścieżka `/admin` jest zarejestrowana jako `Route::inertia('/', 'admin/index')->name('index')` (pełna nazwa `admin.index`).
   - Usunięto dołączenie `settings.php` z wnętrza `admin.php`; `require __DIR__.'/settings.php'` znajduje się teraz bezpośrednio w `routes/web.php` obok `admin.php`.

3. **Uproszczenie backendu lokalizacji**:
   - `LocalizationManager` został uwolniony od mutowalnego stanu żądania w singletonie. Kontekst `area` oraz `locale` jest zapisywany w atrybutach żądania HTTP (`$request->attributes`) i pobierany bezpośrednio z żądania.
   - Jawne rozpoznawanie obszaru: sprawdzanie atrybutów/defaults dopasowanej trasy (`_area`), nazw `admin.*`/`settings.*`/`profile.*` itd., z bezpiecznym fallbackiem pre-routingowym.
   - Spójne locale i fallback: `ResolveLocalization` ustawia `app()->setLocale($locale)` oraz `app()->setFallbackLocale($fallback)` spójnie dla backendu Laravel i payloadu Inertia.
   - `LocalizedUrlGenerator` sprawdza aktywność publicznego języka docelowego: w przypadku nieaktywnego języka automatycznie powraca do aktywnego `public_default`.

4. **Migracja frontendu na `i18next` + `react-i18next`**:
   - Zainstalowano stabilne i zgodne z React 19 wersje: `i18next` (^26.4.2) oraz `react-i18next` (^17.0.13).
   - Usunięto własny silnik interpolacji/pluralizacji i zlikwidowano globalną mutowalną zmienną `activeTranslator`.
   - Zaimplementowano `resources/js/i18n/adapter.ts`: konwersja parametrów `:param` oraz pluralizacji `{ one, few, many, other }` na standard i18next (`{{param}}`, `_one`, `_few`, `_many`, `_other`).
   - Wdrożono `I18nProvider` w `resources/js/app.tsx`, który izoluje instancję `i18next` na poziomie renderowania React.
   - Usunięto założenie sztywnego `en` w `resources/js/lib/localized-routes.ts` poprzez obsługę konfigurowalnego domyślnego locale (`defaultLocale`).

5. **Weryfikacja macierzy jakości**:
   - Testy automatyczne PHP (64 testy: Localization, Routing, Admin, AsyncJob, LocalizedNotificationEmail) — 100% PASS.
   - Pint formatting — PASS (`{"tool":"pint","result":"passed"}`).
   - TypeScript `tsc --noEmit` — 0 błędów (PASS).
   - Kontrakt UI `check:ui-contract` (ADR-019) — PASS (93 pliki UI, 64 wyjątki).
   - Rzeczywista weryfikacja SSR `scripts/verify-ssr-i18n.mjs` (`npm run test:ssr`) — renderowanie EN/PL/DE, brak wycieku stanu pomiędzy kolejnymi i współbieżnymi renderowaniami (PASS).

#### Jak dodawać nowe trasy (Przewodnik programisty):

1. **Nowa trasa publiczna**:
   Dodaj definicję wyłącznie w `routes/front.php`:
   ```php
   // routes/front.php
   Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
   ```
   Wynik:
   - Domyślny język (np. EN): URL `/articles`, nazwa trasy `articles.index`.
   - Dodatkowy język (np. PL, DE): URL `/pl/articles`, `/de/articles`, nazwa trasy `localized.articles.index`.
   - Linki kanoniczne i przełącznik języków automatycznie uwzględniają nową trasę przez `LocalizedUrlGenerator`.

2. **Nowy CRUD w panelu administracyjnym**:
   Dodaj zasób wewnątrz grupy w `routes/admin.php`:
   ```php
   // routes/admin.php
   Route::middleware(['auth', 'verified', EnsureCanAccessAdminPanel::class])
       ->prefix('admin')
       ->name('admin.')
       ->group(function () {
           Route::inertia('/', 'admin/index')->name('index');
           Route::resource('articles', ArticleAdminController::class)->except(['show']);
       });
   ```
   Wynik:
   - URL: `/admin/articles`, `/admin/articles/create`, `/admin/articles/{article}/edit`.
   - Nazwy tras: `admin.articles.index`, `admin.articles.create`, `admin.articles.store`, `admin.articles.edit`, `admin.articles.update`, `admin.articles.destroy`.
   - Ochrona: Pełne middleware admina (`auth`, `verified`, MFA/role check).
   - Brak prefiksów językowych w URL: język interfejsu panelu wynika z profilu użytkownika (`admin_locale`) lub sesji.
