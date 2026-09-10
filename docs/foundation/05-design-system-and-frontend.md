# Design system i frontend

## Cel

Przenosić design z Figmy do spójnego, dostępnego UI bez tworzenia nowego zestawu komponentów w każdym projekcie. Nie dostarczono pliku Figmy ani istniejącego UI; paleta i marka nie są jeszcze ustalone.

## Decyzje i uzasadnienie

### ADR-012: shadcn/Radix jako baza, własne tokeny i prymitywy jako API

Status: kierunek zatwierdzony. React, TypeScript, Inertia, Tailwind i shadcn/ui stanowią bazę zgodną z oficjalnym starterem; Radix dostarcza zachowania złożonych, dostępnych kontrolek. shadcn/Radix nie są publicznym API aplikacji: kod po review trafia do warstwy prymitywów, z której korzystają dopiero komponenty design systemu. Konkretny zestaw wersji i tooling musi zostać przypięty podczas P0-A. [Oficjalny starter](https://laravel.com/framework/docs/13.x/starter-kits).

Komponenty shadcn to kod utrzymywany w aplikacji: aktualizacje upstream wymagają porównania i review, nie samego podbicia npm. Nie instalować pełnego katalogu. Alternatywy: własny HTML/CSS (mniej zależności, większy koszt dostępności złożonych widgetów) lub Filament (szybciej CRUD, drugi stos i mniej swobody Figmy). [Repozytorium shadcn/ui](https://github.com/shadcn-ui/ui).

### ADR-013: jeden system React/Inertia, dwa charaktery wizualne

Status: kierunek zatwierdzony. CSS custom properties są źródłem kolorów, typografii i geometrii; Tailwind mapuje je do użytecznych nazw. React/Inertia obsługuje zarówno panel, jak i publiczny frontend SSR. Obie części współdzielą tokeny, prymitywy, a11y i ikony, lecz frontend jest editorialny/content-first, a panel utility-first i gęstszy. Nie tworzyć niezależnych systemów UI ani nie używać domyślnego wyglądu shadcn jako marki.

## Tokeny

| Grupa       | Plan                                                                                              | Zasada zmiany                                                              |
| ----------- | ------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| Kolory      | `background`, `surface`, `foreground`, `muted`, `border`, `primary`, `danger`, `success`, `focus` | Parami tło/tekst; marka klienta na poziomie tokenów                        |
| Typografia  | Font body/display, rozmiary, line-height i weight                                                 | Font systemowy P0; zewnętrzny dopiero po sprawdzeniu licencji i hostowania |
| Spacing     | Skala oparta o 4 px: 4/8/12/16/24/32/48/64                                                        | Wyjątki z Figmy jawne, nie przypadkowe arbitralne wartości                 |
| Radius      | sm/md/lg i kontrolowana wartość brand                                                             | Te same role dla formularzy, kart i dialogów                               |
| Shadows     | focus, overlay, raised                                                                            | Nie zastępują kontrastu i obramowania                                      |
| Breakpoints | Startowo 640/768/1024/1280 px                                                                     | Dopasować do zachowania treści, nie tylko makiety desktop                  |
| Motion      | Krótkie przejścia i reduced-motion                                                                | Brak animacji blokującej interakcję                                        |

Light i dark mode są częścią kontraktu P0. Oba motywy definiują ten sam komplet semantycznych tokenów; przełączenie następuje na korzeniu dokumentu, bez ręcznych wariantów `dark:` i warunków motywu w komponentach. Kontrolka wyboru motywu jest osobnym elementem UX; jej brak nie zwalnia z obsługi obu motywów. Zmiana jednego tokena wymaga przeglądu kluczowych stanów i kontrastu. Brand nie zmienia znaczenia danger/success ani zasad focus.

## LLM-safe UI contract

### ADR-019: zamknięte API UI i egzekwowanie kontraktu w CI

Status: **wymaganie użytkownika** co do ograniczeń; poniższy podział plików i API jest proponowaną realizacją. Design system ogranicza zbiór dozwolonych decyzji, zamiast jedynie sugerować styl. Zastępuje wcześniejsze odłożenie dark mode do P1. Nie oznacza zgody na implementację aplikacji w obecnym zadaniu dokumentacyjnym.

### Granica stylowania

Proponowana struktura, jeszcze nieistniejąca:

| Warstwa                                                    | Dozwolone                                                                 | Niedozwolone                                                        |
| ---------------------------------------------------------- | ------------------------------------------------------------------------- | ------------------------------------------------------------------- |
| Definicje tokenów `resources/css/design-system/tokens.css` | Wartości bazowe i mapowanie semantyczne dla light/dark                    | Import surowej palety przez kod aplikacji                           |
| Prymitywy `resources/js/design-system/primitives/**`       | Wewnętrzne klasy, statyczne mapy wariantów, kontrolowane style dynamiczne | Eksport nieograniczonego API stylowania                             |
| Komponenty złożone `design-system/components/**`           | Kompozycja prymitywów i skończone warianty                                | Własne `className`, inline styles lub nowe utilities poza wyjątkiem |
| Ekrany, sekcje i moduły aplikacji                          | Publiczne API design systemu, semantyczne props                           | Bezpośrednie stylowanie i importy implementacji/vendor UI           |

`className`, `class`, `style`, arbitrary Tailwind values/properties/variants i helpery `cn`/`clsx`/`cva` są dozwolone wewnętrznie wyłącznie w warstwie prymitywów albo w dokładnie wskazanym wyjątku. Nazwa katalogu `components/ui` nie daje automatycznie takiego prawa. Kod shadcn/Radix wymagający niskopoziomowego DOM/stylowania adaptować do jawnie zarejestrowanych prymitywów; ekrany importują wyłącznie publiczny punkt wejścia. Nie przenosić całej strony do katalogu prymitywów, aby ominąć reguły.

Wartości kolorów i techniczne nazwy palety (np. `slate-700`, `blue-500`, hex/rgb/oklch) występują wyłącznie w definicjach tokenów lub źródłowych assetach brandu. Nawet prymitywy używają semantycznych odwołań: `surface`, `text-primary`, `text-muted`, `border-default`, `action-primary`, `on-action-primary`, `status-danger`, `focus-ring`. Brand asset nie może być furtką do inline SVG kolorującego UI; ikony używają `currentColor`. Skan rozpoznaje kontekst wartości stylu, nie zabrania słowa „blue” w treści klienta.

Nie dopuszczać surowego CSS/CSS modules/CSS-in-JS, tagów `<style>`, `setAttribute('style', ...)`, `element.style` ani dynamicznego dopisywania klas w kodzie aplikacyjnym. Bezpośrednie importy bibliotek omijających API DS i deep imports blokować. Arbitrary values w prymitywach muszą mieć powód (np. geometria pozycjonowania); powtarzalna decyzja powinna stać się tokenem. Nie interpolować wejścia użytkownika do klas ani CSS.

### Typowane prymitywy P0

Nie tworzyć uniwersalnego `Box` przyjmującego dowolny CSS. Minimalne publiczne API, do doprecyzowania przy pierwszych ekranach:

| Prymityw    | Skończone props                                                   | Znaczenie                                                   |
| ----------- | ----------------------------------------------------------------- | ----------------------------------------------------------- |
| `Stack`     | gap: `tight/default/relaxed`; align: `start/center/stretch`       | Pionowy rytm elementów                                      |
| `Inline`    | gap: jak Stack; align: `start/center`; wrap: boolean              | Wiersz akcji lub metadanych                                 |
| `Container` | width: `reading/content/wide`; padding: `page/none`               | Szerokość treści i margines strony                          |
| `Grid`      | layout: `single/split/cards`                                      | Zatwierdzone układy, z responsywnością wewnątrz prymitywu   |
| `Surface`   | tone: `default/subtle/raised`; padding: `compact/default/relaxed` | Powierzchnia wraz z właściwym tekstem i obramowaniem        |
| `Text`      | variant: `body/label/caption`; tone: `default/muted/danger`       | Typografia treści; domyślnie semantyczny element            |
| `Heading`   | level: 1–6; variant: `page/section/subsection`                    | Hierarchia HTML oddzielona od zatwierdzonej skali wizualnej |

Przykład zamierzonego użycia: `Stack gap="relaxed"` + `Heading level={1} variant="page"` + `Text tone="muted"`. `gap={17}`, `tone="blue"`, `className="..."` i `style={{...}}` są błędami kontraktu. Nowy preset responsive też jest wariantem wymagającym uzasadnienia, a nie dowolnym obiektem media query z ekranu.

Publiczne TS props: zamknięte union types, brak index signature oraz `className?: never`, `style?: never`; zamiast dziedziczyć wszystkie HTML props, jawna lista potrzebnych atrybutów semantycznych, zdarzeń i a11y. Brak publicznego `asChild`/dowolnego `as`, `slotProps`, `css`, `sx` czy passthrough do stylowania. Dodawać wyłącznie ograniczone semantyczne `as`, jeśli konkretny komponent tego potrzebuje. Typy nie są sandboxem: `any`, spread i przekazanie props z innego obiektu wymagają dodatkowej kontroli AST; na granicy prymitywu nie forwardować nieznanych props do DOM.

SSR renderuje te same komponenty React; TypeScript nie zastępuje kontroli runtime ani sanitizacji rich textu. Style automatycznie dodawane przez zatwierdzony adapter biblioteki pozycjonowania pozostają odpowiedzialnością prymitywu i muszą być opisane w jego kontrakcie.

### Light/dark bez decyzji na poziomie ekranu

Każdy token semantyczny ma wartość w obu motywach; komponent nie zna motywu, nie używa `dark:` i nie wybiera koloru warunkiem `isDark`. Dopuszczone jest sterowanie korzeniem przez pojedynczy provider/bootstrap motywu, z jasno ograniczonym zakresem. Prymitywy też nie mają ręcznych wariantów kolorów light/dark. Pary surface/foreground oraz action/on-action sprawdzane w obu motywach, także hover, disabled, error i focus. Systemowa preferencja daje domyślny motyw; ewentualny zapis preferencji i inicjalizacja muszą być zgodne z CSP oraz nie powodować błysku niewłaściwego motywu/hydration mismatch.

### Rozszerzenie i wyjątek

Nowy token lub wariant wymaga rekordu w dokumentacji DS: ID, decyzja designowa, dlaczego obecny zestaw nie wystarcza, alternatywy, co najmniej dwa konkretne miejsca planowanego ponownego użycia, kontrakt light/dark/responsive/a11y, właściciel i akceptacja człowieka przed implementacją. „Może kiedyś się przyda” nie jest uzasadnieniem. Jednorazowego przypadku nie promować automatycznie do globalnego tokena; przedstawić go jako kandydat do wyjątku. Zasady dotyczą też początkowych tokenów: wykazać użycie np. w loginie i formularzu strony, nie zatwierdzać całej skali na zapas.

Proponowany rejestr maszynowy `design-system.exceptions.json` powstanie podczas implementacji. Wpis: unikalne ID, reguła, dokładny plik i symbol/atrybut (opcjonalnie fingerprint AST), powód biznesowy/designowy, alternatywy, zakres odstępstwa, owner, link do review człowieka, termin wygaśnięcia i zadanie usunięcia. Marker `DS-EXC-...` przy miejscu użycia musi odpowiadać wpisowi. Zakaz wildcardów katalogowych, globalnego `eslint-disable` i wiecznych wyjątków. Wpis nie nadaje zgody sam sobie: review egzekwuje chroniony workflow i właściciel DS. Zmiana fragmentu objętego fingerprintem wymaga ponownego przeglądu. CI wykrywa wpisy wygasłe, nieużyte i rozszerzony zakres.

### Proponowane reguły ESLint/Oxlint

Nazwy `ds/*` poniżej są specyfikacją **lokalnych reguł do napisania**, nie nazwami istniejącej paczki. Jedno źródło manifestu tokenów, API prymitywów i wyjątków dla wszystkich kontroli.

| Reguła                         | Wykrywa / wymusza                                                                                                                                                                  |
| ------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `ds/no-style-escape`           | Poza prymitywami: JSX `className`/`style`, odpowiedniki helperów, modyfikację stylu/classList i style injection; nieanalizowalny spread odrzuca lub wymaga jawnego mapowania props |
| `ds/semantic-tokens-only`      | Surowe kolory/techniczne nazwy w kontekście stylowania; tokeny spoza manifestu, także w prymitywach                                                                                |
| `ds/no-arbitrary-utilities`    | Arbitrary values/properties/variants poza prymitywami/wyjątkiem; dynamiczne składanie klas poza zamkniętą mapą                                                                     |
| `ds/public-api-only`           | Deep imports prymitywów/vendorów, re-export obejścia, CSS imports poza dozwoloną warstwą; kontrola aliasów i ścieżek względnych                                                    |
| `ds/closed-props`              | Eksport nieograniczonych style props, index signature, niekontrolowany passthrough i rozszerzenie wszystkich HTML props w API DS; uzupełniona testami typów                        |
| `ds/no-component-theme-branch` | `dark:` w mapach klas, odczyt motywu poza providerem i ręczne przełączanie kolorów w komponentach                                                                                  |
| `ds/require-design-record`     | Nowy token/wariant publiczny bez powiązanego rekordu decyzji; sens i wielokrotne użycie ocenia człowiek                                                                            |
| `ds/valid-exception`           | Brak, mismatch, expiry lub osierocony wpis rejestru; nieautoryzowane wyłączenie reguły                                                                                             |

P0 rekomendacja: ESLint jako referencyjny runner lokalnego kontraktu, z testami RuleTester i własnymi regułami AST; `no-restricted-imports`/`no-restricted-syntax` mogą stanowić pierwszą warstwę, ale same nie wykrywają spreadów i aliasów. Brak automatycznego autofix wybierającego wariant designu. [ESLint custom rules](https://eslint.org/docs/latest/extend/custom-rules).

Oxlint może uruchamiać JS plugins przez `jsPlugins`; według odczytanej dokumentacji ta funkcja jest alpha, a własne reguły wymagające type-awareness i custom parsers nie są obsługiwane. Dlatego nie deklarować pełnej zamienności: Oxlint dla pokrytych reguł, ESLint dla pozostałych; żadna bramka nie może zniknąć przy migracji. Przejście całości na Oxlint dopiero po identycznych wynikach zestawu fixtures, wraz z testami CSS i SSR poza runnerem JS. [Oxlint JS plugins](https://oxc.rs/docs/guide/usage/linter/js-plugins.html).

P0 nie musi utrzymywać dwóch ogólnych linterów. Jeśli upstream narzuca Oxlint, ESLint wykonuje wyłącznie brakujące kontrole DS, z rozłącznym zakresem pozostałych reguł. CSS i SSR wymagają osobnych testów kwalifikowanych przy implementacji; sam regex lub ESLint TSX nie stanowi pełnej ochrony renderowania.

### CI jako warunek użycia startera

Szczegółowy zestaw przypadków opisuje [strategia testów](03-testing-and-quality.md). Required check `ui-contract` (proponowana nazwa) obejmuje całe źródła React/TSX/CSS, nie tylko zmienione linie: lint AST, typy publicznego API, skan CSS, rejestr tokenów i wyjątków. Zero naruszeń poza ważnymi, zatwierdzonymi wyjątkami. Brak parsera, nieznany plik lub wyłączona reguła daje błąd, nie ciche pominięcie. Lista plików w zaufanej warstwie i zmiany zakresu lint wymagają review właściciela DS.

To kontrakt inżynierski, nie zabezpieczenie przed dowolnym złośliwym JS. Zakaz nieanalizowalnych konstrukcji, zamknięte props, przegląd dependencies oraz kontrola zmian konfiguracji uzupełniają linter. W P0 wdrożyć go dla małego zestawu realnie używanych prymitywów, zamiast projektować własny język UI.

## Komponenty P0 i warianty

Button: primary/secondary/destructive/link, rozmiar, pending/disabled; Input/Textarea/Label/FieldError; checkbox; alert; badge statusu; confirm dialog; toast jako pomocniczy feedback; tabela i paginator; page header; admin shell i public layout. Warianty muszą odpowiadać intencji użytkownika, nie konkretnej stronie.

Jedna rodzina ikon Lucide; import pojedynczych ikon, aria-hidden dla dekoracji, accessible name na przycisku ikonowym. Licencja i upstream są w [Lucide](https://github.com/lucide-icons/lucide). Toast nie może być jedynym miejscem błędu formularza. Dialog ma nazwę, focus trap, zamknięcie Escape, powrót focus i potwierdzenie akcji destrukcyjnej. Tabela semantyczna; filtr i sort w URL; paginacja serwerowa i czytelny stan bez wyników. W P0 prosta tabela wystarcza, biblioteka zaawansowanych tabel tylko przy konkretnej potrzebie.

P0 obejmuje Tiptap w panelu, z zamkniętym schema: nagłówki, akapity, listy, linki, cytat, code inline, podstawowe formatowanie i obrazy wybierane wyłącznie z DAM. P1: date picker, combobox, uploady użytkowników, filtry zaawansowane i dashboard tylko z realnymi metrykami biznesowymi. Nie wyświetlać fikcyjnych KPI.

## Formularze i stany

Inertia form helpers dla własnego panelu. Backend ma ostatnie słowo o walidacji; UI może dawać szybszy feedback, ale nie posiada osobnej logiki uprawnień. Po submit pending i blokada powtórnego kliknięcia; backend nadal gwarantuje idempotencję. Po 422 focus na pierwszym błędzie/podsumowaniu, pole z aria-describedby, zachowane dane z wyjątkiem haseł i sekretów. Używać właściwego autocomplete i inputmode.

Każdy ekran ma: initial loading (skeleton tylko jeśli pomaga), empty (wyjaśnienie i dozwolona akcja), error (co się stało i retry), success i partial failure dla procesów asynchronicznych. W utracie sesji informacja o ponownym logowaniu; nie obiecywać zapisu, gdy kolejka nadal pracuje. Unsaved changes guard tylko tam, gdzie realnie grozi utrata pracy.

## Responsywność i dostępność

Cel jakości: WCAG 2.2 AA. To cel weryfikacji, nie deklaracja zgodności prawnej. P0: semantyczne nagłówki i landmarki, skip link, label, focus widoczny, klawiatura, kontrast tekstu, obsługa zoom/reflow, komunikaty błędów i statusów. Sprawdzić mobile 360 px, tablet, desktop i zoom 200%/400%; dla tabel dopuścić kontrolowany scroll z czytelnym kontekstem. Preferować cele dotykowe 44×44 px, oceniając też kryteria minimum WCAG. [WCAG 2.2](https://www.w3.org/TR/WCAG22/).

Automat axe na publicznej stronie, loginie i formularzu panelu; ręcznie tab order, dialog, błędy, czytnik ekranu w jednym krytycznym procesie. Nie uznawać wyniku Lighthouse za pełny audyt dostępności.

## Proces Figmy i ponownego użycia

1. Ustalić właściwy plik, wersję, ekrany, warianty, fonty i prawa do assetów; brak projektu oznacza pytania, nie wymyślanie brandu.
2. Zinwentaryzować istniejące tokeny i komponenty przed napisaniem nowego. Agent wyszukuje nazwę, funkcję i podobny wzorzec.
3. Zmapować Figma variables/styles na tokeny. Rozbieżności między makietą a dostępnością przedstawić projektantowi z propozycją korekty.
4. Najpierw użyć istniejącego komponentu. Jeśli nie wystarcza, przed implementacją przedstawić rozszerzenie systemu: brakującą decyzję designową, alternatywy i miejsca ponownego użycia. Dopiero po akceptacji właściciela design systemu implementować wariant lub nowy komponent; nie robić lokalnego obejścia. W PR wskazać, co sprawdzono.
5. Odtworzyć strukturę i zachowanie, uzupełnić brakujące loading/error/empty/mobile; nie kopiować absolutnych pozycji z makiety jako architektury strony.
6. Porównać screenshoty w uzgodnionych viewportach i przejść klawiaturą. Człowiek akceptuje wygląd i ewentualne nowe baseline.

P0 katalog komponentów jako prosta strona demonstracyjna tylko local/staging lub dokumentacja z przykładami. Osobny Storybook opcjonalny, gdy liczba komponentów i review designu uzasadnią utrzymanie kolejnego builda.

## Website Kit i wydajność

P0 public layout SSR, hero, sekcja treści, CTA, kontakt, footer oraz lista i widok artykułu/aktualności — tylko elementy użyte na stronie pilotażowej. P1 FAQ, lista usług, testimonials i powtarzalne sekcje ze zdefiniowanym schematem; żadnego uniwersalnego JSON page buildera.

Budżety robocze: publiczna strona ≤100 KB skompresowanego JS aplikacyjnego, panel ≤250 KB początkowego JS, obrazy w formatach/wymiarach dopasowanych do wyświetlania, wymiary rezerwujące layout. LCP ≤2,5 s, INP ≤200 ms, CLS ≤0,1 jako cele pomiaru terenowego p75 według [Web Vitals](https://web.dev/articles/vitals); w P0 użyć testu laboratoryjnego jako przybliżenia, INP potwierdzić po ruchu. Przekroczenie budżetu wymaga uzasadnienia i review. Nginx cache'uje hashowane assety Vite jako immutable; HTML SSR i odpowiedzi Inertia nie są współdzielone domyślnie. Redis cache'uje tylko bezpieczne odczyty publiczne z invalidacją po publikacji. Prefetch Inertia jest ograniczony do prawdopodobnych, tanich nawigacji GET i krótkiego TTL, nigdy do mutacji, formularzy i dużych list. Analityka nie ładuje się przed rozstrzygnięciem zasad zgód; nie wysyła pól formularza ani parametrów URL z PII.

## Ryzyka

Zbyt szeroki design system opóźnia klienta; szablony bez review stają się generyczne. Kopiowanie shadcn przenosi utrzymanie na zespół. Rozbieżność typów props/DTO i błędy fokusowania mogą zostać niewykryte przez typecheck. Budżet wydajności trzeba przetestować na docelowej stronie i urządzeniu.

## Checklista

- [ ] Komponent używa wyłącznie publicznego API DS; `ui-contract` przechodzi dla TSX/CSS, a SSR ma test HTML/meta.
- [ ] Nowe tokeny/warianty mają decyzję designową, miejsca ponownego użycia i akceptację przed implementacją.
- [ ] Light/dark działają przez pełne mapy tokenów; brak ręcznych wariantów motywu.
- [ ] Wyjątki mają ograniczony zakres, ownera, approval i datę wygaśnięcia.
- [ ] Loading, empty, error, pending i sukces są opisane/testowane.
- [ ] Formularz i dialog działają z klawiaturą i czytnikiem.
- [ ] Publiczny HTML ma treść bez JS i poprawne meta.
- [ ] Viewporty, kontrast i budżety sprawdzone; baseline zaakceptował człowiek.
- [ ] Fonty/ikony/zdjęcia mają potwierdzone prawa użycia.

## Otwarte pytania

Czy jest referencyjna Figma i kto zatwierdza design? Jaka marka, paleta i typografia mają nadać frontendowi editorialny charakter? Jakie przeglądarki i urządzenia wspierać? Kto zatwierdza rozszerzenia i wyjątki design systemu? Czy potrzebna jest kontrolka wyboru motywu i wielojęzyczność? Obsługa light/dark w tokenach jest już wymaganiem. Jaka analityka jest potrzebna i kto zatwierdza jej przetwarzanie danych?
