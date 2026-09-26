---
paths:
  - 'resources/js/**'
---

# Js

## Każdy layout musi być responsywny (RWD)
Każdy tworzony lub modyfikowany layout — publiczny, panelu i ekranów uwierzytelniania — musi od początku obsługiwać RWD (mobile-first), także bez makiety mobilnej. Korzystaj z zatwierdzonych responsywnych wariantów design systemu. Przed uznaniem pracy za gotową sprawdź mobile od 360 px, tablet, desktop i szerokości pośrednie: czytelność treści, dostępność nawigacji i akcji oraz brak nakładania elementów, ucinania treści i poziomego przewijania całej strony. Szerokie tabele mogą mieć kontrolowany scroll we własnym kontenerze. RWD jest kryterium odbioru, nie późniejszym ulepszeniem.

## Kontrakty Inertia generowane z Laravel Data
Wymagany standard docelowy: propsy całych stron, współdzielone payloady i zagnieżdżone struktury backendu definiuje Laravel Data; TypeScript i enumy kontraktu generujemy z PHP, bez ręcznego duplikowania. CI ma wykrywać drift i sprawdzać typy po generacji. Typy lokalnego stanu UI pozostają lokalne. Zachowuj semantykę boolean, enum, null/optional, dat i paginacji; bez rzutowań TS maskujących błędny payload. Szczegóły i granice wdrożenia: docs/foundation/01-architecture-and-modularity.md.
