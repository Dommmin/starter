# Roadmapa implementacji

## Cel

Dostarczyć mały starter nadający się do pierwszego klienta, a następnie rozwijać tylko potrzebne moduły. To kolejność przyszłych działań; **żadne zadanie implementacyjne poniżej nie zostało wykonane ani zatwierdzone przez samo utworzenie planu**.

## Decyzje i uzasadnienie

### ADR-018: ograniczony pilotaż, rozwój według dowodów

Status: proponowany. P0 ma jedną referencyjną domenę: strony z draft/published i prosty kontakt. P0 obejmuje adminowy DAM i ograniczony Tiptap zgodnie z zatwierdzonym kierunkiem. Bez importu Excel, publicznego API, dynamicznego RBAC, multi-tenancy i własnego CMS. Kity są granicami zakresu, nie osobnymi produktami.

Pierwotny szacunek P0, przed doprecyzowaniem LLM-safe UI contract: 12–18 osobodni doświadczonego zespołu, w tym konfiguracja operacyjna i review; nie jest ofertą ani terminem zobowiązującym. Hosting, decyzje dostępu, niezgodność paczek lub brak review mogą zwiększyć czas kalendarzowy. Po P0-A ponownie oszacować zakres, uwzględniając prymitywy light/dark, reguły AST, kontrolę TSX/CSS/SSR, DAM, rich text i testy kontraktu. Te kontrole należą do P0, nie do opcjonalnego P1. Przy przekroczeniu budżetu usuwać opcjonalne UI i moduły, nie MFA, testy dostępu, restore i approval.

Konfigurację AI wdrażać małymi krokami według [09](09-agents-skills-and-workflows.md): w P0 krótki routing w instrukcjach obu produktów, dwa wąskie skille i jeden opcjonalny reviewer; pozostałe role dopiero na żądanie. Nie budować orkiestratora. Pilotaż porównuje czas/koszt prostych zadań z pojedynczym agentem, zachowując bramki bezpieczeństwa.

## Etapy i kryteria wyjścia

| Etap  | Zakres / budżet roboczy                                                           | Zależność                            | Dowód wyjścia                                                   |
|-------|-----------------------------------------------------------------------------------|--------------------------------------|-----------------------------------------------------------------|
| D0/D1 | Decyzje, threat model, runtime, hosting i opiekunowie; 1–2 dni                    | Potwierdzenie miejsca i celu         | Zatwierdzone ADR-y, scope i realna ścieżka ręcznej produkcji    |
| P0-A  | Git/scaffold/natywne środowisko z PostgreSQL, Redis i Horizon; GitHub CI; 3–4 dni | D1                                   | Projekt odtwarzalny, wersje przypięte, podstawowe testy i skany |
| P0-B  | Auth/MFA/policies, UI, SSR, CRUD stron/artykułów, DAM i kontakt; 5–7 dni          | P0-A                                 | Kryteria funkcjonalne, SSR i negatywne testy spełnione          |
| P0-C  | Staging, backup/restore, alerty, Deployer na jednej VM UE; 3–5 dni                | P0-B i hosting                       | UAT, restore, atomowy release, health check i próbny rollback   |
| P1    | Moduły pierwszego klienta i usprawnienia operacji                                 | Zakończony P0, zaakceptowane stories | Każdy moduł ma testy, koszty i opiekuna                         |
| P2    | Uogólnienia na podstawie ≥2 projektów                                             | Pomiary ponownego użycia             | ADR wykazuje korzyść większą niż utrzymanie abstrakcji          |

Role: PO = właściciel produktu; TL = tech lead/reviewer; DEV = developer wspierany AI; OPS = operator; SEC = człowiek odpowiedzialny za bezpieczeństwo. W małym zespole jedna osoba może pełnić kilka ról, ale agent nigdy nie zastępuje człowieka w zatwierdzeniu. Przed startem przypisać nazwiska i zastępcę operatora.

W zadaniu 09 obowiązuje kolejność: inwentaryzacja decyzji i miejsc użycia → zaakceptowane API → mapy light/dark → prymitywy React → reguły lint i negatywne testy → składanie ekranów SSR. Nie budować ekranu z lokalnymi klasami z obietnicą późniejszego uporządkowania.

## Przykładowe kryteria pilotażu

- Given ekran z `className`, arbitrary value lub style poza prymitywem, when uruchamia się CI, then PR jest blokowany, chyba że istnieje dokładny, zatwierdzony i niewygasły wyjątek.
- Given token lub wariant bez rekordu decyzji albo wartości dark, when uruchamia się ui-contract, then kontrola nie przechodzi.
- Given admin bez potwierdzonego MFA, when otwiera panel, then widzi wyłącznie enrollment/logout/recovery; bez możliwości użycia CRUD przez bezpośredni request.
- Given editor, when zmienia chronione pole roli lub wykonuje niedozwoloną publikację, then backend odmawia i nie zmienia DB.
- Given draft, when gość zna slug, then otrzymuje 404, a wpisu nie ma w sitemap/cache.
- Given opublikowana strona, when zmienia się slug, then stary URL przekierowuje raz do nowego, a canonical i sitemap wskazują nowy.
- Given zgłoszenie kontaktowe i awaria mailera, when dostarczenie zawodzi, then zgłoszenie pozostaje pending/failed i operator otrzymuje alarm; replay jest kontrolowane.
- Given konto agenta i zbudowane archiwum, when próbuje wdrożenia/approval produkcji, then platforma odmawia przed udostępnieniem credentiali.

