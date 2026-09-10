# Frontend

Glob: `resources/js/**`, `resources/css/**`

## Stos i rendering

- React 19 + TypeScript strict + Inertia v3 + Tailwind v4. Jeden renderer dla panelu i publicznych stron.
- Publiczne trasy wymagają SSR: treść, title, description i canonical muszą być obecne w pierwszym HTML bez JS.
- Strony Inertia w `resources/js/pages/`. Używaj `Inertia::render()` po stronie serwera.
- Przed zmianą wzorca Inertia sprawdź dokumentację (`search-docs`). Aktywuj skill `inertia-react-development` przy pracy z klientem React.
- Inertia v3: `useHttp`, `useLayoutProps`, instant visits, `Inertia::optional()` zamiast `Inertia::lazy()`. Axios usunięty — używaj wbudowanego klienta XHR.

## LLM-safe UI contract (ADR-019 — wymaganie użytkownika)

- Semantyczne tokeny, nie techniczne nazwy kolorów (`slate-700`, hex) w kodzie aplikacyjnym. Tokeny w `resources/css/`.
- `className`, `class`, `style`, arbitrary Tailwind values, helpery `cn`/`clsx`/`cva` — dozwolone wyłącznie wewnątrz prymitywów (warstwa `design-system/primitives/`) lub w jawnym, zatwierdzonym wyjątku. Nazwa katalogu `components/ui` nie daje automatycznie tego prawa.
- Zakaz: surowy CSS/CSS modules/CSS-in-JS, tagi `<style>`, `element.style`, deep imports vendorów omijające API DS, spread nieznanych props do DOM.
- Ekrany i moduły aplikacji importują wyłącznie publiczne API design systemu.
- Nie przenoś ekranu do katalogu prymitywów, aby ominąć reguły.

## Light/dark

- Oba motywy definiują ten sam komplet semantycznych tokenów. Przełączenie na korzeniu dokumentu.
- Komponenty nie znają motywu, nie używają `dark:` i nie wybierają koloru warunkiem `isDark`.
- Każdy nowy token musi mieć wartość w obu motywach.

## Nowy token lub wariant

- Wymaga: decyzja designowa, co najmniej dwa konkretne miejsca ponownego użycia i akceptacja właściciela DS — przed implementacją.
- Najpierw szukaj istniejącego komponentu/tokena. Jeśli nie wystarcza → zaproponuj rozszerzenie → uzyskaj akceptację → implementuj.
- Nie twórz lokalnego obejścia.

## Wyjątki od kontraktu UI

- Wyjątek ma: ID, plik/symbol, powód, review człowieka, owner, termin wygaśnięcia i plan usunięcia.
- Nie używaj globalnego `eslint-disable`, wildcardów katalogowych ani wiecznych wyjątków.

## Komponenty

- Jedna rodzina ikon: Lucide. Import pojedynczych ikon, `aria-hidden` dla dekoracji, accessible name na przycisku ikonowym.
- Shadcn/Radix to baza implementacyjna, nie publiczne API ekranów. Aktualizacja upstream wymaga review, nie samego podbicia.
- `resources/js/components/ui/*` to vendored shadcn — wykluczony z lint/fmt w `vite.config.ts`.
- Sprawdź istniejące komponenty przed napisaniem nowego.

## Stany i formularze

- Każdy ekran: loading (skeleton), empty, error, success. Partial failure dla procesów asynchronicznych.
- Formularz: po 422 focus na pierwszym błędzie, `aria-describedby` na polu, zachowane dane (poza hasłami). Pending i blokada powtórnego kliknięcia po submit.
- Toast nie zastępuje komunikatu przy polu.
- Dialog: focus trap, zamknięcie Escape, powrót focus, potwierdzenie akcji destrukcyjnej.

## Dostępność

- Cel: WCAG 2.2 AA. Semantyczne nagłówki, landmarki, skip link, label, widoczny focus, klawiatura, kontrast.
- Mobile 360 px, tablet, desktop i zoom 200%.
- Cele dotykowe preferowane 44×44 px.

## Wydajność

- Budżety: publiczna strona ≤100 KB skompresowanego JS aplikacyjnego; panel ≤250 KB początkowego JS.
- LCP ≤2,5 s, INP ≤200 ms, CLS ≤0,1 (cele pomiaru laboratoryjnego P0).
- Prefetch Inertia: ograniczony do prawdopodobnych nawigacji GET, krótki TTL, nigdy mutacji.

## Wersje JS

- Przed użyciem API paczki sprawdź `package.json`. React 19, Inertia v3, Tailwind v4, Vite 8, Wayfinder 0.x.
