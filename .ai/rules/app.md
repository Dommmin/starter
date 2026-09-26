---
paths:
  - 'app/**'
---

# App

## Kontrakt architektury i typowania — 2026-09-10
Obowiązuje zaakceptowany kontrakt w docs/foundation/01-architecture-and-modularity.md: thin controllers, własne FormRequests z jawnym authorize, Actions bez HTTP, konkretne Repositories dla zapytań aplikacyjnych; Services i interfejsy tylko z uzasadnieniem. Laravel Data i generowany TypeScript są wymaganym standardem kontraktów Inertia; walidacja wejścia ma jedno źródło w FormRequest. Używaj enumów dla zamkniętych zestawów wartości domenowych oraz jawnych castów Eloquent (w tym boolean dla 0/1, enum, daty i JSON); DTO nie naprawia brakujących castów. SOLID/DRY/KISS/YAGNI i kompozycja służą prostocie, bez pustych warstw i generic CRUD. Standard jest decyzją docelową, nie deklaracją wdrożenia paczek ani migracji istniejącego kodu.
