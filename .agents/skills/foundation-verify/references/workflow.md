Oddaj skończoną zmianę do review człowieka z dowodami, nie zapewnieniami. Dobierz realne komendy
z repo do diffu i opisz wyniki. Źródło: skill finish z ai-playbook-templates 3.62.2.

1. Zbierz AC z polecenia, issue albo planu. Każde AC dostaje komendę i wynik albo powód, dla
   którego nie da się go sprawdzić automatycznie (wtedy instrukcja sprawdzenia ręcznego).
2. Dobierz kontrole do diffu (odczytaj scripts z `composer.json`, `package.json`, `Makefile` i CI):
   - PHP: najwęższy `make test ARGS='--filter=...'`, Pint przez `make composer`;
   - SQL zależny od silnika: `make test-pgsql` i `make test-mysql`;
   - TS/TSX/CSS: `make npm ARGS='run check'`, `make npm ARGS='run types:check'`, testy UI;
   - konfiguracja agentów, hooków, CI: `python3 -m unittest discover -s tests/ai`;
   - STANDARD i HIGH-RISK przed PR: `make check`; FAST: kontrole obszaru, resztę robi CI.
   Czerwony wynik: napraw przyczynę (skill `foundation-debug`) i wróć do kroku 1.
3. Przejrzyj własny diff względem gałęzi bazowej: kod diagnostyczny, zakomentowany kod, zmiany
   poza zakresem, `.env*`, artefakty builda. Usuń to, co zostawiłeś.
4. STANDARD i HIGH-RISK: `foundation-reviewer` dostaje cel, pliki, AC, patch, wyniki kontroli,
   budżet i warunek zakończenia (kontrakt w dokumencie 09). Findings krytyczne i ważne popraw,
   potem krok 2 ponownie. Najwyżej 2 rundy; otwarte uwagi idą do opisu PR. FAST: bez reviewera.
5. Zmiana UI albo endpointu w zadaniu STANDARD/HIGH-RISK: zaproponuj człowiekowi sprawdzenie
   działającej aplikacji (mobile 360 px, light/dark); nie zastępuj go własną oceną.
6. Opis PR według `.github/pull_request_template.md`: „Zmiana i kryteria odbioru” (AC z dowodem),
   „Weryfikacja” (komenda → wynik z tej wersji kodu; niewykonane z przyczyną), „Ryzyko i review”
   (tryb, wpływ na dane, findings reviewera i ich rozstrzygnięcie, cofnięcie).

Commit, push i PR tylko na jawne polecenie człowieka, nigdy na `main` ani `develop`. Przed
zgłoszeniem sukcesu sprawdź SHA, staged diff i status. Wynik w opisie pochodzi z kodu po ostatniej
poprawce; brak uruchomienia kontroli to nie PASS.
