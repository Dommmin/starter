# Audyt UI strony publicznej i plan poprawek

Data: 2026-10-05 · Gałąź: `feat/small-apps-kit` · Środowisko: lokalny Docker (`make up`)

## Zakres i metoda

Przejrzano strony `/`, `/pl`, `/about`, `/articles`, `/articles/{slug}`, `/login`,
`/register` i 404. Sprawdzono je w trybie jasnym i ciemnym, w widokach desktop
1440 px i mobile 390 px. Sprawdzono też przełącznik motywu, przełącznik języka,
submenu i menu mobilne. Zrzuty powstały w Playwright. Nie wystąpiły błędy JS ani
poziome przewijanie, a wszystkie strony poza 404 zwracają 200. Dla każdego
problemu ustalono przyczynę w kodzie.

Oznaczenie **[DS]** oznacza, że poprawka zmienia publiczne API design systemu
(nowy wariant, prop albo zmianę układu prymitywu). Zgodnie z ADR-019 i
`.claude/rules/frontend.md` taka poprawka wymaga akceptacji przed
implementacją.

## Lista problemów

### P1: błędy widoczne dla użytkownika

| # | Problem | Gdzie | Przyczyna |
|---|---------|-------|-----------|
| 1 | Przycisk „Log in” w sekcji CTA jest niewidoczny w **obu** motywach: widać tylko pustą ramkę. | Strona główna, sekcja „Ready to give your project the right rhythm?” | `CTA` ma `tone="inverted"`, ale `Actions` w `components/home/home-sections.tsx:138` renderuje `Button variant="outline"` z `text-foreground`. Na tle `surface-inverted` kolor tekstu jest równy kolorowi tła. |
| 2 | `/about` zwraca goły tekst „about” bez layoutu, a CMS nie może opublikować strony o slugu `about`. | `routes/front.php:24` | Testowa trasa `Route::get('/about', fn () => 'about')` jest zarejestrowana także w środowiskach innych niż testy. |
| 3 | Karty artykułów nie mają wewnętrznego odstępu poziomego, więc tytuł i opis dotykają krawędzi karty. | Strona główna (najnowsze artykuły), `/articles` | `Card` w `design-system/primitives/card.tsx:13` ma tylko `py-*`. Poziomy padding dodają podkomponenty (`px-6`), a ekrany wkładają treść bezpośrednio do `Card`. |
| 4 | W trybie ciemnym sekcja CTA jest jasnym blokiem, który wyraźnie odstaje od reszty strony. | Strona główna, dark | Token `--surface-inverted` w dark ma wartość `oklch(0.94 …)` (`resources/css/app.css:178`). Inwersja daje jasny pas zamiast akcentu. |

### P2: układ nagłówka i spójność

