# 18 — Roadmapa operacji panelu (inspiracja: Backpack for Laravel)

Status: **propozycja do akceptacji** (2026-11-26). Po akceptacji to jedyna lista zakresu dla tej fali; zmiany zakresu wpisujemy tutaj, zanim powstanie kod.

## Cel

Dodać do panelu brakujące, powtarzalne operacje znane z Backpacka (Clone, Reorder, Fetch/InlineCreate, pola warunkowe, eksport), zachowując architekturę startera: **jawny kod + generator**, bez runtime `CrudPanel`. Kod Backpacka służy wyłącznie jako referencja zachowań; nie kopiujemy go (core MIT, PRO zamknięty — nie dotykamy).

Poza zakresem: runtime CrudPanel, Bootstrap/Basset, widgety Backpacka, PermissionManager (role stałe — decyzja świadoma), Revise (pokrywa audit), drzewo nested-set (patrz krok 7, tylko na potrzebę).

## Zasady realizacji

- **1 krok = 1 gałąź = 1 PR** do `develop`. Gałąź `feat/panel-<slug>` (lub `refactor/…`, `docs/…`), tytuł PR i commity w Conventional Commits (`type(scope): description`, ≤72 znaki, po angielsku).
- Kolejność jest wiążąca w obrębie fazy; fazy B–E są niezależne od siebie po zakończeniu fazy A.
- Wzorzec z dokumentu 01: **najpierw jawnie na jednym module, potem generator**.
- Rozszerzenie API DS (nowy prymityw/prop) = osobny krok „propozycja” przed implementacją (ADR-019).
- Kroki oznaczone **HIGH-RISK** zmieniają policies/uprawnienia lub migracje — akceptacja tego planu nie zastępuje akceptacji zakresu w danym PR.
- Agent przygotowuje gałąź, commit i PR; merge i ocena należą do człowieka.

### Definition of Done (każdy PR)

- [ ] AC kroku spełnione i opisane w szablonie PR (zmiana, weryfikacja, ryzyko).
- [ ] `make check`, `make test` zielone; `make test-pgsql` przy zmianach zapytań/migracji.
- [ ] `make generator-smoke` przy zmianach generatora lub stubów.
- [ ] Testy: feature (w tym odmowa 403 dla roli bez uprawnienia), vitest + a11y dla nowych/zmienionych prymitywów, E2E gdy zmienia się przepływ UI.
- [ ] i18n PL/EN/DE, brak nowych wyjątków `ui-contract`.
- [ ] Audit dla operacji zmieniających dane.
- [ ] Docs: instrukcja panelu (`docs/panel/README.md`) i rejestr komponentów (13) aktualne, gdy dotyczy.

---

## Faza 0 — plan

- [ ] **0. `docs/panel-operations-roadmap`** — ten dokument + link w master planie. AC: plan zaakceptowany w PR.

## Faza A — Clone (wzorzec operacji)

- [ ] **1. `feat/panel-faq-clone`** — Clone na FAQ, napisany jawnie. **HIGH-RISK (policy)**
  - Action `Faqs/DuplicateFaq` (`replicate()`, `published=false`, pozycja na końcu), trasa `POST admin/faqs/{faq}/duplicate`, ability `duplicate` w `FaqPolicy` (admin + editor), akcja wiersza w `ResourceTable` i przycisk w edycji, redirect do edycji kopii, toast, wpis audytu.
  - AC: kopia ma nowe ID, jest nieopublikowana, oryginał bez zmian; podgląd (viewer) dostaje 403; audit zawiera źródło kopii.
- [ ] **2. `feat/generator-clone-operation`** — flaga `--operations=clone` w `app:make-resource`.
  - Parsowanie i walidacja listy operacji w `ResourceBlueprint` (nieznana operacja = błąd), stuby: action, trasa, ability w policy, akcja UI, i18n, test feature. Relacje M:N kopiowane, `hasMany` nie; slug (przy `--public`) z sufiksem `-copy-N`, rekord jako szkic.
  - AC: smoke generuje moduł z clone bez ręcznych poprawek; `--dry-run` pokazuje nowe pliki.
- [ ] **3. `feat/panel-content-clone`** — Clone stron i artykułów. **HIGH-RISK (policy)**
  - Kopia tłumaczeń z unikalnymi slugami per locale, status szkic, bez `published_at` i bez kopiowania przekierowań slugów; artykuł: tagi i okładka zachowane.
  - AC: brak kolizji slugów w żadnym locale; kopia niewidoczna publicznie do publikacji.

## Faza B — Reorder

- [ ] **4. `refactor/panel-reorder-records`** — wydzielenie wspólnej logiki z `ReorderMenuItems` i `ReorderHomeSections` (zakres grupy, transakcja, blokada, walidacja kompletności listy ID) do jednej Action/serwisu. Bez zmiany zachowania.
  - AC: istniejące testy menu i sekcji przechodzą bez modyfikacji asercji.
