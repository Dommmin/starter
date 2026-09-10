Wykonaj jasno opisane zadanie w bieżącej sesji bez delegacji.
Przeczytaj instrukcje projektu i tylko pliki potrzebne do zmiany.
Polecenie wyznacza cel i kryteria odbioru; nie twórz osobnej spec/ADR.
Auth, role/policies, migracje, sekrety, płatności, produkcja i istotna architektura
wymagają HIGH-RISK według instrukcji projektu. Zachowaj już udzieloną zgodę;
nazwa FAST nie pozwala pominąć bramki ryzyka.

Sprawdź istniejący wzorzec i zmień minimalny zakres. UI podlega ADR-019;
brakujący wariant najpierw zaproponuj. Nie uruchamiaj całego katalogu skilli.
Odczytaj faktyczne scripts z composer.json/package.json i właściwe CI.
Dla zmiany zachowania dodaj lub popraw test, uruchom najwęższe pokrycie.
Dla PHP uruchom wymagany Pint. Copy/config sprawdź adekwatnie do diffu.
Nie uruchamiaj composer setup jako weryfikacji istniejącej instalacji:
generuje klucz aplikacji i wykonuje migracje. Brak komendy nie oznacza PASS.

Po dwóch nieudanych próbach tej samej przyczyny lub przekroczeniu celu 10 minut
nazwij nowy fakt i dostosuj podejście w udzielonym upoważnieniu.
Nową decyzję biznesową lub rozszerzenie wrażliwego zakresu przedstaw użytkownikowi.
Zakończ wynikiem, rzeczywistymi kontrolami i pozostałymi ograniczeniami.
Bez merge chronionego brancha, produkcji i samodzielnego zatwierdzania pracy.

Przed stagingiem sprawdź `git status --short`, `git diff` i `git diff --cached`.
Nie dotykaj cudzych lub nieznanych zmian staged. Stosuj Conventional Commits i
nie używaj `--no-verify`, `LEFTHOOK=0`, skip/exclude ani obejść hooków/CI.
Commit i push wykonuj wyłącznie po jawnym poleceniu użytkownika; push pozostaje
ręczny. Po commicie sprawdź SHA, zawartość commita i status, a w raporcie
rozróżnij kontrole wykonane od niewykonanych.
