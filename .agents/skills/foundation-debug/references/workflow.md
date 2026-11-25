Prowadź błąd, czerwony test albo zachowanie niezgodne z oczekiwanym od odtworzenia do poprawki
przyczyny. Bez odtworzenia nie ma poprawki. Źródło: skill debugging z ai-playbook-templates 3.62.2.

1. Odtwórz: komenda (`make test ARGS='--filter=...'`, żądanie HTTP, `make artisan ARGS='...'`)
   i jej wynik: komunikat, stack trace, kod wyjścia. Błąd środowiska (baza, kolejka, Vite,
   uprawnienia): najpierw `make doctor`. Błąd niepowtarzalny: zapisz warunki i zapytaj.
2. Zawęź: log i stack trace od pierwszego wpisu z błędem; najmniejszy przypadek (jeden test,
   rekord, żądanie); regresja po zmianie, która działała: `git bisect` z komendą z kroku 1.
   SQL zależny od silnika: odtwórz też `make test-pgsql` albo `make test-mysql`.
3. Hipoteza: jedno zdanie „błąd powstaje, bo…” i sposób sprawdzenia przed zmianą kodu. Zapisz
   wynik, także obalający. Potwierdzona przyczyna to `plik:linia` z wyrażeniem i jednym zdaniem
   „dlaczego”; do tego czasu zmieniasz najwyżej log tymczasowy w 1–2 plikach.
4. Test czerwony: test, który pada na obecnym kodzie z tej przyczyny. Uruchom i zapisz wynik.
5. Poprawka przyczyny, nie objawu (pusty `catch`, `?->`/`??` na wartości, która nie może być
   pusta, ponawianie „aż przejdzie” to objaw). Ten sam test przechodzi; potem testy obszaru.
6. Usuń logi tymczasowe i kod diagnostyczny. Po zmianie PHP: Pint jak w AGENTS.md.

Zakazy: nie wyciszasz błędu (skip, ignore, baseline, obniżony poziom logu); nie dopasowujesz
asercji do błędnego zachowania; ta sama hipoteza po dwóch nieudanych próbach: zmień podejście
albo zgłoś blokadę (co odtworzyłeś, co wykluczyłeś i czym, czego potrzebujesz). Nie czytasz
`.env*` poza `.env.example`. Logi i dane w raporcie bez PII i sekretów.

Raport po polsku:
- Błąd: `<komenda>` → wynik przed poprawką.
- Przyczyna: `<plik:linia>`, wyrażenie i dlaczego; czym sprawdzona.
- Wykluczone: hipoteza → czym obalona.
- Dowód: test na starym kodzie (czerwony), ten sam po poprawce (zielony), testy obszaru.