## Ryzyka i reguły zatrzymania

Brak potwierdzonego katalogu → nie scaffoldować. Brak danych o hostingu → można rozwijać lokalny kod po autoryzacji, ale nie deklarować gotowego release. Niezgodna opcjonalna paczka → usunąć ją z etapu zamiast obniżać framework. Brak skutecznego approval → nie uruchamiać produkcji. Nieudany restore lub niewykonane testy dostępu → bramka klienta zamknięta. Zmiana danych/sekretów/policies poza zaakceptowanym zakresem → przygotować propozycję i uzyskać wyraźną zgodę przed zmianą.

## Checklista odbioru roadmapy

- [ ] P0 mieści się w jednym procesie referencyjnym i ma właścicieli.
- [ ] Q1–Q5 oraz wybór runtime rozwiązane przed scaffoldem.
- [ ] Każde zadanie ma zależność i konkretny dowód ukończenia.
- [ ] Zdolności security/backup/monitoring nie zależą od instalacji opcjonalnych dashboardów.
- [ ] Wdrożenie produkcyjne wymaga człowieka, również hotfix; automatyczny rollback w zatwierdzonym oknie deployu wykonuje recepta według 10.
- [ ] P1/P2 uruchamiane przez zatwierdzone potrzeby klienta.

## Otwarte pytania

Do rozstrzygnięcia: właściwe repo (Q1), klient i zakres (Q2/Q8), UI (Q3), DB/hosting/region (Q4), budżet i approvals (Q5), RTO/RPO (Q6), monitoring/dane (Q7), runtime (Q9), opiekun startera (Q10). Pełne rekomendacje są w [master planie](00-master-plan.md). Decyzje produktowe pozostają otwarte, ale każdy etap ma propozycję domyślnego wariantu i bramkę przed pracą zależną.

## Zadania w kolejności wykonania