| # | Problem | Przyczyna |
|---|---------|-----------|
| 5 | Menu główne jest przyklejone do prawej i wymieszane z narzędziami (motyw, język) i kontem. Kolejność to motyw → język → menu → logowanie, więc menu nie da się szybko znaleźć. | `HeaderUtility` (`header-utility.tsx`) ma układ `justify-between` z dwiema grupami: logo oraz jeden `<nav>` z `ThemeSwitcher`, `LocaleSwitcher` i `children` (menu + auth). Brakuje osobnego slotu na menu. **[DS]** |
| 6 | Landmark `nav` „Main navigation” obejmuje też przełączniki i przyciski konta, co jest problemem a11y. | Jak w #5. **[DS]** |
| 7 | Różne rozmiary tekstu w nagłówku: Features 16 px, Articles 14 px, język 14 px, Log in i Register 12 px. | `topLinkClasses` nie określa rozmiaru, trigger submenu ma `text-sm`, przyciski auth `size="sm"` mają `text-xs`. |
| 8 | Link „Features” jest stale podkreślony, a „Articles” (przycisk submenu) nie jest. Wygląda to jak stan aktywny, choć nim nie jest. Brak prawdziwego stanu aktywnego (`aria-current`). | `topLinkClasses` zawiera `underline`. `NavItemLink` nie oznacza bieżącej strony. |
| 9 | Przełącznik motywu to sama ikona monitora bez etykiety (stan „System”). Nie wiadomo, do czego służy, a dropdown otwiera się przesunięty w lewo względem triggera. | `ThemeSwitcher`. Wymaga decyzji: ikona z tooltipem albo segmentowy przełącznik. **[DS]** |
| 10 | Przełącznik języka pokazuje pełną nazwę („English”, „Polski”), która zajmuje dużo miejsca w pasku, a na mobile jest tylko ikoną globu. | `LocaleSwitcher`. Decyzja: kod (`EN`) czy nazwa. **[DS]** |
| 11 | Submenu „Articles” zawiera ponownie „Articles”, a zewnętrzny „Docs” nie ma oznaczenia zewnętrznego linku. | `DesktopSubmenu` dokleja rodzica jako pierwszą pozycję, a `NavItemLink` nie ma ikony dla linku zewnętrznego. |
| 12 | Mobile: nazwa „Studio Test” łamie się w dwie linie, a w nagłówku jest 5 okrągłych kontrolek (motyw, język, logowanie, rejestracja, menu). Szuflada menu nie zawiera motywu, języka ani logowania. | `BrandLogo` nie ma `whitespace-nowrap`/truncate. Utility nie przenosi się do `MobileNav`. **[DS]** |
| 13 | Niespójne rozmiary przycisków: hero `lg`, „Send message” mniejszy tekst na pełną szerokość, „All articles” mały. | Każda sekcja dobiera `size` osobno. Trzeba ustalić regułę: CTA sekcji `lg`, submit formularza `default`. |
| 14 | Hierarchia w sekcji Features: tytuł cechy (ok. 13 px) jest mniejszy od opisu, a ikony są bardzo małe. | `FeatureGrid` używa wariantu tekstu tytułu mniejszego niż body. **[DS]** |
| 15 | Sekcja „Najnowsze artykuły” nie ma nagłówka, a karty zajmują 2 z 3 kolumn, wyrównane do lewej, mimo że reszta strony jest wyśrodkowana. | Sekcja nie przekazuje tytułu, grid ma stałe 3 kolumny. |
| 16 | Stopka na krótkich stronach (`/articles`, artykuł, 404) kończy się w połowie ekranu, a pod nią zostaje pusta przestrzeń. | `PublicChrome` nie ma układu `min-h-screen` + `flex-1` dla `main`. |
| 17 | Stopka jest uboga: tylko „Contact” i e-mail, bez nawigacji ani linków prawnych. | Brak menu `footer` w danych. Do decyzji, czy starter ma je seedować. |
| 18 | Strony auth: marka „Punkt Startowy” zamiast „Studio Test”, logo Laravel zamiast logo marki i brak powrotu do strony. | Layout auth bierze nazwę z katalogu i18n, a nie z `site_settings`. **Poprawione 2026-10-05:** nowy prymityw `AuthShell` (nagłówek z marką z ustawień, przełącznik języka i motywu, wąska kolumna w `main#main-content`, jedno `h1`, bez logo Laravel). `HeaderUtility` domyślnie bierze markę z `site`. Szablony `layouts/auth/auth-{simple,card,split}-layout.tsx` są nieużywane i do usunięcia razem z ich wyjątkami UI (zmiana rejestru wyjątków wymaga przeglądu człowieka). |
| 19 | ~~Inny kolor tekstu przycisku na stronie logowania w dark.~~ **Fałszywy alarm:** oba przyciski mają `text-primary-foreground` (ciemny w dark). Różnicę dał zrzut JPEG. | — |

### P3: treści i dane (nie kod)

