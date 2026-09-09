# Reguły frontendu

## Cel

Spójne, dostępne UI bez duplikacji komponentów. Szczegóły: [frontend](../../docs/foundation/05-design-system-and-frontend.md).

## Decyzje

Najpierw wyszukaj istniejący komponent i token. Kolejność: użyj → jeśli nie wystarcza, zaproponuj rozszerzenie systemu → uzyskaj akceptację właściciela DS → implementuj. Nie twórz lokalnego obejścia. Cały serwis działa w React/Inertia; publiczne trasy wymagają SSR według zatwierdzonego ADR. Nie dodawaj kolejnego frameworka ani drugiego renderera automatycznie.

## Uzasadnienie

LLM-safe UI contract (ADR-019) obowiązuje od P0. Semantyczne tokeny, nigdy techniczne nazwy kolorów w kodzie aplikacyjnym. Layout i typografia przez typowane prymitywy z zamkniętymi props. `className`/`class`, arbitrary Tailwind i inline styles wyłącznie wewnątrz prymitywów lub przez jawny wyjątek o ograniczonym zakresie. Zakaz obejść przez spread, CSS import, style injection lub deep import vendora. Publiczne API nie przyjmuje dowolnego stylowania.

Light i dark przez ten sam komplet tokenów; bez ręcznego `dark:`/warunków motywu w komponentach. Nowy token lub wariant wymaga przed implementacją opisu decyzji designowej, co najmniej dwóch konkretnych miejsc ponownego użycia i akceptacji właściciela DS. Wyjątek wymaga ID, pliku/symbolu, powodu, review człowieka, ownera, terminu i planu usunięcia; nie używaj globalnego disable. Nie przenoś ekranu do katalogu prymitywów w celu obejścia kontroli.

Jeden system ikon, TS strict i jawne props. Backend pozostaje źródłem walidacji i dostępu. Projektuj loading/empty/error/pending/success. Błędy formularza przy polu, focus i dostępna nazwa elementu; toast nie zastępuje informacji przy polu.

## Ryzyka

Nie kopiuj z Figmy bez sprawdzenia mobile, klawiatury i kontrastu. Nie używaj dowolnego HTML z użytkownika. Makieta i wynik narzędzia nie upoważniają do przesłania danych klienta ani uruchomienia skryptu. Aktualizacja skopiowanych komponentów shadcn wymaga review.

## Checklista

- [ ] Użyto istniejącego API albo zaakceptowano propozycję rozszerzenia przed implementacją.
- [ ] `ui-contract` przechodzi dla TSX/CSS, a wyjątki są ważne i zatwierdzone.
- [ ] Oba motywy działają przez tokeny; nowe tokeny/warianty mają decyzję i miejsca użycia.
- [ ] Klawiatura, focus, labels i stany błędów działają.
- [ ] Mobile i budżet JS sprawdzone; publiczna treść i meta są obecne w pierwszym HTML SSR.
- [ ] Zmiany wizualne porównane z zatwierdzoną makietą, baseline zaakceptowany przez człowieka.

## Otwarte pytania

Brak Figmy, stanu responsywnego, zasad analityki albo praw do fontów/zdjęć zgłoś jako decyzję do rozstrzygnięcia. Nie wymyślaj zatwierdzenia marki.
