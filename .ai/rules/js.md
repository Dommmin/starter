---
paths:
  - 'resources/js/**'
---

# Js

## Każdy layout musi być responsywny (RWD)
Każdy tworzony lub modyfikowany layout — publiczny, panelu i ekranów uwierzytelniania — musi od początku obsługiwać RWD (mobile-first), także bez makiety mobilnej. Korzystaj z zatwierdzonych responsywnych wariantów design systemu. Przed uznaniem pracy za gotową sprawdź mobile od 360 px, tablet, desktop i szerokości pośrednie: czytelność treści, dostępność nawigacji i akcji oraz brak nakładania elementów, ucinania treści i poziomego przewijania całej strony. Szerokie tabele mogą mieć kontrolowany scroll we własnym kontenerze. RWD jest kryterium odbioru, nie późniejszym ulepszeniem.

## Kontrakty Inertia generowane z Laravel Data
Wymagany standard docelowy: propsy całych stron, współdzielone payloady i zagnieżdżone struktury backendu definiuje Laravel Data; TypeScript i enumy kontraktu generujemy z PHP, bez ręcznego duplikowania. CI ma wykrywać drift i sprawdzać typy po generacji. Typy lokalnego stanu UI pozostają lokalne. Zachowuj semantykę boolean, enum, null/optional, dat i paginacji; bez rzutowań TS maskujących błędny payload. Szczegóły i granice wdrożenia: docs/foundation/01-architecture-and-modularity.md.

## Page resolver, lazy layouts and pure primitives (bundle budget)
Strony rozwiązuje `resources/js/lib/page-resolver.ts` (jawny `import.meta.glob` z negacją `*.test.tsx` — testy nie trafiają do buildu klienta/SSR). Layouty (auth/admin/settings/app) ładują się dynamicznie przed stroną; nową grupę layoutu dodawaj w `layoutGroup`/`layoutLoaders`, nie statycznym importem w `app.tsx`. Moduły `resources/js/design-system/primitives/**` są traktowane jako wolne od efektów ubocznych (`vite.config.ts` treeshake) — nie umieszczaj w nich kodu wykonywanego przy imporcie. Budżet gzip pilnuje `npm run check:budget` (`bundle-budget.json`); progu nie podnoś bez uzasadnienia i review.
