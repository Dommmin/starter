# Reguły architektury

## Cel

Utrzymać prosty monolit z czytelnymi granicami procesów. Szczegóły: [architektura](../../docs/foundation/01-architecture-and-modularity.md).

## Decyzje

Najpierw sprawdź istniejący kod i ADR. Controller koordynuje HTTP, FormRequest waliduje wejście, policy chroni operację, Action realizuje proces, Resource/DTO definiuje jawny payload. Nie dodawaj wszystkich warstw do trywialnego endpointu. Nie zapisuj bezpośrednio tabel innego modułu bez jawnego, zaakceptowanego kontraktu.

## Uzasadnienie

Używaj Eloquent i standardowego Laravel; nie twórz generic repository, BaseCrudService, silnika modułów ani własnego event busa bez potwierdzonego problemu. Inertia nie potrzebuje równoległego API. Generowane typy i trasy mają jedno źródło; Wayfinder nie generuje kontraktu odpowiedzi. Publiczne trasy React/Inertia mają SSR; nie dodawaj Blade ani kolejnego frameworka bez ADR.

## Ryzyka

Transakcje nie obejmują atomowo zewnętrznego maila. Jobs muszą mieć timeout, retry i idempotencję; cache nie stanowi źródła prawdy. Nie włączaj współdzielonego response cache dla danych sesyjnych i draftów.

## Checklista

- [ ] Właściciel danych i zależności są jawne.
- [ ] Policy i test odmowy chronią operację.
- [ ] DTO nie ujawnia niepotrzebnych pól.
- [ ] Mutacje, błędy, retry i konkurencja mają odpowiednią obsługę.

## Otwarte pytania

Nowa granica modułu, publiczne API, SSR i abstrakcja wymagają uzasadnienia w ADR; status proponowany nie oznacza zatwierdzenia.
