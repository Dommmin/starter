# Roadmapa implementacji

## Cel

Dostarczyć mały starter nadający się do pierwszego klienta, a następnie rozwijać tylko potrzebne moduły. To kolejność przyszłych działań; **żadne zadanie implementacyjne poniżej nie zostało wykonane ani zatwierdzone przez samo utworzenie planu**.

## Decyzje i uzasadnienie

### ADR-018: ograniczony pilotaż, rozwój według dowodów

Status: proponowany. P0 ma jedną referencyjną domenę: strony z draft/published i prosty kontakt. P0 obejmuje adminowy DAM i ograniczony Tiptap zgodnie z zatwierdzonym kierunkiem. Bez importu Excel, publicznego API, dynamicznego RBAC, multi-tenancy i własnego CMS. Kity są granicami zakresu, nie osobnymi produktami.

Pierwotny szacunek P0, przed doprecyzowaniem LLM-safe UI contract: 12–18 osobodni doświadczonego zespołu, w tym konfiguracja operacyjna i review; nie jest ofertą ani terminem zobowiązującym. Hosting, decyzje dostępu, niezgodność paczek lub brak review mogą zwiększyć czas kalendarzowy. Po P0-A ponownie oszacować zakres, uwzględniając prymitywy light/dark, reguły AST, kontrolę TSX/CSS/SSR, DAM, rich text i testy kontraktu. Te kontrole należą do P0, nie do opcjonalnego P1. Przy przekroczeniu budżetu usuwać opcjonalne UI i moduły, nie MFA, testy dostępu, restore i approval.

Konfigurację AI wdrażać małymi krokami według [09](09-agents-skills-and-workflows.md): w P0 krótki routing w instrukcjach obu produktów, dwa wąskie skille i jeden opcjonalny reviewer; pozostałe role dopiero na żądanie. Nie budować orkiestratora. Pilotaż porównuje czas/koszt prostych zadań z pojedynczym agentem, zachowując bramki bezpieczeństwa.

## Etapy i kryteria wyjścia

| Etap  | Zakres / budżet roboczy                                                                | Zależność                            | Dowód wyjścia                                                   |
| ----- | -------------------------------------------------------------------------------------- | ------------------------------------ | --------------------------------------------------------------- |
| D0/D1 | Decyzje, threat model, runtime, hosting i opiekunowie; 1–2 dni                         | Potwierdzenie miejsca i celu         | Zatwierdzone ADR-y, scope i realna ścieżka ręcznej produkcji    |
| P0-A  | Git/scaffold/Docker Compose z PostgreSQL, Redis; Horizon planowany; GitHub CI; 3–4 dni | D1                                   | Projekt odtwarzalny, wersje przypięte, podstawowe testy i skany |
| P0-B  | Auth/MFA/policies, UI, SSR, CRUD stron/artykułów, DAM i kontakt; 5–7 dni               | P0-A                                 | Kryteria funkcjonalne, SSR i negatywne testy spełnione          |
| P0-C  | Staging, backup/restore, alerty, Deployer na jednej VM UE; 3–5 dni                     | P0-B i hosting                       | UAT, restore, atomowy release, health check i próbny rollback   |
| P1    | Moduły pierwszego klienta i usprawnienia operacji                                      | Zakończony P0, zaakceptowane stories | Każdy moduł ma testy, koszty i opiekuna                         |
| P2    | Uogólnienia na podstawie ≥2 projektów                                                  | Pomiary ponownego użycia             | ADR wykazuje korzyść większą niż utrzymanie abstrakcji          |

Role: PO = właściciel produktu; TL = tech lead/reviewer; DEV = developer wspierany AI; OPS = operator; SEC = człowiek odpowiedzialny za bezpieczeństwo. W małym zespole jedna osoba może pełnić kilka ról, ale agent nigdy nie zastępuje człowieka w zatwierdzeniu. Przed startem przypisać nazwiska i zastępcę operatora.

W zadaniu 09 obowiązuje kolejność: inwentaryzacja decyzji i miejsc użycia → zaakceptowane API → mapy light/dark → prymitywy React → reguły lint i negatywne testy → składanie ekranów SSR. Nie budować ekranu z lokalnymi klasami z obietnicą późniejszego uporządkowania.

## Uzupełnienie P0-A: commity, Lefthook i guardy — 2026-09-10