- [ ] **5. `feat/panel-faq-reorder`** — `OrderableList` dla FAQ w zakresie locale zamiast ręcznego pola „Kolejność”. **HIGH-RISK (migracja danych)**
  - Normalizacja `position` (dziś nullable) migracją danych; pole liczby usunięte z formularza; nowe rekordy na końcu; import CSV nadal honoruje `position`.
  - AC: kolejność publiczna = kolejność w panelu; równoległa zmiana daje konflikt, nie cichą utratę.
- [ ] **6. `feat/generator-reorder-operation`** — `--operations=reorder` (kolumna `position` z indeksem, opcjonalny zakres `--reorder-scope=field`).
  - AC: smoke z reorder; lista domyślnie sortowana po pozycji.
- [ ] **7. (opcjonalny, P2) `feat/panel-tree-reorder`** — reorder drzewa >2 poziomów. Tylko przy potwierdzonej potrzebie klienta; do tego czasu nie implementujemy.

## Faza C — Fetch + InlineCreate (relacje na dużych tabelach)

- [ ] **8. `docs/ds-async-combobox-proposal`** — propozycja API prymitywu `AsyncComboboxField` (single/multi, debounce, preload wybranych etykiet, stany loading/empty/error, klawiatura, ARIA combobox). AC: API zaakceptowane przez właściciela DS.
- [ ] **9. `feat/ds-async-combobox`** — implementacja prymitywu + strona w `/admin/design-system` + vitest/a11y.
- [ ] **10. `feat/panel-relation-options`** — generyczny endpoint opcji. **HIGH-RISK (autoryzacja)**
  - Rejestr źródeł (wzorzec jak `AdminSearchSource`), `GET admin/options/{source}?q=&ids[]=`, autoryzacja `viewAny` modelu źródła, limit wyników, rate limit, wyszukiwanie przez `LikeSearch`, tylko allowlistowane kolumny.
  - AC: nieznane źródło = 404; brak uprawnienia = 403; brak N+1; test wstrzyknięcia wzorca LIKE.
- [ ] **11. `feat/panel-async-relations`** — tagi w artykułach na `AsyncComboboxField`; generator: `belongsTo`/`belongsToMany` korzystają z endpointu (flaga `--async-relations` albo domyślnie — do decyzji w kroku 8).
- [ ] **12. `feat/panel-inline-create`** — „Dodaj nowy” w comboboxie otwiera `FormDialog` i wybiera utworzony rekord; pierwsze użycie: tagi. **HIGH-RISK (policy)** — wymaga `create` na modelu docelowym.

## Faza D — pola warunkowe

- [ ] **13. `docs/ds-resource-form-conditions-proposal`** — propozycja `visibleWhen` w definicji pola `ResourceForm` (deklaratywne warunki na wartościach innych pól, bez dowolnych funkcji w danych z serwera).
- [ ] **14. `feat/ds-resource-form-conditions`** — implementacja + refaktor formularza pozycji menu (dziś warunki są ręczne w `navigation/form.tsx`) jako dowód.
  - Serwer: lustrzane reguły (`exclude_unless`/`required_if`); ukryte pole nigdy nie jest autoryzacją.
  - AC: wynik zapisu menu identyczny jak przed refaktorem (testy bez zmian asercji).

## Faza E — eksport

- [ ] **15. `refactor/panel-list-export`** — wspólny eksport z `ListQuery` (dziś osobno w kontakcie i w stubach `--export`): stream CSV (`lazyById`), te same filtry/sort co lista, `CsvCell` przeciw formula injection, ability `export`, audit. Bez zmiany zachowania kontaktu.
- [ ] **16. `feat/panel-content-export`** — eksport dla artykułów, stron, FAQ i tagów (z bieżącymi filtrami i zapisanym widokiem). **HIGH-RISK (policy, dane)** — domyślnie tylko admin.

## Faza F — porządki

- [ ] **17. `docs/panel-operations-wrapup`** — aktualizacja `STATE.md`, rejestru komponentów (13), katalogu (11: ADM-04/07/10/12/13 statusy), instrukcji panelu; usunięcie nieaktualnego `TODO.md` (`isAdmin()` już korzysta z ról).

## Otwarte decyzje

| ID | Decyzja | Krok |
| --- | --- | --- |
| R1 | Czy editor może klonować i eksportować, czy tylko admin? | 1, 16 |
| R2 | Async relacje domyślnie w generatorze czy za flagą? | 8, 11 |
| R3 | Czy FAQ zachowuje edytowalną liczbę pozycji jako fallback (import)? | 5 |
