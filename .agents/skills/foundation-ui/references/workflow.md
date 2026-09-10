Najpierw znajdź komponent, publiczny eksport, token i podobny ekran.
Przeczytaj potrzebny fragment ADR-019 w docs/foundation/05-design-system-and-frontend.md.
Zweryfikuj stan kodu: planowane prymitywy i ui-contract nie są wdrożone
tylko dlatego, że opisano je w dokumentacji. components/ui nie jest
automatycznie zatwierdzoną warstwą prymitywów.

Użyj istniejącego zatwierdzonego API. Jeśli go brakuje, przedstaw minimalne
rozszerzenie: czego brakuje, alternatywy, dwa konkretne miejsca użycia,
light/dark, responsive i a11y. Uzyskaj akceptację przed implementacją nowej
decyzji; nie pytaj ponownie o już zaakceptowany kontrakt.
Jednorazowy wyjątek wymaga dokładnego zakresu, ownera, zgody i terminu usunięcia.
Nie twórz obejścia przez className/style, spread, CSS import lub deep import.
Nie przenoś ekranu do prymitywów, aby ominąć kontrolę.

Składaj ekran przez semantyczne props i zamknięte warianty. Motyw obsługują
tokeny; ekran nie wybiera kolorów ani dark: ręcznie. Uwzględnij loading,
empty, error, pending i success oraz klawiaturę, focus i etykiety.
Łączenie z backendem realizuj zgodnie z projektowym Wayfinder/Inertia.

Dobierz test do zachowania i uruchom istniejące kontrole typów oraz lint.
Uruchom ui-contract, gdy istnieje; jego brak raportuj jako brak bramki.
Sprawdź wizualnie zakres zmiany w obu motywach i właściwych viewportach,
jeśli dostępne środowisko na to pozwala. Nie akceptuj sam baseline.
Raportuj użyte API, zaakceptowane rozszerzenie/wyjątek i dowody weryfikacji.

Przed stagingiem sprawdź `git status --short`, `git diff` i `git diff --cached`.
Nie dotykaj cudzych lub nieznanych zmian staged. Stosuj Conventional Commits i
nie używaj `--no-verify`, `LEFTHOOK=0`, skip/exclude ani obejść hooków/CI.
Commit i push wykonuj wyłącznie po jawnym poleceniu użytkownika; push pozostaje
ręczny. Po commicie sprawdź SHA, zawartość commita i status, a w raporcie
rozróżnij kontrole wykonane od niewykonanych.