| # | Problem | Przyczyna |
|---|---------|-----------|
| 20 | Stopka „© 2026 Studio Test - stopka EN” wyświetla się także po polsku. | Tekst wpisany ręcznie w panelu (`site_setting_translations`), brak wersji PL, działa fallback. |
| 21 | `/pl` nie ma sekcji artykułów, choć istnieje polski artykuł. | `home_sections` (pl, `latest_articles`) ma `enabled = false`. Kolejność sekcji EN też różni się od PL i DE. |
| 22 | Artykuł „Coming next week” ma opis „stay hidden until publication date”, ale data już minęła i artykuł jest widoczny. | Seeder ustawił stałą datę. Demo powinno liczyć ją względnie. |
| 23 | Baza zawiera śmieci po testach E2E i ręcznych (potwierdzone: `make e2e` losuje hasło kont `e2e-*` przy każdym uruchomieniu, więc lokalnie nie ma konta ze znanym hasłem): 2 strony `e2e-page-*`, ok. 15 kont `*@mailinator.com`, „Docs” w menu i konta `e2e-*`. Nie ma konta demo `test@example.com`. | E2E działało na lokalnej bazie deweloperskiej. |
| 24 | Copy demo jest techniczne (ADR-002, „zero hydration mismatch”) i nie nadaje się do pokazania klientowi. | Treść demo z seederów. |

### Znalezione po świeżym seedzie (2026-10-05)

| # | Problem | Przyczyna | Status |
|---|---------|-----------|--------|
| 25 | Stopka i tytuł pokazują „Laravel” („© 2026 Laravel”, „… - Laravel”), a logo pokazuje „Punkt Startowy”. | `seo.site_name` i `seo.organization.name` spadały na `APP_NAME` (domyślnie `Laravel`). | Poprawione: bez zapisanych ustawień i bez `SEO_SITE_NAME` używana jest marka z katalogu (`common.brand.name`). `documentTitle` nie dokleja nazwy, gdy tytuł już ją zawiera. |
| 26 | Stopka dubluje pojedynczy link: „Privacy policy” jako tytuł kolumny i link pod nim. | `Footer` robił z każdego samodzielnego linku osobną kolumnę z tytułem równym etykiecie. | Poprawione: samodzielne linki trafiają do jednej kolumny „Informacje”. |
| 27 | Po świeżym seedzie strona główna nie ma sekcji artykułów w żadnym języku. | `HomeSectionSeeder` ustawia `latest_articles` jako wyłączoną. | Poprawione (decyzja 2026-10-05): demo włącza sekcję artykułów, a `init-project --remove-demo` nadal ją ukrywa. |

Przy resecie zauważono też:
- `make backup` pada (`tar: media: Cannot open: Permission denied`).
- Ustawienia strony w cache (`rememberForever`) przetrwają `migrate:fresh` i trzeba wywołać `cache:clear`.

## Plan poprawek

Kolejność uwzględnia ryzyko i zależności. Każdy krok to osobny, mały commit z
testem.