Zakres do wdrożenia przed P0-B, jako rozwinięcie zadania 07. Kontrakt: [03 — commity i hooki](03-testing-and-quality.md#commity-i-lefthook--kontrakt-p0-a) oraz [04 — guardy agentów](04-ai-sdlc.md#guardy-agentów-przy-commitach--p0-a). Wszystkie poniższe zadania pozostają otwarte; aktualizacja planu nie instaluje narzędzi ani nie konfiguruje ochrony zdalnego repo.

| Kolejność | Zadanie | Zależność / właściciel | Dowód ukończenia |
| --- | --- | --- | --- |
| 07a | **Częściowo wykonane 2026-09-10:** przypięto Lefthook, commitlint, Gitleaks i Deployer; dodano `.githooks`, `lefthook.yml`, `commitlint.config.mjs`, `Makefile` oraz testy. | 05–06 / DEV/TL | Lokalnie: błędna wiadomość jest blokowana, konfiguracja AI/deploy przechodzi testy, Gitleaks 8.30.1 i core.hooksPath są aktywne. Brakuje testu świeżego klonu i syntetycznego sekretu w tym repo. |
| 07b | **Częściowo wykonane 2026-09-10:** CI waliduje commity/tytuły PR, sekrety i jakość dla `develop`/`main`; ręczny deploy buduje artefakt i używa GitHub Environment. | Dostęp administratora GitHub / TL/OPS | Pozostaje ustawić required checks, ochronę branchy, reviewerów, branch policy i sekrety/zmienne środowisk na GitHub oraz zweryfikować workflow po ręcznym pushu. |
| 07c | **Częściowo wykonane 2026-09-10:** guardy dodano do AGENTS, CLAUDE i wspólnych procedur FAST/UI; reviewer ma ograniczone narzędzia. | 07a–07b / DEV/SEC | Testy statyczne przechodzą. Pozostaje rzeczywisty pilotaż klientów oraz odmowa merge/deploy przez konto agenta po skonfigurowaniu platformy. |

Nie zamykać 07b–07c na podstawie samych plików konfiguracyjnych. Brak uprawnień administratora lub ownera blokuje wyłącznie odbiór zależnych zabezpieczeń; lokalne hooki są aktywne i przetestowane osobno. Dla zadań 08–09 „07” obejmuje teraz także odbiór 07a–07c. Zmiana zabezpieczeń wymaga review człowieka; pilotaż korzysta z syntetycznych repo/danych, bez obchodzenia ochrony właściwego repo.

## Usprawnienia CRUD — wymaganie 2026-09-10

Użytkownik wskazał szybkie tworzenie CRUD-ów, walidację frontową i tabele wielokrotnego użycia jako wymaganie startera. Proponowane etapowanie poniżej uzupełnia zadania 09–10 i 21; implementacja pozostaje otwarta. Kontrakt opisuje [05 — formularze i tabele](05-design-system-and-frontend.md#szybkie-crud-y-wspólne-formularze-i-tabele--2026-09-10).

| Etap | Zakres | Dowód ukończenia |
| --- | --- | --- |
| P0-B, zadania 09–10 | Wspólny formularz i tabela użyte we wzorcowym CRUDzie; walidacja podczas edycji, wyszukiwanie, filtry, sortowanie, paginacja i akcje rekordu. | Create/edit współdzielą pola; testy obejmują błędny zapis i brak uprawnień, niedozwolone filtry/sortowanie, reset paginacji oraz odtworzenie URL po odświeżeniu i Wstecz. Test UI sprawdza feedback walidacji, starsze odpowiedzi oraz obsługę błędów i klawiatury. |
| P0-B, zaraz po wzorcowym CRUDzie (decyzja 2026-09-27) | Generator `make:resource` emitujący zwykłe pliki (controller, FormRequest, policy, strony React, testy) na bazie wzorcowego CRUD-a, ze wspólnym `ListQuery`, `ResourceTable` i `ResourceForm`; dry-run i odmowa nadpisania istniejących plików. | Wygenerowany moduł przechodzi testy, typecheck i kontrakt UI bez ręcznych poprawek; test generatora potwierdza dry-run i odmowę nadpisania. |
| P2, po pomiarach | Ocena uniwersalnego silnika zasobów i ewentualnej ekstrakcji paczki. | Korzyść i koszt utrzymania potwierdzone użyciem w co najmniej dwóch projektach; osobna decyzja zakresowa. |

Decyzja 2026-09-27 (właściciel): generator szablonu przesunięty z P1 do P0-B, bo szybkie CRUD-y są celem startera. Zasady z 01 pozostają: generator emituje jawne pliki, bez runtime metaprogramowania i klas BaseEverything. Uniwersalny silnik zasobów nadal pozostaje w P2. Rozszerzenie P0-B wymaga aktualizacji szacunku.

## Wielojęzyczność — wymaganie 2026-09-10

Obowiązkowe P0, również dla klienta startującego z jednym językiem. Kontrakt: [01 — i18n i SEO](01-architecture-and-modularity.md#wielojęzyczność-i-adresy-url--p0), [05 — tłumaczenia UI](05-design-system-and-frontend.md#tłumaczenia-całego-interfejsu--p0), [03 — odbiór](03-testing-and-quality.md#odbiór-wielojęzyczności--obowiązkowe-p0). Poniższe prace pozostają do wdrożenia; uzupełnienie planu nie potwierdza ich wykonania.

| Etap | Zakres | Dowód ukończenia |
| --- | --- | --- |
| D0/D1 | Ustalić dowolny publiczny język domyślny (bez obowiązku PL), osobne aktywne języki strony i panelu, właściciela tłumaczeń i publiczne adresy bez prefiksu domyślnego lub z prefiksem każdego języka. | Klient zna konkretne przykłady URL i skutki późniejszej zmiany; strategia zapisana przed publikacją. |
| P0-A, przed kolejnymi ekranami zadań 08–09 | Rejestr locale z osobną konfiguracją strony i panelu, panel domyślnie EN bez prefiksów z preferencją profilu/sesji, katalogi i adaptery backend/React, wspólny kontekst SSR/klienta, generowanie publicznych URL i kontrola kompletności tłumaczeń; objąć też ekrany dostarczone ze starterem. | Konfiguracja EN/DE bez PL działa; panel zachowuje URL i preferencję, bez wyboru używa EN. Formularz i ekran błędu są tłumaczone, test izolacji locale przechodzi, brak klucza wykrywany przez CI. |
| P0-B, zadania 08–12 | Cały UI/auth/walidacja/powiadomienia lokalizowane; Content przechowuje tłumaczenia i publikację per locale od pierwszego modelu; publiczne URL, przełącznik, canonical, hreflang i sitemap. | Test dodania DE bez przebudowy kodu oraz testy SSR, braków tłumaczeń i zmiany slugów przechodzą. |
| P0-C, przed odbiorem zadania 13 | Odbiór wszystkich aktywnych katalogów, treści publicznych, SEO i dłuższych tekstów na mobile. | Kryteria 03 spełnione i tłumaczenia odebrane przez wskazaną osobę; niekompletne wersje publiczne nie są publikowane. |

To zależność początkowa zadań 08–12, a nie poprawka po ukończeniu Website Kit. Wcześniejsze szacunki wymagają aktualizacji o i18n, model treści i odbiór języków; nie przesuwać tego zakresu do P1 w celu zachowania starego budżetu.

## Katalog komponentów — wymaganie 2026-09-10

Zakres docelowy i źródła: [11 — katalog](11-component-catalog.md). Status: wymaganie dokumentacyjne zapisane; implementacja i zakup produktów pozostają osobnymi zadaniami. Etapy rozszerzają zadania 09, 10, 19, 21 i 23, bez obietnicy pełnego pokrycia przed pilotażem.

| Etap | Zadanie | Kryterium ukończenia |
| --- | --- | --- |
| P0-A | Zmapować istniejące komponenty do ID katalogu, rozdzielić planned/candidate/implemented/verified; wskazać publiczne API i braki pilotażu. | Każdy element użyty w pilocie ma rekord, właściciela i status oparty na dowodach. |
| P0-B | Formularz, tabela, szczegóły, akcje, shell i sekcje strony pilotażowej ze wspólnych komponentów; demo local/staging. | Lista/create/edit/view i publiczna strona używają udokumentowanych importów; przykłady i stany spełniają odbiór 11. |
| P1 | Rozbudować katalog panel/aplikacja/strona, relacje i szablony; kwalifikować konkretne darmowe/płatne kompozycje. | Wyszukiwanie po funkcji, demo i przykład użycia; drugi CRUD korzysta z tego samego API. Każdy zakupiony element ma zapisane prawa dla docelowego użycia. |
| P2 | Specjalistyczne pola, kalendarz/Kanban/mapy, konfigurowalne dashboardy i pozostałe rodziny według potrzeby klienta. | Osobny zakres, koszt i testy; mapowanie do katalogu i brak duplikowania wspólnego zachowania. |

Przed deklaracją pełnego pokrycia Filament porównać indeks wybranej wersji z ADM-01–18 i zapisać wszystkie luki lub świadome wyłączenia. Marketplace pozostaje źródłem okresowej oceny wartościowych dodatków, a nie obowiązkiem skopiowania każdego pluginu. Szacunki pilotażu nie obejmują automatycznie budowy całego katalogu.

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

## Zakres małych aplikacji — decyzja właściciela 2026-09-30

Starter służy małym aplikacjom klientów budowanym w weekend lub tydzień, bez płatności, multi-tenancy i dynamicznego RBAC. Z roadmapy wycięto: dynamiczne role i Activitylog (20), multi-tenancy i SSO (25), formalny test odmowy merge/deploy dla agenta (17), Pulse/APM (22), obowiązkowy staging (14) i formalny pomiar RTO/RPO (04, 16). Powrót któregokolwiek z tych punktów wymaga nowego zamówienia klienta i osobnej decyzji. Cel ≤100 KB JS strony publicznej zastąpiono zapadką z `bundle-budget.json` (decyzja D4).

## Zadania w kolejności wykonania

| Kolejność | Priorytet | Zadanie                                                                                                                                                         | Zależność                 | Właściciel             | Dowód ukończenia                                                                        |
| --------- | --------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------- | ---------------------- | --------------------------------------------------------------------------------------- |
| 01        | P0        | Potwierdzić pusty katalog lub wskazać istniejące repo; przy repo ponowić analizę                                                                                | Brak                      | PO/TL                  | Jawnie potwierdzony punkt startowy                                                      |
| 02        | P0        | Ustalić pierwszy profil klienta, non-goals, budżet, role i AC                                                                                                   | 01                        | PO                     | Zaakceptowany scope, maksymalny budżet P0                                               |
| 03        | P0        | Przypiąć patche Laravel 13/PHP 8.5/Node 24 LTS, PostgreSQL, hosting UE i plan GitHub; potwierdzić React/Inertia SSR                                             | 02                        | TL/OPS                 | Zatwierdzone ADR, zgodność toolchainu i wykonalny approval produkcji                    |
| 04        | P0        | Uzgodnić threat model, retencję i sposób backupu/monitoringu; RTO/RPO szacunkowe (Q6), bez formalnej procedury                                                                                           | 03                        | SEC/PO/OPS             | Lista ryzyk i właścicieli; zgody danych                                                 |
| 05        | P0        | Po osobnej autoryzacji utworzyć Git i zakwalifikować snapshot startera, tagi/licencje/advisories                                                                | 03–04                     | DEV/TL                 | Manifesty/lockfile i dowody z rejestru 07; bez przypadkowych funkcji                    |
| 06        | P0        | Docker Compose + Makefile zaimplementowane: setup/doctor/up, Mailpit, PostgreSQL, Redis, Horizon i scheduler                                                       | 05                        | DEV/OPS                | Uruchomienie od zera ≤30 min i brak prod danych                                         |
| 07        | P0        | Skonfigurować checks CI, scanning, ochronę branch/workflow i kont agenta; krótki routing FAST/STANDARD/HIGH-RISK oraz minimalne adaptery Claude/Codex według 09 | 05–06                     | TL/OPS                 | PR bez wymaganych kontroli nie może się połączyć                                        |
| 08        | P0        | Po zatwierdzeniu modelu dostępu wdrożyć auth, e-mail verification, reset, MFA, role statyczne i policies                                                        | 04, 07                    | DEV/SEC                | Testy login/reset/MFA/enumeracji i odmów; brak domyślnego hasła                         |
| 09        | P0        | Zdefiniować decyzje tokenów/wariantów, typowane prymitywy i light/dark; uruchomić ui-contract oraz rejestr wyjątków przed składaniem shell/panel/public layout  | 03, 07                    | DEV/TL + właściciel DS | Zamknięte API, lint/typy/CSS i negatywne fixtures blokują obejścia; oba motywy dostępne |
| 10        | P0        | Po zgodzie na migracje/policies wdrożyć CRUD stron i artykułów, Tiptap, sanitizer oraz podstawowy audit                                                         | 08–09                     | DEV/TL                 | Draft/published, walidacja, konflikt, policy, rich text i audit z testami               |
| 11        | P0        | Dodać publiczny Inertia SSR, meta, sitemap, redirects, 404, ochronę preview oraz kontrolowany cache/prefetch                                                    | 10                        | DEV                    | HTML SSR, test braku draftów, kontrola canonical/301 i cache                            |
| 11a       | P0        | Wdrożyć prywatny adminowy DAM lokalnie na VM: limit 50 MB, kwarantanna, skan, publiczne warianty obrazów i backup                                               | 08–10                     | DEV/SEC/OPS            | Testy policy, skanu, odmowy i braku dostępu użytkowników do DAM                         |
| 12        | P0        | Dodać kontakt bez załączników, limity, trwały status dostarczenia, recovery i cleanup                                                                           | 08–09                     | DEV/OPS                | Test awarii mailera i bezpiecznego ponowienia                                           |
| 13        | P0        | Zamknąć testy feature/contract/E2E, progi static/coverage, manualne a11y i SEO                                                                                  | 10–12                     | DEV/TL                 | Wszystkie bramki 03 spełnione; raport ograniczeń                                        |
| 14        | P0        | Przygotować recipe Deployer dla Nginx/PHP-FPM, pięciu release’ów i Horizon na VM UE; staging tylko, gdy klient go zamówi                                                                   | 07, 13                    | OPS                    | Atomowy release zatwierdzonego commita, readiness/smoke i aktualny Horizon  |
| 15        | P0        | Włączyć JSON/journal, rotację i archiwizację poza hostem, projekcję zdarzeń dla admina, error/uptime/queue/scheduler/backup alerty według 10                    | 14                        | OPS                    | Kontrolowane awarie wywołują odebrane alarmy                                            |
| 16        | P0        | Wykonać backup/restore i próbę automatycznego rollbacku, zerwania SSH i zgodności SSR/jobów według 10                                                           | 14–15                     | OPS/TL                 | Udany restore z backupu i rollback aplikacji bez automatycznego cofania migracji               |
| 17        | P0        | ~~Test odmowy merge/deploy dla agenta~~ (wycięte 2026-09-30, patrz niżej); zaakceptować UAT                                                                                  | 13–16                     | SEC/PO                 | Zgoda na konkretny release                                   |
| 18        | P0        | Człowiek wykonuje pierwsze wydanie, smoke i obserwację; zapis wersji startera i instrukcji wsparcia                                                             | 17                        | OPS/PO                 | Release done według 03/06, spełniona checklista pierwszego klienta                      |
| 19        | P1        | Według potrzeb klienta: uploady użytkowników, ustawienia i sekcje; uploady mają własny storage, policies i skan/kwarantannę                                     | 18 + nowe AC              | DEV/SEC                | Funkcje oraz negatywne testy bezpieczeństwa                                             |
| 20        | P1        | ~~Dynamiczne role i Activitylog~~ (wycięte 2026-09-30: poza zakresem startera)                                                                                  | 18 + approval ról         | DEV/SEC                | Macierz dostępu, invalidacja i retencja sprawdzone                                      |
| 21        | P1        | Pierwszy moduł App Kit; API/webhook/XLSX tylko jeśli zamówione                                                                             | 18 + spec                 | DEV/TL                 | Kontrakty, retry i przypadki nadużyć przetestowane                                      |
| 22        | P1        | Health po pomiarach; regularny restore drill (bez Pulse/APM)                                                                                                      | 15–18 + metryki           | OPS                    | Udokumentowana poprawa i budżet; brak zdublowanych monitorów                            |
| 23        | P1        | Uporządkować katalog komponentów, selektywne visual tests, aktualizacje kopii startera; po pomiarach dodawać kolejne role/skille z 09                           | 18–21                     | TL/DEV                 | Powtarzalny proces update i akceptacji designu                                          |
| 24        | P2        | Po dwóch wdrożeniach ocenić generowanie CRUD i ekstrakcję wspólnych pakietów                                                                                    | 23 + pomiary              | TL/PO                  | ADR pokazujący realną oszczędność i koszt utrzymania                                    |
| 25        | P2        | Analizować response cache lub skalowanie tylko przy nowej potrzebie (multi-tenancy i SSO wycięte 2026-09-30)                                                           | 24 + osobny business case | TL/SEC/PO              | Osobny scope, threat model, testy i zgoda; brak automatycznej realizacji                |

## Kontrakty danych — wymaganie P0 z 2026-09-10

Laravel Data + TypeScript Transformer przeniesiono z opcjonalnego P1 do obowiązkowego P0. Do wykonania: kwalifikacja i instalacja zgodnych wersji, Data dla istniejących kontraktów Inertia, generowane typy całych stron i enumów, kontrola driftu i typecheck w CI, testy serializacji oraz audyt castów modeli (boolean 0/1, enum, null, daty, JSON i liczby). Stosować podział warstw i kryteria z [01](01-architecture-and-modularity.md). Ta aktualizacja dokumentacji nie oznacza wykonania wdrożenia.
