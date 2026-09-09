# Reguły testowania

## Cel

Dostarczać dowody działania, nie pozorne pokrycie. Szczegóły: [testy i jakość](../../docs/foundation/03-testing-and-quality.md).

## Decyzje

Testy wynikają z AC. Feature/integration dla przepływów i autoryzacji, unit dla reguł, kilka E2E dla podróży użytkownika. DB integracyjna zgodna z produkcyjną rodziną/majorem. Testy izolowane, dane syntetyczne, bez produkcyjnych credentiali.

## Uzasadnienie

Każda zmiana auth, policies, walidacji, migracji, logiki lub integracji wymaga testu adekwatnego do ryzyka. Bugfix ma reprodukcję. Zmiana dokumentacji lub prostego wyglądu bez zachowania może mieć tylko jawnie opisaną weryfikację manualną. Nie twórz testów kopii gettera ani deklaracji implementacji. Obowiązkowe są testy kontraktu UI: zamknięte props, negatywne fixtures reguł lint, brak obejść w TSX/CSS, kompletność light/dark, ważność wyjątków i rzeczywiste blokowanie CI. Nie testuj kopii wartości hex, lecz spójność i granice dozwolonego API.

## Ryzyka

Nie usuwaj testu, nie obniżaj progu i nie aktualizuj snapshotu tylko dlatego, że zmiana nie przechodzi. Wyjaśnij przyczynę i wymagaj niezależnego review. Brak uruchomienia testu nie jest PASS. Retry nie usuwa przyczyny flaky testu.

## Checklista

- [ ] Sukces, odmowa i granice wynikają ze specyfikacji.
- [ ] Test odmowy sprawdza brak mutacji/maila/joba.
- [ ] Rzeczywiste komendy z repo uruchomione; wynik i ograniczenia zapisane.
- [ ] Progi dokumentu 03 spełnione lub jawna blokada do rozstrzygnięcia przez człowieka.

## Otwarte pytania

Gdy brakuje AC, scenariusza lub infrastruktury testowej, nazwij brak i zaproponuj minimalny dowód. Nie deklaruj pełnej weryfikacji na podstawie samej analizy kodu.
