# Backend

Glob: `app/**`, `routes/**`, `database/**`, `config/**`

## Architektura

- Modularny monolit, standardowy Laravel. Nie twórz generic repository, BaseCrudService, silnika modułów ani event busa bez zatwierdzonego ADR.
- Controller koordynuje HTTP; FormRequest waliduje wejście; Policy chroni operację; Action realizuje przypadek użycia; Resource/DTO definiuje jawny payload. Nie dodawaj wszystkich warstw do trywialnego endpointu.
- Nie zapisuj bezpośrednio tabel innego modułu bez jawnego kontraktu.
- Inertia nie potrzebuje równoległego REST API. API dla zewnętrznego konsumenta: `/api/v1`, Resources, paginacja i OpenAPI.

## Eloquent i dane

- Używaj Eloquent z jawnym eager loading (`with(...)`). `Model::shouldBeStrict(true)` jest aktywne poza produkcją — lazy loading, ciche odrzucanie atrybutów i odczyt niepobranych atrybutów rzucą wyjątek.
- SQL parametryzowany; nazwy sortowania/kolumn z allowlisty. Nigdy `request()->all()` na uprzywilejowanych modelach.
- Pieniądze: integer w najmniejszej jednostce + waluta; daty ISO 8601 UTC; duże identyfikatory jako string; enum i nullable identyczne po obu stronach PHP/TS.
- Unikalność gwarantuje constraint DB, nie tylko walidacja requestu. Przy edycji sprawdzaj `updated_at` lub lock w transakcji.

## Migracje

- Nie edytuj migracji już wykonanej na współdzielonym środowisku. Każda zmiana migracji wymaga akceptacji człowieka.
- Nowe kolumny/tabele: expand/contract. Nowa nullable kolumna przed kodem zależnym; backfill osobnym idempotentnym jobem. Usuwanie starego pola dopiero w późniejszym release po obserwacji.

## Kolejki i scheduler

- Każdy job: timeout, retry z backoff, maksymalny limit prób i idempotencja (unikalny klucz operacji w DB). `dispatch` po commit transakcji.
- Failed jobs mają alarm. Worker restartowany po zmianie obrazu.
- Scheduler: `onOneServer`/`withoutOverlapping` ze współdzielonym lockiem Redis.

## Wayfinder

- Wygenerowane pliki (`resources/js/actions/**`, `resources/js/routes/**`, `resources/js/wayfinder/**`) mają jedno źródło i deterministyczną komendę. Nie poprawiaj ich ręcznie.
- Preferuj named routes i `route()`.

## PHP

- Jawne return type declarations i type hints. PHP 8 constructor property promotion. Nawiasy klamrowe nawet przy jednoliniowych ciałach.
- TitleCase dla kluczy Enum. PHPDoc blocks zamiast inline comments (chyba że wyjątkowo złożona logika).
- Przed użyciem API paczki sprawdź jej zainstalowaną wersję: `composer show <vendor/package>`.
- Po edycji PHP uruchom `vendor/bin/pint --dirty --format agent` przez Docker.

## Komendy Artisan

- Używaj `php artisan make:*` z `--no-interaction` do tworzenia nowych plików.
- Przy nowym modelu utwórz factory i seeder.
- Komendy uruchamiaj przez Docker: `docker compose exec app php artisan ...` lub `make artisan ARGS='...'`.
