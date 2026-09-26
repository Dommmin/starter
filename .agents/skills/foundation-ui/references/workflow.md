Najpierw znajdź komponent, publiczny eksport, token i podobny ekran.
Przeczytaj tylko fragment ADR-019 potrzebny do bieżącej decyzji.
Zweryfikuj stan kodu: planowane prymitywy i ui-contract nie są wdrożone
tylko dlatego, że opisano je w dokumentacji. components/ui nie jest
automatycznie zatwierdzoną warstwą prymitywów.

Użyj istniejącego zatwierdzonego API. Jeśli go brakuje, zatrzymaj implementację
i przedstaw minimalne rozszerzenie: czego brakuje, alternatywy, dwa konkretne
miejsca użycia, light/dark, responsive i a11y. Uzyskaj akceptację przed
implementacją nowej decyzji; nie pytaj ponownie o już zaakceptowany kontrakt.
Jednorazowy wyjątek wymaga dokładnego zakresu, ownera, zgody i terminu usunięcia.
Nie twórz obejścia przez className/style, spread, CSS import lub deep import.
Nie przenoś ekranu do prymitywów, aby ominąć kontrolę.

Dla partii komponentów zapisz przed kodem maksymalnie 2–5 elementów jednej
rodziny, ich publiczne API oraz konkretne AC dla stanów i interakcji. Po
gotowym diffie wykonawca uruchamia kontrole, a `foundation-reviewer` może
jednorazowo wykonać read-only review wskazanych AC i plików. Findings wracają
do tego samego wykonawcy; druga runda review tylko przy nowym ryzyku.

Składaj ekran przez semantyczne props i zamknięte warianty. Motyw obsługują
tokeny; ekran nie wybiera kolorów ani dark: ręcznie. Uwzględnij loading,
empty, error, pending i success oraz klawiaturę, focus i etykiety.
Łączenie z backendem realizuj zgodnie z projektowym Wayfinder/Inertia.

Dobierz test do zachowania i uruchom istniejące kontrole typów oraz lint.
Uruchom ui-contract, gdy istnieje; jego brak raportuj jako brak bramki.
Sprawdź wizualnie zakres zmiany w obu motywach i właściwych viewportach,
jeśli dostępne środowisko na to pozwala. Nie akceptuj sam baseline.
Raportuj użyte API, zaakceptowane rozszerzenie/wyjątek i dowody weryfikacji.

Dla jasnej poprawki UI obowiązuje limit FAST: po 2 minutach analizy bez
minimalnego kroku albo po dwóch nieudanych próbach podaj nowy fakt i poproś o decyzję. Nie zamieniaj lokalnej
zmiany w redesign, nową markę ani analizę całego design systemu.

Przed stagingiem sprawdź `git status --short`, `git diff` i `git diff --cached`.
Nie dotykaj cudzych lub nieznanych zmian staged. Stosuj Conventional Commits i
nie używaj `--no-verify`, `LEFTHOOK=0`, skip/exclude ani obejść hooków/CI.
Commit i push wykonuj wyłącznie po jawnym poleceniu użytkownika; push pozostaje
ręczny. Po commicie sprawdź SHA, zawartość commita i status, a w raporcie
rozróżnij kontrole wykonane od niewykonanych.
