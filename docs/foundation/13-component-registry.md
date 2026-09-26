# Rejestr komponentów — inwentaryzacja i status realizacji

Rejestr wykonawczy dla [11 — katalogu komponentów](11-component-catalog.md), zgodny z zasadami [05 — design system i frontend](05-design-system-and-frontend.md) (ADR-012/013/019) oraz [.ai/rules/frontend.md](../../.ai/rules/frontend.md). Katalog 11 jest źródłem zakresu i backlogiem. Komponenty oraz kompozycje budujemy we własnym repo; zewnętrzne produkty są tylko wzorcami zachowania i nie są kandydatami do zakupu, importu ani kopiowania kodu. Ten dokument aktualizowany jest podczas realizacji; zastępuje potrzebę osobnego demo do czasu powstania strony `local/staging` (dopuszczalne per 05: „dokumentacja z przykładami”).

Statusy zgodne z 11: `planned`, `implemented`, `verified`, `deprecated`.

Priorytety są wiążące: P0 oznacza obowiązkowy komponent startera, P1 oznacza planowaną wspólną bibliotekę po zamknięciu P0, a P2 oznacza komponent opcjonalny, uruchamiany wyłącznie dla potwierdzonego przypadku produktowego. Pełny podział jest w [11 — kolejność wdrażania](11-component-catalog.md#kolejność-wdrażania).

`implemented` = kod istnieje, ma testy wszystkich zmienionych zachowań oraz przechodzi `npm run test:ui`, typecheck, lint i `ui-contract` dla ustalonego baseline'u albo jawnie wskazanego zakresu plików. Nie wolno wnioskować o wyniku własnych plików z czerwonej kontroli całego repo. **Nie jest `verified`** dopóki nie ma: dema stanów (default/loading/empty/error/disabled/pending/success + long content/brak media), i18n na poziomie ekranu, potwierdzonego light/dark, RWD od 360 px, obsługi klawiatury/focusu i poprawnego SSR bez błędu hydratacji. Żadna pozycja w tym rejestrze nie ma dziś statusu `verified` — wymaga to uruchomionego środowiska (`make up`) i przeglądu wizualnego, którego ta sesja nie wykonała.

Każda partia komponentów otrzymuje osobne zlecenie z maksymalnie 2–5 elementami jednej rodziny, publicznym API, stanami i kryteriami odbioru. Po gotowym diffie wykonawca uruchamia kontrole, następnie `foundation-reviewer` wykonuje jedno read-only review AC i wskazanych plików. Wykonawca weryfikuje findings, poprawia je i ponownie uruchamia kontrole zmienionego zakresu; kolejna runda review jest potrzebna tylko przy nowym ryzyku.

## ADM — panel administracyjny

### ADM-01 — Podstawy UI (P0) — `implemented`, nie `verified`

| Komponent | Plik | Status |
| --- | --- | --- |
| Button | [button.tsx](../../resources/js/design-system/primitives/button.tsx) | implemented (pre-existing) |
| IconButton | [icon-button.tsx](../../resources/js/design-system/primitives/icon-button.tsx) | implemented |
| Link | [link.tsx](../../resources/js/design-system/primitives/link.tsx) | implemented |
| Avatar | [avatar.tsx](../../resources/js/design-system/primitives/avatar.tsx) | implemented |
| Badge | [badge.tsx](../../resources/js/design-system/primitives/badge.tsx) | implemented |
| Icon | [icon.tsx](../../resources/js/design-system/primitives/icon.tsx) | implemented |
| Separator | [separator.tsx](../../resources/js/design-system/primitives/separator.tsx) | implemented |
| Tooltip | [tooltip.tsx](../../resources/js/design-system/primitives/tooltip.tsx) | implemented |
| Spinner | [spinner.tsx](../../resources/js/design-system/primitives/spinner.tsx) | implemented |
| Skeleton | [skeleton.tsx](../../resources/js/design-system/primitives/skeleton.tsx) | implemented |
| Progress | [progress.tsx](../../resources/js/design-system/primitives/progress.tsx) | implemented |
| Alert/Callout | [alert.tsx](../../resources/js/design-system/primitives/alert.tsx) | implemented (tony: neutral/success/danger — brak tokenów warning/info, patrz „Decyzje do zatwierdzenia”) |
| EmptyState | [empty-state.tsx](../../resources/js/design-system/primitives/empty-state.tsx) | implemented |

Import: `import { Button, IconButton, ... } from '@/design-system/primitives'`.

### ADM-02 — Układy i schematy (P0 układ; P1 wizard) — `implemented`, nie `verified`

| Komponent | Plik | Status |
| --- | --- | --- |
| Stack, Grid, Container, Section | [stack.tsx](../../resources/js/design-system/primitives/stack.tsx) i in. | implemented (pre-existing) |
| Fieldset | [fieldset.tsx](../../resources/js/design-system/primitives/fieldset.tsx) | implemented |
| Card | [card.tsx](../../resources/js/design-system/primitives/card.tsx) | implemented (własny wrapper, nie import z `components/ui/card.tsx`) |
| Collapsible | [collapsible.tsx](../../resources/js/design-system/primitives/collapsible.tsx) | implemented (Radix `@radix-ui/react-collapsible`, już zależność) |
| Accordion | [accordion.tsx](../../resources/js/design-system/primitives/accordion.tsx) | implemented (kompozycja `Collapsible`, single/multiple, bez nowej zależności Radix Accordion) |
| Tabs | [tabs.tsx](../../resources/js/design-system/primitives/tabs.tsx) | implemented — decyzja 2026-09-11: zainstalowano `@radix-ui/react-tabs` (zgoda człowieka) |
| SplitLayout | [split-layout.tsx](../../resources/js/design-system/primitives/split-layout.tsx) | implemented |
| Wizard/Stepper | — | `planned` (P1), brak blokera zależności |

### ADM-03 — Pola tekstowe (P0 podstawy; P1 specjalizacje) — częściowo `implemented`

| Komponent | Plik | Status |
| --- | --- | --- |
| TextField (label/help/error, text/email/url/tel/search) | [text-field.tsx](../../resources/js/design-system/primitives/text-field.tsx) | implemented |
| TextareaField | [textarea-field.tsx](../../resources/js/design-system/primitives/textarea-field.tsx) | implemented |
| PasswordField | [password-field.tsx](../../resources/js/design-system/primitives/password-field.tsx) | implemented |
| NumberField | [number-field.tsx](../../resources/js/design-system/primitives/number-field.tsx) | implemented 2026-09-27 (zgoda właściciela DS) — natywny `<input type="number">`, anatomia `TextField` (wewnętrzny `native-input-field.tsx`, poza publicznym API); props: `id?`, `name`, `label`, `description?`, `error?`, `required?`, `disabled?`, `placeholder?`, `value: string` (`''` = brak), `onChange(value: string)`, `onBlur?`, `min?`, `max?`, `step?: number \| 'any'`, `ref?`; `inputMode` automatycznie `numeric` dla całkowitego `step`, inaczej `decimal`. Bez nowych zależności. Test: [number-date-field.accessibility.test.tsx](../../resources/js/design-system/primitives/number-date-field.accessibility.test.tsx) |
| DateField | [date-field.tsx](../../resources/js/design-system/primitives/date-field.tsx) | implemented 2026-09-27 (zgoda właściciela DS) — natywny `<input type="date">` (picker i format wyświetlania przeglądarki), ta sama anatomia/ARIA/tokeny; props jak `NumberField` bez `placeholder`/`step`, `value: 'YYYY-MM-DD' \| ''`, `min?`/`max?` jako `YYYY-MM-DD`. Bez biblioteki date pickera. Test jw. |
| PhoneInput, MoneyInput | — | `planned` (P1); maskowanie wymaga wyboru biblioteki (brak w `package.json`) — decyzja przed instalacją |
| Maski, prefiks/sufiks, kopiowanie wartości | — | `planned` (P1) |

Kontrakt: pola są samodzielnymi „field” (label+control+description+error+`aria-describedby`), sterowane (`value`/`onChange`), z `ref` do fokusowania po 422 przez przyszły `ErrorSummary`/`FormSection` (ADM-08, patrz niżej — jeszcze nie zbudowany).

### ADM-04 — Wybór wartości (P0 podstawy; P1 reszta) — częściowo `implemented`

| Komponent | Status | Uwaga |
| --- | --- | --- |
| CheckboxField | `implemented` — [checkbox-field.tsx](../../resources/js/design-system/primitives/checkbox-field.tsx) | |
| SelectField | `implemented` — [select-field.tsx](../../resources/js/design-system/primitives/select-field.tsx) | Własny wrapper Radix `@radix-ui/react-select` (już zależność), nie import z `components/ui/select.tsx` |
| RadioGroupField | `implemented` — [radio-group-field.tsx](../../resources/js/design-system/primitives/radio-group-field.tsx) | Decyzja 2026-09-11: zainstalowano `@radix-ui/react-radio-group` (zgoda człowieka) |
| SwitchField | `implemented` — [switch-field.tsx](../../resources/js/design-system/primitives/switch-field.tsx) | Decyzja 2026-09-11: zainstalowano `@radix-ui/react-switch` (zgoda człowieka) |
| CheckboxGroup | `planned` (P0 luka) | Nie zrealizowano w tej partii; brak blokera zależności |
| ToggleGroup | `planned` (P1) | `components/ui/toggle-group.tsx` istnieje jako baza (już zależność), brak wrappera DS |
| AsyncCombobox, MultiSelect, TagsInput | `planned` (P1) | Własny komponent z wyszukiwaniem, klawiaturą, debounce, stanami async i chips; nie zastępować go filtrowanym Selectem |

### ADM-05 — Data i zakresy — `planned` (P1)

DatePicker/TimePicker/DateTimePicker/DateRangePicker/Slider budujemy jako własne komponenty DS. Wzorce zewnętrzne mogą pomóc określić zachowania, ale nie stanowią zależności ani źródła kodu. Przed implementacją trzeba ustalić kontrakt dat, stref czasowych, lokalizacji, ograniczeń i obsługi klawiatury.

### ADM-06 — Treść i media (P0 edytor i DAM) — edytor `implemented` (nie `verified`), DAM **zablokowany**

| Komponent | Plik | Status |
| --- | --- | --- |
| RichTextField | [rich-text-field.tsx](../../resources/js/design-system/primitives/rich-text-field.tsx) (+ wewnętrzny, ładowany leniwie [rich-text-editor.tsx](../../resources/js/design-system/primitives/rich-text-editor.tsx)) | implemented — kontrolowane pole Tiptap v3 o zamkniętych props (`id`, `name`, `label`, `hint?`, `error?`, `required?`, `disabled?`, `value: RichTextDocument`, `onChange`, `labels: RichTextFieldLabels` z i18n `admin.richText.*`). Jawna allowlista schematu zgodna z backendem: paragraph, heading (2–4), bold, italic, strike, code, bulletList, orderedList, listItem, blockquote, hardBreak, horizontalRule, link (tylko http/https/mailto, `openOnClick: false`, `rel="noopener noreferrer nofollow"`); codeBlock i underline wyłączone, brak obrazów. Toolbar `role="toolbar"` z nazwą, jednym tab stopem (strzałki/Home/End), `aria-pressed` dla przełączników i `aria-keyshortcuts` dla skrótów Tiptap. Link przez `FormDialog` + `TextField` (bez `window.prompt`). Edytor ma `role="textbox"`, `aria-labelledby`, `aria-describedby` (hint/błąd), `aria-invalid`, `aria-required`. SSR i pierwszy render klienta pokazują `Skeleton`; chunk Tiptap (~127 kB gzip) ładuje się dopiero po montażu, więc nie trafia do bundla SSR ani do początkowego JS innych ekranów. Test: [rich-text-field.accessibility.test.tsx](../../resources/js/design-system/primitives/rich-text-field.accessibility.test.tsx) |

- Dokument trzymany jako Tiptap JSON (`RichTextDocument`); walidacja schematu i sanitizacja pozostają po stronie serwera (tiptap-php). Rozszerzenie `TrailingNode` Tiptapa dopisuje pusty akapit po końcowym bloku (nagłówek, cytat, lista) — backend może go przycinać.
- Obrazy w treści: dopiero po DAM (wybór wyłącznie z DAM per [05](05-design-system-and-frontend.md#komponenty-p0-i-warianty)).
- MediaPicker: wymaga backendu DAM (upload, storage, warianty) — w P0 uploady są **wyłączone** per [.claude/rules/security.md](../../.claude/rules/security.md). Nie tworzono pozorowanej integracji.
- MarkdownEditor, CodeEditor, ColorPicker: `planned` (P1/P2), każdy wymaga osobnej decyzji o bibliotece.

### ADM-07 — Dane złożone — `planned` (P1; Builder P2)

Repeater, KeyValueEditor, pola warunkowe — brak blokera zależności, nie realizowano w tej partii (priorytet niższy niż ADM-08/09).

### ADM-08 — Formularz jako całość (P0/P1) — P0 `implemented`, nie `verified`

| Komponent | Plik | Status |
| --- | --- | --- |
| FormSection | [form-section.tsx](../../resources/js/design-system/primitives/form-section.tsx) | implemented |
| FormActions | [form-actions.tsx](../../resources/js/design-system/primitives/form-actions.tsx) | implemented |
| ResourceForm | [resource-form.tsx](../../resources/js/design-system/primitives/resource-form.tsx) | implemented — jedna definicja sekcji/pól dla create/edit (`text`, `textarea`, `number` — `min`/`max`/`step`, `date` — `min`/`max`; obie o wartości string, `select`, `switch`, `checkbox`, `richText` — wartość `RichTextDocument`, pole wymaga `labels: RichTextFieldLabels`; `label`, `hint`, `required`, `disabled`), typowana nazwami kluczy wartości; błędy przy polach + `ErrorSummary` z fokusem na pierwszym błędnym polu; blokada podwójnego submitu (pending + blokada w tym samym ticku), pola zablokowane i `aria-busy` w trakcie zapisu, opcjonalny komunikat `saved`. Stan i żądanie prowadzi strona (Inertia `useForm`: `data`/`errors`/`processing`/`recentlySuccessful`/`submit`). Test: [resource-form.accessibility.test.tsx](../../resources/js/design-system/primitives/resource-form.accessibility.test.tsx). Brak jeszcze realnego ekranu create/edit (czeka na zasób z mutacjami i polityką). |
| ErrorSummary | [error-summary.tsx](../../resources/js/design-system/primitives/error-summary.tsx) | implemented — przenosi fokus na **pierwsze błędne pole** (nie na siebie) przy przejściu z 0→N błędów, bez ponownej kradzieży fokusu przy re-renderach z tymi samymi błędami; linki podsumowania nadal przenoszą fokus na dowolne pole po `id` |

Poprawka 2026-09-11 (review P0): pola formularza (`TextField`, `TextareaField`, `PasswordField`, `CheckboxField`, `SelectField`, `SwitchField`, `RadioGroupField`) przyjmują teraz opcjonalny, stabilny prop `id`, zamiast opierać publiczne powiązanie z `ErrorSummary` o nieznany rodzicowi wewnętrzny `useId()`. `ContactSection` (WEB-05) korzysta z tego mechanizmu i integruje `ErrorSummary` bezpośrednio.

Formularz wieloetapowy (P1) pozostaje `planned`.

### ADM-09 — Tabela (P0 podstawy; P1 formatery) — częściowo `implemented`

| Komponent | Plik | Status |
| --- | --- | --- |
| DataTable (typowane kolumny, sort, loading/error/empty) | [data-table.tsx](../../resources/js/design-system/primitives/data-table.tsx) | implemented |
| Paginator | [paginator.tsx](../../resources/js/design-system/primitives/paginator.tsx) | implemented |
| SearchInput | [search-input.tsx](../../resources/js/design-system/primitives/search-input.tsx) | implemented — debounce lokalny, `onChange` woła stronę, która prowadzi zapytanie Inertia |
| FilterBar | [filter-bar.tsx](../../resources/js/design-system/primitives/filter-bar.tsx) | implemented — czysto układowa kompozycja, filtry przekazywane jako `children` |
| ResourceTable | [resource-table.tsx](../../resources/js/design-system/primitives/resource-table.tsx) | implemented — kompozycja `PageHeader?`/`SearchInput`/`FilterBar`+`SelectField`/`DataTable`/`Paginator`/`EmptyState`/`RetryPanel`/`ActionMenu`; stan w URL przez Inertia `router.get` (`preserveState`, `preserveScroll`; wyszukiwanie z debounce 300 ms robi `replace`, sort/filtr/strona dodają wpis historii, więc Wstecz/Dalej odtwarza stan z propsów); każda zmiana poza stroną resetuje `page` do 1; rozróżnia pustą listę i brak wyników; `aria-busy` w trakcie wizyty. Test: [resource-table.accessibility.test.tsx](../../resources/js/design-system/primitives/resource-table.accessibility.test.tsx) |
| FilterChips | — | `planned` (P1) |
| Formatery komórek (waluta, data, status, ikona, obraz, kolor) | — | `planned` (P1) |

**Kontrakt `DataTable`/`Paginator`**: komponenty są czysto prezentacyjne — `onSortChange`/`onPageChange` zwracają zamiar, a stan w URL i zapytanie serwerowe prowadzi wywołująca strona (Inertia `router.get`). Zgodne z wymaganiem „stan w URL” z [05](05-design-system-and-frontend.md#formularze-i-stany) bez wiązania prymitywu z routingiem.

**Pierwszy rzeczywisty przepływ (2026-09-11)**: [resources/js/pages/admin/users/index.tsx](../../resources/js/pages/admin/users/index.tsx) łączy `DataTable`, `SearchInput`, `FilterBar`, `Paginator` i `ActionMenu` w realny ekran listy użytkowników (`App\Http\Controllers\Admin\UserIndexController`, trasa `admin.users.index`), z wyszukiwaniem/filtrem/sortem/stroną w URL (Inertia `router.get` z `preserveState`), stanami loading/empty/error+retry. Akcje wiersza są celowo minimalne i bez fikcyjnych mutacji (kopiowanie e-maila, `mailto:`), bo backend nie ma jeszcze warstwy ról/uprawnień do bezpiecznych akcji edycji/usuwania.

**Kontrakt listy (2026-09-27)**: backend opisuje listę jawnie przez `App\Support\Listing\ListQuery` (kolumny wyszukiwania, allowlista sortowania z domyślnym sortem i kierunkiem, allowlista filtrów `nazwa → wartości + callback`, `perPage`). Ta sama definicja daje reguły FormRequest (`rules()`), zapytanie Eloquent (`apply()`/`paginate()`: `LIKE ... escape '!'` z escapowaniem `%`/`_`, stabilny sort z rozstrzygnięciem remisu po kluczu) i jednolity payload `{ items, pagination: {page,totalPages,total,perPage}, filters: {search, sort, direction, ...filtry} }` (`payload()`), zgodny z typami `ResourceListPagination`/`ResourceListFilters` w `ResourceTable`. Wzorzec: [ListAdminUsersRequest](../../app/Http/Requests/ListAdminUsersRequest.php) + [UserIndexController](../../app/Http/Controllers/Admin/UserIndexController.php) + [admin/users/index.tsx](../../resources/js/pages/admin/users/index.tsx). Payload nie jest jeszcze generowany z Laravel Data (typy TS są ręczne — patrz `.ai/rules/js.md`).

### ADM-10 — Zaawansowana tabela — `planned` (P1 widoki/akcje; P2 ciężkie warianty)

ColumnPicker, zapisane widoki, wielosort, grupowanie, agregaty, przypinanie/reorder, rozwijane wiersze i edycja komórek są własnym zakresem komponentu. Wirtualizacja dużych zbiorów jest **P2** i wymaga mierzalnej potrzeby wydajnościowej oraz osobnego projektu technicznego.

### ADM-11 — Widok rekordu / infolist — P0 podstawowy widok `implemented`, nie `verified`

| Komponent | Plik | Status |
| --- | --- | --- |
| DescriptionList | [description-list.tsx](../../resources/js/design-system/primitives/description-list.tsx) | implemented — placeholder dla pustej wartości |
| RecordDetails | [record-details.tsx](../../resources/js/design-system/primitives/record-details.tsx) | implemented — kompozycja `Card` + `DescriptionList` + slot na akcje |

TextEntry, IconEntry, ImageEntry, ColorEntry, CodeEntry, KeyValueEntry, RepeatableEntry (typowane warianty wartości) pozostają `planned` (P1) — `DescriptionList` dziś przyjmuje dowolny `ReactNode` jako wartość.

### ADM-12 — Akcje (P0 podstawy; P1 rozszerzenia) — częściowo `implemented`

| Komponent | Status | Uwaga |
| --- | --- | --- |
| ConfirmDialog | `implemented` — [confirm-dialog.tsx](../../resources/js/design-system/primitives/confirm-dialog.tsx) | focus trap/Escape/return focus przez Radix Dialog; wymaga potwierdzenia (`confirmLabel`) na akcję destrukcyjną |
| ActionMenu | `implemented` — [action-menu.tsx](../../resources/js/design-system/primitives/action-menu.tsx) | Własny wrapper Radix `@radix-ui/react-dropdown-menu` (już zależność), trigger to `IconButton` |
| FormDialog | `implemented` — [form-dialog.tsx](../../resources/js/design-system/primitives/form-dialog.tsx) | Kompozycja szkieletu `ConfirmDialog` + `<form>`; blokuje zamknięcie w trakcie `isPending`. Poprawka 2026-09-11: `<form onSubmit>` też odrzuca wywołanie `onSubmit` gdy `isPending`, więc Enter w polu i programowy event `submit` nie wysyłają formularza drugi raz (błąd wykryty w review). |
| Drawer/SlideOver | `planned` (P1) | `components/ui/sheet.tsx` istnieje jako baza (już zależność), brak wrappera DS |

### ADM-13 — Operacje na zbiorach — `planned` (P1, po zamówieniu importu/eksportu), **wymaga backendu**

BulkActions, zaznaczenie strony/całego wyniku, ImportWizard, ExportDialog, mapowanie kolumn, JobProgress — UI niezrealizowane. Wymaga: kontraktu backendowego dla kolejki/joba importu-eksportu (idempotencja, raport błędów, pobranie wyniku) oraz decyzji produktowej o zakresie — przed implementacją.

### ADM-14 — Zasoby i relacje — `planned` (P0 wzorzec; P1 generator/relacje)

ResourceList/Create/Edit/Show, RelationManager, generator CRUD — to wzorzec architektoniczny (thin controller + FormRequest + Action + Resource, per [.ai/rules/app.md](../../.ai/rules/app.md)), nie pojedynczy komponent do zbudowania w izolacji. Ustalić przy pierwszym realnym zasobie domenowym, korzystając z już gotowych ADM-03/08/09/12.

Pierwszy przykład ResourceList (2026-09-11): [UserIndexController](../../app/Http/Controllers/Admin/UserIndexController.php) + [ListAdminUsersRequest](../../app/Http/Requests/ListAdminUsersRequest.php) + [admin/users/index.tsx](../../resources/js/pages/admin/users/index.tsx) — tylko odczyt (bez Create/Edit/Show), bo rozbudowa o mutacje wymaga modelu ról/uprawnień, którego jeszcze nie ma (`User::isAdmin()` to tymczasowe allow-all, patrz `@todo` w modelu). Nie generalizować tego jednego przypadku do generatora CRUD bez kolejnego zasobu.

### ADM-15 — Nawigacja panelu (P0 shell; P1 search; P2 organizacje) — `implemented` (pre-existing, poza tą sesją), **wymaga porządku ADR-019**

Uwaga: dotyczy panelu administracyjnego — publiczna nawigacja (`PublicHeader`, `MobileNav`) jest opisana w WEB-01, gdzie w tej sesji dodano typowane elementy nawigacji i realny hamburger na wąskich ekranach.

AdminShell/Sidebar/Topbar/Breadcrumbs/UserMenu już działają jako `app-shell.tsx`, `app-sidebar.tsx`, `app-header.tsx`, `breadcrumbs.tsx`, `user-menu-content.tsx` w `resources/js/components/` — **nie są jeszcze przeniesione do `design-system/primitives`** i część (`user-menu-content.tsx`) ma dziś aktywne naruszenia `ui-contract` (ADR-019) z wcześniejszej, niedokończonej pracy w tym repo — poza zakresem tej sesji, do zamknięcia osobno. GlobalSearch/CommandPalette = `planned` (P1). Przełącznik organizacji = `planned` (P2), wymaga modelu dostępu multi-tenant (decyzja produktowa + backend).

### ADM-16 — Powiadomienia (P0 feedback; P1 centrum; P2 realtime) — częściowo `implemented`

Toast działa (`sonner` + `useFlashToast`), ale plik `components/ui/sonner.tsx` ma dziś surowe hex/`dark:` niezgodne z ADR-019 — istniejący dług, poza zakresem tej sesji (cudzy niedokończony diff). InlineFeedback: decyzja 2026-09-11 — nie tworzymy osobnego komponentu; gotowy [alert.tsx](../../resources/js/design-system/primitives/alert.tsx) pokrywa ten przypadek (unikanie abstrakcji na zapas). NotificationCenter = `planned` (P1, wymaga backendu). Realtime = `planned` (P2) — wymaga decyzji o transporcie (np. Pusher/Laravel Reverb, nowa zależność) i kosztu.

### ADM-17 — Widgety — `planned` (P1 metryki; P2 personalizacja), **zablokowane brakiem biblioteki wykresów**

StatCard/ChartCard/TableWidget — żadna biblioteka wykresów (np. `recharts`) nie jest zainstalowana; decyzja przed implementacją `ChartCard`. `StatCard`/`TableWidget` nie mają tego blokera, ale wymagają realnych metryk biznesowych (nie fikcyjnych KPI, per [05](05-design-system-and-frontend.md#komponenty-p0-i-warianty)) — priorytet po ADM-09/11. Konfigurowalna siatka dashboardu = `planned` (P2), decyzja produktowa.

### ADM-18 — Konto i dostęp (auth P0 bieżący plan; role dynamiczne P1; tenancy P2) — `implemented` (pre-existing auth)

Logowanie, rejestracja, 2FA, passkeys, profil, bezpieczeństwo już działają (Fortify + istniejące strony/komponenty auth) — poza zakresem tej sesji. Macierz uprawnień = `planned` (P1), wymaga modelu ról. Multi-tenancy = `planned` (P2) — jawnie wymaga osobnej decyzji backendowej per [.claude/rules/architecture.md](../../.claude/rules/architecture.md).

## APP — funkcje aplikacyjne

| ID | Zestaw | Status | Uwaga |
| --- | --- | --- | --- |
| APP-01 | Onboarding | `planned` (P1) | Bez blokera zależności |
| APP-02 | Aktywność i współpraca | `planned` (P1 historia — wymaga backendu audytu; P2 współpraca — wymaga backendu + polityki moderacji) | |
| APP-03 | Kalendarz i rezerwacje | `planned` (P2) | Własny Calendar/Agenda/TimeSlotPicker wymaga modelu domenowego rezerwacji i reguł konfliktu po stronie backendu |
| APP-04 | Organizacja pracy | `planned` (P1 drzewo/lista; P2 Kanban/Gantt) | Własne TreeView, SortableList i Kanban; przed DnD ustalić kontrakt kolejności, konflikty i dostępność klawiaturą |
| APP-05 | Pliki (DAM) | `planned`, **zablokowane** | Uploady wyłączone w P0 per [.claude/rules/security.md](../../.claude/rules/security.md); wymaga polityki (limity, weryfikacja treści, kwarantanna, skan) przed jakimkolwiek UI |
| APP-06 | Dane lokalizacyjne | `planned` (P2) | Własne AddressField/MapPicker; źródło map lub geokodowania wymaga osobnej decyzji produktowej |
| APP-07 | Dokumenty i raporty | `planned` (P1/P2) | Wymaga kontraktu backendowego eksportu |
| APP-08 | Ustawienia i integracje | `planned` (P1) | SettingsSection/PreferenceSwitch bez blokera; CredentialField/ConnectionStatus wymagają backendu bezpiecznego przechowywania sekretów przed realnym podłączeniem — UI może być tylko maskujące |
| APP-09 | Stany procesu | P0 zestaw `implemented`, nie `verified` | [offline-banner.tsx](../../resources/js/design-system/primitives/offline-banner.tsx), [retry-panel.tsx](../../resources/js/design-system/primitives/retry-panel.tsx), [conflict-dialog.tsx](../../resources/js/design-system/primitives/conflict-dialog.tsx), [session-expired.tsx](../../resources/js/design-system/primitives/session-expired.tsx), [permission-denied.tsx](../../resources/js/design-system/primitives/permission-denied.tsx), [not-found.tsx](../../resources/js/design-system/primitives/not-found.tsx), [maintenance.tsx](../../resources/js/design-system/primitives/maintenance.tsx) — zbudowane z gotowych `Alert`/`EmptyState`/`Button`/Radix Dialog, bez nowej zależności. AsyncJobStatus pozostaje `planned` (P1, wymaga kontraktu joba). |
| APP-10 | Rozliczenia i handel | `planned` (P2) | Jawnie: UI nie oznacza wdrożenia płatności; wymaga wyboru dostawcy (Stripe/Paddle/inny), kosztu i backendu billing |
| APP-11 | Wyszukiwanie i pomoc | `planned` (P1/P2) | Własne CommandPalette/SearchResults; silnik wyszukiwania to oddzielna decyzja backendowa, nie komponent UI |

## WEB — strona publiczna/portal

| ID | Zestaw | Status | Uwaga |
| --- | --- | --- | --- |
| WEB-01 | Nawigacja | częściowo `implemented` | `PublicHeader` rozbudowany 2026-09-11 o typowany `navItems` i integrację z [mobile-nav.tsx](../../resources/js/design-system/primitives/mobile-nav.tsx) (widoczny poniżej `md`, tytuł/Escape/focus trap/powrót fokusu przez Radix Dialog, cele dotykowe ≥44×44 px); [footer.tsx](../../resources/js/design-system/primitives/footer.tsx) używany w `welcome.tsx`. MegaMenu/AnnouncementBar pozostają `planned` (P1) |
| WEB-02 | Landing/oferta | P0 zestaw `implemented`, nie `verified` | [hero.tsx](../../resources/js/design-system/primitives/hero.tsx), [feature-grid.tsx](../../resources/js/design-system/primitives/feature-grid.tsx), [cta.tsx](../../resources/js/design-system/primitives/cta.tsx) — zbudowane z gotowych `Stack`/`Section`/`Grid`/`Heading`/`Text`/`Button`, bez fikcyjnych danych biznesowych. Od 2026-09-11 realnie użyte (nie tylko zbudowane) w [resources/js/pages/welcome.tsx](../../resources/js/pages/welcome.tsx) razem z `Footer` i rozbudowanym `PublicHeader`; treści `landing.*` niezmienione. |
| WEB-03 | Wiarygodność | `planned` (P1) | |
| WEB-04 | Treści (blog) | `RichTextContent` `implemented` (nie `verified`); reszta `planned` | [rich-text-content.tsx](../../resources/js/design-system/primitives/rich-text-content.tsx) renderuje zaufany HTML zsanityzowany na serwerze (allowlista tiptap-php) z typografią treści przez tokeny (wspólna z edytorem, [rich-text-typography.ts](../../resources/js/design-system/primitives/rich-text-typography.ts)); jedyne miejsce `dangerouslySetInnerHTML` w DS — kontrakt: nigdy HTML z klienta ani niesanityzowany. Test: [rich-text-content.accessibility.test.tsx](../../resources/js/design-system/primitives/rich-text-content.accessibility.test.tsx). Model treści (Post/Article) i ekrany nadal wymagają backendu. |
| WEB-SEO | Meta i SEO | `Seo` `implemented` (nie `verified`) | [seo.tsx](../../resources/js/design-system/primitives/seo.tsx) — zamknięte props (`title`, `description`, `canonical`, `alternates` hreflang + `x-default`, `robots` jako union, `image`, `type` `website`/`article`, `publishedAt`, `jsonLd`); renderuje przez Inertia `<Head>` z `head-key` (bez duplikatów): title (sufiks z `createInertiaApp({ title })`), description, canonical, hreflang, Open Graph, `twitter:card`, robots i JSON-LD serializowany przez `serializeJsonLd` (escapowanie `<`, `>`, `&`, U+2028/2029). Domyślne wartości ze współdzielonego propu `seo` (`SeoDefaultsData`); strony `noindex` nie dostają canonical ani hreflang. Użyte w `welcome.tsx`, `pages/show.tsx`, `errors/show.tsx`. Test: [seo.test.tsx](../../resources/js/design-system/primitives/seo.test.tsx), SSR: `npm run test:ssr`. |
| WEB-05 | Usługi i kontakt | `ContactSection` `implemented` (prezentacyjny), **brak kontraktu backendowego** | [contact-section.tsx](../../resources/js/design-system/primitives/contact-section.tsx) renderuje pola i stany (loading/error/success), integruje `ErrorSummary` (fokus na pierwszym błędnym polu) i blokuje ponowne `onSubmit` w trakcie `isPending` (poprawka 2026-09-11, także dla Enter/programowego `submit`). `onSubmit` jest w pełni po stronie wywołującego — nie istnieje jeszcze endpoint mail/lead. Strona integrująca komponent musi poczekać na ten kontrakt zanim formularz zacznie realnie wysyłać zgłoszenia. |
| WEB-06 | Media | `planned` (P1) | |
| WEB-07 | Konwersja i komunikaty | `planned` (P1) | Wymaga ustalonego modelu zgód/analityki — otwarte pytanie w [05](05-design-system-and-frontend.md#otwarte-pytania) |
| WEB-08 | Katalog i handel | `planned` (P2) | Wymaga backendu e-commerce i dostawcy płatności |
| WEB-09 | Portal użytkownika | `planned` (P1/P2) | Zazębia się z ADM-18/ustawieniami; wymaga decyzji o zakresie portalu |
| WEB-10 | Całe szablony stron | `planned` | Zależy od WEB-01/02/04/05 — realizować po nich |

## Decyzje wymagające zatwierdzenia (nie podjęto ich w tej sesji)

1. **Silniki techniczne i integracje** — komponenty budujemy własne. Ewentualne zależności techniczne dla dat/range, edycji treści, DnD, wykresów, wirtualizacji, realtime lub map wymagają osobnego wyboru, wersji, licencji i testu; nie są zakupem gotowego komponentu ani częścią jego publicznego API.
2. **Tokeny `status-warning`/`status-info`** — brak w `resources/css/app.css`; potrzebne dla pełnego `Alert`/`Badge`, wymaga decyzji designowej per [05](05-design-system-and-frontend.md#rozszerzenie-i-wyjątek).
3. **Uploady/DAM (ADM-06, APP-05)** — pozostają wyłączone w P0 zgodnie z [.claude/rules/security.md](../../.claude/rules/security.md); wymagają jawnej polityki przed jakimkolwiek kodem.
4. **Migracja legacy komponentów** (`text-link.tsx`, `input-error.tsx`, `components/ui/sonner.tsx`) do zamkniętego API ADR-019 — istniejący dług, nie generowany w tej sesji, wymaga osobnego zadania i review.
5. **Kontrakt backendowy formularza kontaktowego (WEB-05)** — nie istnieje endpoint mail/lead; `ContactSection` jest gotowy jako komponent prezentacyjny, ale nie jest podłączony do żadnej trasy.
6. **Model treści bloga (WEB-04)** — nie zrealizowano w tej partii; wymaga `app/Models/Post` lub odpowiednika przed jakimkolwiek UI.

## Decyzje podjęte w tej sesji (2026-09-11)

Za zgodą człowieka zainstalowano trzy pakiety Radix wymagane dla P0 ADM-02/ADM-04: `@radix-ui/react-tabs`, `@radix-ui/react-radio-group`, `@radix-ui/react-switch` (patrz `package.json`). Wybrano ten wariant zamiast budowy Tabs/RadioGroup/Switch od zera, dla spójności z pozostałymi prymitywami opartymi już na Radix (Select, Dialog, Dropdown, Checkbox, Collapsible) i niższego ryzyka a11y.

## Backend wymagany przed pełnym działaniem (UI może istnieć wcześniej jako kontrakt)

APP-01–02 (audyt/aktywność), APP-03 (rezerwacje), APP-05 (DAM), APP-07 (eksport), APP-08 (sekrety integracji), APP-10 (billing), APP-13/ADM-13 (import/eksport), WEB-04 (treści), WEB-05 (kontakt), WEB-08 (e-commerce), WEB-09 (portal), ADM-10 P2 (wirtualizacja — techniczne, nie backend), ADM-17 (realne metryki), ADM-18 P1/P2 (role dynamiczne, tenancy).