1. **Szybkie poprawki bez zmiany API DS** (#2, #3, #7, #8, #11, #15, #16). **Status: zacommitowane 2026-10-05 (`d2d9ec7`, `9b2a787`, `5206caa`).**
   Odstępstwa: w #11 zostaje pozycja-rodzic w submenu, bo trigger to
   przycisk i bez niej `/articles` byłby nieosiągalny z menu desktopowego
   (właściwe rozwiązanie to split-button, krok 3). W #15 dodano nagłówek,
   a wyrównanie gridu zostaje (wymaga nowego propa `Grid`, **[DS]**).
   - Ograniczyć trasę `/about` do środowiska `testing` albo przenieść ją do
     testu. Test: `/about` poza testami zwraca 404 albo stronę CMS.
   - Dodać poziomy padding w `Card` dla treści bezpośredniej (wewnętrzna
     zmiana prymitywu, publiczne props bez zmian).
   - Ujednolicić rozmiar tekstu w nagłówku i usunąć stałe podkreślenie.
     Dodać `aria-current="page"` i styl stanu aktywnego.
   - Submenu bez duplikatu rodzica, ikona i `sr-only` dla linku zewnętrznego.
   - Nagłówek sekcji artykułów, grid dopasowany do liczby kart.
   - Stopka przyklejona do dołu (`PublicChrome`).
   - Testy: Vitest a11y dla `PublicHeader` i `PublicChrome`, Pest dla trasy.
2. **Kontrast w CTA** (#1, #4) **[DS]**. **Status: zacommitowane 2026-10-05 (`b398e62`, D4 = ciemny akcent).**
   Wykonanie: nowa para tokenów `--surface-emphasis(-foreground)` we wszystkich
   czterech motywach. W dark to ciepły ciemny akcent zamiast jasnego pasa.
   Ton `inverted` w `Section`, `Surface`, `Heading` i `Text` używa tych
   tokenów, a `surface-inverted` zostaje dla tooltipów. `Section` z tonem
   inverted ustawia `data-tone`, a przyciski `outline` i `ghost` wewnątrz
   biorą kolor z tego kontekstu. Publiczne props bez zmian. Pierwotny plan: dodać `Button` wariant albo ton
   `inverted` (outline na ciemnym/jasnym tle inwersji), ewentualnie
   kontekst tonu z `Section`. Poprawić `--surface-inverted` w dark, żeby był
   ciemnym akcentem, nie jasnym pasem. Test: kontrakt tokenów light/dark i
   test kontrastu przycisku w CTA.
3. **Nowy układ nagłówka** (#5, #6, #9, #10, #12) **[DS]**. **Status: #5 i #6 zacommitowane 2026-10-05 (`be12397`, D1 = menu od lewej); #9 i #10 zrobione, bez commita (D2, D3).**
   `HeaderUtility` dostał opcjonalny slot `navigation` obok logo, a landmark
   `nav` obejmuje już tylko menu. Menu desktopowe od `lg` (na 768 px nie
   mieściło się z przełącznikami), etykiety przycisków się nie łamią.
   #9: motyw w menu konta (grupa nad pozycjami `UserMenuContent`, bo plik
   ma wyjątek UI przypięty do treści), dla gościa przełącznik z etykietą w
   stopce, a na stronach auth zostaje ikona. #10: kod języka (`PL`) zawsze
   widoczny, w liście kod + nazwa. #12 częściowo: na 360 px w nagłówku są
   4 kontrolki zamiast 5. Pierwotny plan: logo | menu
   (wyśrodkowane albo od lewej, do decyzji) | narzędzia + konto. `nav`
   obejmuje tylko menu. Mobile: logo + menu + CTA, a motyw, język i
   logowanie trafiają do szuflady. Wymaga makiety i akceptacji przed kodem.
4. **Spójność komponentów** (#13, #14, #18) **[DS]** dla #14: reguła
   rozmiarów przycisków, hierarchia `FeatureGrid`, marka i logo w layoucie
   auth z `site_settings`.
5. **Dane i seedy** (#20–#24): oczyścić lokalną bazę przez
   `migrate:fresh --seed` (to niszczy dane, dlatego tylko za zgodą),
   względne daty w `ArticleSeeder`, sekcja artykułów włączona dla PL,
   izolacja E2E od bazy deweloperskiej (osobna baza w `compose.e2e.yaml`),
   decyzja o menu stopki i copy demo.
6. **Weryfikacja wizualna**: zrzuty light/dark × desktop/mobile dla tych
   samych stron przed i po zmianach, akceptacja człowieka.

## Decyzje do podjęcia przed krokami 2–4

- ~~D1~~: menu od lewej (decyzja 2026-10-05).
- ~~D2~~: menu konta, a dla gościa stopka (decyzja 2026-10-05).
- ~~D3~~: kod języka (decyzja 2026-10-05).
- ~~D4~~: ciemny akcent (decyzja 2026-10-05).
- D5: czy wyczyścić lokalną bazę (`migrate:fresh --seed`)?
