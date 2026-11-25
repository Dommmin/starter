# Testowanie

Glob: `tests/**`

## Framework i runner

- Pest 5 na PHPUnit. Twórz testy: `php artisan make:test --pest {name}` (bez katalogu `Feature/` w nazwie).
- Uruchamiaj testy przez Docker: `make test ARGS='--filter=testName'` lub `docker compose exec app php artisan test --compact`.
- Uruchamiaj najwęższy zestaw testów pokrywający zmianę. Po zaliczeniu poproś użytkownika o pełny `make test`.
- Nie usuwaj testów ani plików testowych bez akceptacji człowieka.
- Aktywuj skill `testing-best-practices` przed pisaniem testów.

## Co testować

- Każda zmiana auth, policies, walidacji, migracji, logiki i integracji wymaga testu adekwatnego do ryzyka.
- Bugfix: najpierw reprodukcja (test red), potem poprawka (test green); raport pokazuje oba wyniki.
- Test chroni decyzję w kodzie projektu i pada, gdy jest zła. Nie piszesz testu, który przejdzie przy każdej poprawnej konfiguracji frameworka (pojedyncza reguła walidacji, relacja ORM, routing, renderowanie bez warunku).
- Jeden test na zachowanie z AC i na gałąź własnej logiki; przypadek brzegowy tylko, gdy kod ma dla niego gałąź albo kontrakt wejścia dopuszcza tę wartość.
- Mockujesz tylko granice systemu (`Http::fake`, `Queue::fake`, `Mail::fake`, czas); własnej logiki nie mockujesz. Bez `sleep`, prawdziwej sieci i zależności od kolejności testów.
- Asercji w istniejących testach nie zmieniasz bez powodu w PR; wymaganie sprzeczne z testem: zatrzymaj się i zgłoś.
- Feature/integration dla przepływów i autoryzacji; unit dla reguł biznesowych.
- Test odmowy (403/404) sprawdza również brak zmiany w DB/queue/mail.
- Testy kontraktu Inertia props: jawne pola, enum/null/data, paginacja.

## Czego nie testować nadmiernie

- Nie pisz testu samego gettera, skopiowanego vendora ani deklaracji implementacji.
- Zmiana dokumentacji lub prostego wyglądu bez zachowania — jawna weryfikacja manualna w PR, bez wymuszania testu.

## Dane testowe

- Factories z jawnymi stanami (`verified`, `admin`, `mfa_enabled`, `draft`, `published`). Sprawdź istniejące stany factory przed ręcznym setupem.
- Syntetyczne dane. Osobna DB per proces; prefiks cache/queue/storage per run.
- Żaden test nie używa URL, credentials ani kopii DB produkcji.
- Faker: sprawdź konwencję `$this->faker` vs `fake()`.

## Progi jakości

- 100% zidentyfikowanych uprzywilejowanych endpointów ma test odmowy.
- Co najmniej 80% linii własnych Actions i Policies.
- Larastan/PHPStan level 6, zero błędów, bez nowego baseline.
- TS strict, zero błędów typecheck.
- Zero sekretów w testach (Gitleaks).

## AI i raporty

- Agent wyprowadza testy z zaakceptowanych kryteriów (AC), nie z własnego kodu.
- Raportuj komendę, rzeczywisty wynik i ograniczenia. Nieuruchomiony test nie jest „zaliczony".
- Zmniejszenie progu, usunięcie testu, snapshot update i suppress wymagają uzasadnienia i niezależnego review.