| Kolejność | Priorytet | Zadanie                                                                                                                                                         | Zależność                 | Właściciel             | Dowód ukończenia                                                                        |
|-----------|-----------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------------------|------------------------|-----------------------------------------------------------------------------------------|
| 01        | P0        | Potwierdzić pusty katalog lub wskazać istniejące repo; przy repo ponowić analizę                                                                                | Brak                      | PO/TL                  | Jawnie potwierdzony punkt startowy                                                      |
| 02        | P0        | Ustalić pierwszy profil klienta, non-goals, budżet, role i AC                                                                                                   | 01                        | PO                     | Zaakceptowany scope, maksymalny budżet P0                                               |
| 03        | P0        | Przypiąć patche Laravel 13/PHP 8.5/Node 24 LTS, PostgreSQL, hosting UE i plan GitHub; potwierdzić React/Inertia SSR                                             | 02                        | TL/OPS                 | Zatwierdzone ADR, zgodność toolchainu i wykonalny approval produkcji                    |
| 04        | P0        | Uzgodnić threat model, retencję, RTO/RPO i sposób backupu/monitoringu                                                                                           | 03                        | SEC/PO/OPS             | Lista ryzyk i właścicieli; zgody danych                                                 |
| 05        | P0        | Po osobnej autoryzacji utworzyć Git i zakwalifikować snapshot startera, tagi/licencje/advisories                                                                | 03–04                     | DEV/TL                 | Manifesty/lockfile i dowody z rejestru 07; bez przypadkowych funkcji                    |
| 06        | P0        | Przygotować natywne środowisko według 10, doctor/setup/start, testowy mail, PostgreSQL, Redis/Horizon, scheduler i instrukcję setup                             | 05                        | DEV/OPS                | Uruchomienie od zera ≤30 min i brak prod danych                                         |
| 07        | P0        | Skonfigurować checks CI, scanning, ochronę branch/workflow i kont agenta; krótki routing FAST/STANDARD/HIGH-RISK oraz minimalne adaptery Claude/Codex według 09 | 05–06                     | TL/OPS                 | PR bez wymaganych kontroli nie może się połączyć                                        |
| 08        | P0        | Po zatwierdzeniu modelu dostępu wdrożyć auth, e-mail verification, reset, MFA, role statyczne i policies                                                        | 04, 07                    | DEV/SEC                | Testy login/reset/MFA/enumeracji i odmów; brak domyślnego hasła                         |
| 09        | P0        | Zdefiniować decyzje tokenów/wariantów, typowane prymitywy i light/dark; uruchomić ui-contract oraz rejestr wyjątków przed składaniem shell/panel/public layout  | 03, 07                    | DEV/TL + właściciel DS | Zamknięte API, lint/typy/CSS i negatywne fixtures blokują obejścia; oba motywy dostępne |
| 10        | P0        | Po zgodzie na migracje/policies wdrożyć CRUD stron i artykułów, Tiptap, sanitizer oraz podstawowy audit                                                         | 08–09                     | DEV/TL                 | Draft/published, walidacja, konflikt, policy, rich text i audit z testami               |
| 11        | P0        | Dodać publiczny Inertia SSR, meta, sitemap, redirects, 404, ochronę preview oraz kontrolowany cache/prefetch                                                    | 10                        | DEV                    | HTML SSR, test braku draftów, kontrola canonical/301 i cache                            |
| 11a       | P0        | Wdrożyć prywatny adminowy DAM lokalnie na VM: limit 50 MB, kwarantanna, skan, publiczne warianty obrazów i backup                                               | 08–10                     | DEV/SEC/OPS            | Testy policy, skanu, odmowy i braku dostępu użytkowników do DAM                         |
| 12        | P0        | Dodać kontakt bez załączników, limity, trwały status dostarczenia, recovery i cleanup                                                                           | 08–09                     | DEV/OPS                | Test awarii mailera i bezpiecznego ponowienia                                           |
| 13        | P0        | Zamknąć testy feature/contract/E2E, progi static/coverage, manualne a11y i SEO                                                                                  | 10–12                     | DEV/TL                 | Wszystkie bramki 03 spełnione; raport ograniczeń                                        |
| 14        | P0        | Przygotować recipe Deployer dla Nginx/PHP-FPM, pięciu release’ów, Horizon i stagingu na VM UE                                                                   | 07, 13                    | OPS                    | Atomowy release zatwierdzonego commita, readiness/smoke i aktualny Horizon na stagingu  |
| 15        | P0        | Włączyć JSON/journal, rotację i archiwizację poza hostem, projekcję zdarzeń dla admina, error/uptime/queue/scheduler/backup alerty według 10                    | 14                        | OPS                    | Kontrolowane awarie wywołują odebrane alarmy                                            |
| 16        | P0        | Wykonać backup/restore i próbę automatycznego rollbacku, zerwania SSH i zgodności SSR/jobów według 10                                                           | 14–15                     | OPS/TL                 | Zmierzony RTO/RPO, rollback aplikacji bez automatycznego cofania migracji               |
| 17        | P0        | Przetestować realną odmowę merge/deploy/approval dla agenta i zaakceptować UAT                                                                                  | 13–16                     | SEC/PO                 | Zapis kontroli uprawnień i zgoda na konkretny release                                   |
| 18        | P0        | Człowiek wykonuje pierwsze wydanie, smoke i obserwację; zapis wersji startera i instrukcji wsparcia                                                             | 17                        | OPS/PO                 | Release done według 03/06, spełniona checklista pierwszego klienta                      |
| 19        | P1        | Według potrzeb klienta: uploady użytkowników, ustawienia i sekcje; uploady mają własny storage, policies i skan/kwarantannę                                     | 18 + nowe AC              | DEV/SEC                | Funkcje oraz negatywne testy bezpieczeństwa                                             |
| 20        | P1        | Dynamiczne role i Activitylog, jeżeli statyczny dostęp/audit już nie wystarcza                                                                                  | 18 + approval ról         | DEV/SEC                | Macierz dostępu, invalidacja i retencja sprawdzone                                      |
| 21        | P1        | Pierwszy moduł App Kit, DTO/generacja typów, API/webhook/XLSX tylko jeśli zamówione                                                                             | 18 + spec                 | DEV/TL                 | Kontrakty, retry i przypadki nadużyć przetestowane                                      |
| 22        | P1        | Health lub Pulse/APM po pomiarach; regularny restore drill                                                                                                      | 15–18 + metryki           | OPS                    | Udokumentowana poprawa i budżet; brak zdublowanych monitorów                            |
| 23        | P1        | Uporządkować katalog komponentów, selektywne visual tests, aktualizacje kopii startera; po pomiarach dodawać kolejne role/skille z 09                           | 18–21                     | TL/DEV                 | Powtarzalny proces update i akceptacji designu                                          |
| 24        | P2        | Po dwóch wdrożeniach ocenić generowanie CRUD i ekstrakcję wspólnych pakietów                                                                                    | 23 + pomiary              | TL/PO                  | ADR pokazujący realną oszczędność i koszt utrzymania                                    |
| 25        | P2        | Analizować SSR, response cache, multi-tenancy, SSO/passkeys lub skalowanie tylko przy nowej potrzebie                                                           | 24 + osobny business case | TL/SEC/PO              | Osobny scope, threat model, testy i zgoda; brak automatycznej realizacji                |
