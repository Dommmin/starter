# Szybka instrukcja dla Gemini / Antigravity

Przeczytaj \`AGENTS.md\`; jest źródłem pełnych reguł projektu. Ten plik jest
adapterem wejściowym — nie zastępuje instrukcji ani nie daje dodatkowych
uprawnień.

## Domyślny tryb FAST

- Dla jasnej, lokalnej poprawki: jeden wykonawca, odczyt wyłącznie miejsca
  zmiany, minimalny patch, najwęższa adekwatna kontrola i krótki raport.
- Analiza bez podjęcia minimalnego kroku trwa najwyżej 2 minuty. Nie uruchamiaj pełnego discovery, wieloagentowego review,
  specyfikacji, ADR ani szerokiego researchu dla znanego wzorca.
- Po 2 minutach albo po dwóch nieudanych próbach zatrzymaj się. Podaj jeden
  nowy fakt, który rozszerza zakres, i poproś o decyzję zamiast kontynuować
  analizę lub przebudowę.
- Nie rozszerzaj zadania o redesign, nową markę, tokeny, warianty komponentów,
  dokumentację ani refaktor. Dla UI użyj istniejącego API; brakujący wariant
  najpierw przedstaw do akceptacji.

## Niezmienne bramki

- Przed edycją przeczytaj \`.ai/rules/index.md\` i wyłącznie reguły pasujące do
  zmienianych ścieżek.
- Dla UI uruchom \`make npm ARGS='run check:ui-contract'\`. Naruszenia w
  stronach, komponentach, layoutach lub CSS blokują wynik, chyba że istnieje
  ważny, zatwierdzony wyjątek.
- Nie commituj, nie pushuj, nie merge'uj, nie wdrażaj i nie zmieniaj sekretów
  bez wyraźnego polecenia człowieka.
