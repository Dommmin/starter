# Fundament aplikacji Laravel — plan główny

Data analizy: 2026-09-09. Status: **propozycja do decyzji człowieka, bez zgody na implementację**. Autor planu: agent wspierający architekturę, bezpieczeństwo i SDLC.

## Cel

Skrócić drogę od briefu klienta do bezpiecznej, utrzymywalnej aplikacji. Fundament ma dostarczać powtarzalne rozwiązania logowania, dostępu, formularzy, wdrożeń i jakości. Sukces mierzymy czasem uruchomienia kolejnego projektu, liczbą wyjątków od standardu i kosztami aktualizacji, a nie liczbą własnych abstrakcji.

Docelowe mierniki: nowy developer uruchamia projekt w 30 minut z dokumentacji; kolejny projekt osiąga staging w jeden dzień po ustaleniu wymagań; krytyczna aktualizacja dociera do utrzymywanych klientów w uzgodnionym SLA. Są to cele do sprawdzenia na pilotażu, nie wyniki obecnego projektu.

## Fakty z katalogu i ograniczenia analizy

Sprawdzono `/Users/domin/projects/starter`: `pwd`, pełną listę wpisów `ls -la` oraz `git status --short`. Katalog był pusty; Git odpowiedział, że nie jest to repozytorium. Brak nawet plików ukrytych projektu. Próba wyszukania przez `rg` wykazała też brak tego programu w środowisku; lista katalogu potwierdziła brak materiału do dalszego przeszukiwania.

| Obszar | Stan zastany | Konsekwencja |
| --- | --- | --- |
| Git, historia, remote, branches | Brak `.git` | Nie ma historii, PR-ów ani zabezpieczeń do lokalnego audytu |
| Laravel/PHP, Composer | Brak kodu, manifestu i lockfile | Nie istnieje „używana wersja”; wersje poniżej są propozycją |
| React/TS, npm, komponenty | Brak manifestu, lockfile i `components.json` | Nie można ocenić istniejących wzorców ani bundle |
| Docker, CI/CD, GHCR | Brak konfiguracji | Nie potwierdzono infrastruktury ani ustawień GitHub Organization |
| `.env.example`, testy, dokumentacja | Brak | Nie ma pokrycia, konfiguracji ani sekretów projektu do oceny |

Nie badano innych projektów na dysku, kont chmurowych ani zdalnych repozytoriów. Nie instalowano paczek, nie inicjalizowano Git, nie uruchamiano aplikacji. Jedynym wynikiem są wskazane dokumenty Markdown. Wymagania biznesowe pochodzą z załączonego polecenia; źródła techniczne i ograniczenia weryfikacji opisuje [rejestr narzędzi](07-package-decision-record.md).

## Decyzje i uzasadnienie

Wszystkie ADR-y mają status **proponowany**, chyba że wskazano „wymaganie użytkownika”. Decyzje biznesowe, budżet i ryzyko zatwierdza właściciel projektu.

### ADR-001: modularny monolit, jedna aplikacja na klienta

**Kontekst:** wiele małych projektów, nieznane przyszłe domeny. **Decyzja:** jeden deploy, jedna baza, moduły wyznaczane przez procesy biznesowe, standardowy Laravel. **Alternatywy:** zwykły monolit bez granic (najkrótszy start, rosnące sprzężenie); mikroserwisy (izolacja i skalowanie kosztem operacji). **Konsekwencje:** prostota transakcji i releasów; granice trzeba pilnować w review. **Powrót do decyzji:** mierzalna potrzeba niezależnego skalowania lub osobnych zespołów. Szczegóły: [architektura](01-architecture-and-modularity.md).

### ADR-002: Laravel 13, PHP 8.5 i Node 24 LTS jako baseline

**Decyzja kierunkowa zatwierdzona:** dla nowego projektu użyć najnowszego stabilnego patcha Laravel 13, PHP 8.5 i Node 24 LTS oraz jednego silnika DB. Laravel 13 wspiera PHP 8.5, a Node 24 jest linią LTS; PHP 8.3 nie jest preferowanym minimum startera. [Polityka Laravel](https://laravel.com/framework/docs/releases), [Node releases](https://nodejs.org/en/about/previous-releases).

**Trade-off:** najnowszy runtime wymaga kwalifikacji wszystkich paczek, rozszerzeń PHP, obrazów i hostingu na faktycznym lockfile. Nie obniżać całego frameworka dla opcjonalnej paczki; nie przechodzić na Node Current zamiast LTS tylko dla pojedynczego narzędzia. Ostateczne patche przypiąć dopiero po teście resolvera i CI.

### ADR-003: React/Inertia i SSR dla całego serwisu

**Decyzja kierunkowa zatwierdzona:** React + TypeScript + Inertia dla całego serwisu, w tym publicznych stron. Publiczne trasy wymagają SSR, aby pierwszy response zawierał treść i meta dla wyszukiwarek; utrzymujemy osobny proces Node SSR monitorowany i przełączany bez przerwy przez równoległy start nowej instancji przy deployu. **Alternatywy:** Blade dla stron publicznych (mniejszy runtime, ale dwa renderery) albo Filament/Livewire (szybszy CRUD, drugi model UI). **Konsekwencje:** jeden renderer i wspólny system komponentów, ale większy koszt operacyjny Node SSR. Filament nie jest częścią P0.

### ADR-004: PostgreSQL jako domyślna propozycja, MySQL jako wariant awaryjny

Wybrać **jeden** wspierany major dostępny u dostawcy i używać go lokalnie, w CI i na produkcji. Decyzją roboczą jest PostgreSQL, ze względu na integralność danych i przyszłe zapytania; MySQL pozostaje wariantem wyłącznie, jeśli ograniczenia hostingu go wymuszą. Nie utrzymywać podwójnej matrycy DB w P0. Brak multi-tenancy; klienci mają osobne dane, sekrety i wdrożenia.

### ADR-005: bezpieczeństwo wydania egzekwowane poza agentem

**Status: wymaganie użytkownika.** Agent nie zatwierdza własnej pracy, nie merge’uje do chronionego brancha i nie deployuje produkcji. Ręczna akceptacja dotyczy konkretnego artefaktu. Reguły Markdown są instrukcjami, nie kontrolą dostępu. Ochronę zapewniają uprawnienia kont, chronione workflows i infrastruktura. [AI SDLC](04-ai-sdlc.md), [release](06-devops-observability-and-release.md).

### ADR-019: LLM-safe UI contract

**Status: wymaganie użytkownika.** Semantyczne tokeny i typowane prymitywy ograniczają decyzje UI. Bez swobodnych klas i stylów w kodzie aplikacyjnym; niskopoziomowe stylowanie tylko w prymitywach lub audytowalnym wyjątku. Light/dark przez tokeny od P0. Nowy token/wariant wymaga decyzji designowej i wskazania wielokrotnego użycia; agent najpierw proponuje rozszerzenie przed implementacją. Kontrakt jest blokującą bramką CI dla React/TSX/CSS i testów SSR. Szczegóły: [kontrakt UI](05-design-system-and-frontend.md).

### ADR-020: proporcjonalny proces agentów

**Status: wymaganie użytkownika dotyczące proporcjonalności.** FAST domyślnie: jeden agent i cel 5–10 min, bez pełnego workflow. STANDARD: krótki plan i opcjonalny specjalista. HIGH-RISK: jawne zgody i odpowiednie review. Role są dostępne na żądanie; review i deploy człowieka pozostają bramkami niezależnie od trybu. [Konfiguracja Claude/Codex, skille i limity](09-agents-skills-and-workflows.md).

## Granice zakresu i startery

„Kit” to profil zastosowania i zestaw wzorców w jednym repozytorium, nie sześć paczek Composer ani osobnych produktów. W P0 nie tworzymy silnika instalowania modułów.

| Kit | Must have na start — P0 | Opcjonalnie — P1 | Świadomie odłożone — P2 |
| --- | --- | --- | --- |
| Laravel Core | Auth ze starter kitu, MFA/2FA admina, policies, migracje, błędy, logi, Redis + Horizon i scheduler | Integracje, dynamiczny RBAC | Multi-tenancy, event sourcing, mikroserwisy |
| Admin Kit | Shell panelu, CRUD stron i artykułów, stałe role admin/editor, prywatny DAM | Ustawienia, zarządzanie rolami | Uniwersalny generator CRUD i workflow engine |
| Website Kit | Home, strony i artykuły SSR, kontakt, meta, sitemap, redirects | Sekcje, wielojęzyczność, integracja analityki | Własny page builder i rozbudowany CMS |
| App Kit | Wyłącznie wzorzec użycia actions/policies z CRUD-u | Pierwszy rzeczywisty moduł klienta | CRM/ERP „na zapas”, płatności |
| Design System | LLM-safe UI contract: tokeny light/dark, typowane prymitywy, formularz, dialog, tabela, stany i blokujący check CI | Sekcje marketingowe i katalog komponentów | Osobny produkt npm, wielomarkowy edytor |
| AI SDLC | Reguły, specyfikacja, review człowieka, bramki CI/release | Role agentów i raporty automatyczne | Autonomiczne decyzje i produkcja |

P0 strony i artykuły: title, slug, description/excerpt, Tiptap body, stan draft/published, published_at i metadata SEO. Zestaw rich textu jest zamknięty, a wynik renderowania sanitizowany; bez dowolnego HTML i drag-and-drop page buildera. Adminowy DAM przechowuje pliki do 50 MB lokalnie na VM, prywatnie do chwili świadomej publikacji; tylko obrazy otrzymują warianty. Początkowy formularz kontaktowy bez załączników. Uploady użytkowników i płatności są poza P0. Analityka domyślnie wyłączona.

P0 infrastruktury: jedna ekonomiczna VM w regionie UE, Nginx + PHP-FPM, PostgreSQL i Redis/Horizon. GitHub Actions wykonuje CI/CD, a Deployer publikuje katalogowe release’y na VM: zachowuje pięć ostatnich wersji, atomowo przełącza symlink `current`, uruchamia kontrolę gotowości i automatycznie wraca do poprzedniej zgodnej wersji po nieudanych kontrolach w oknie deployu. To zapewnia bezprzerwowe przełączenie kodu aplikacji, lecz nie wysoką dostępność przy awarii pojedynczej VM.

## Kolejność i zależności

1. D0: potwierdzić katalog, profil pierwszego klienta, budżet i właścicieli decyzji.
2. D1: zatwierdzić ADR-y stacku, hostingu, danych i kontroli produkcji.
3. P0-A: szkielet + lokalne środowisko + podstawowe CI; dopiero potem funkcje.
4. P0-B: auth/MFA/policies + wzorcowy CRUD + minimalny design system i Website Kit.
5. P0-C: testy, staging, monitoring, backup/restore, próba odtworzenia i wydania.
6. Bramka pierwszego klienta: review i odbiór operacyjny przez człowieka.
7. P1: tylko moduły wynikające z podpisanego zakresu klienta.
8. P2: uogólnienia po co najmniej dwóch wdrożeniach i pomiarze powtarzalnych problemów.

Zależność krytyczna: dostawca/plan GitHub → realny approval → tożsamości wdrożeniowe → release. Auth/policies → każdy CRUD. Model publikacji → sitemap/cache. Kontrakty danych → komponenty formularzy. Retencja i klasyfikacja danych → monitoring i backup. Szczegółowe zadania i bramki są w [roadmapie](08-implementation-roadmap.md).

## Ryzyka

- Brak repozytorium może oznaczać niewłaściwy katalog; jeśli istnieje aplikacja gdzie indziej, trzeba ponowić analizę przed implementacją.
- Starter może przerodzić się w platformę wewnętrzną. Limit P0: jeden scenariusz biznesowy, brak platformy rozszerzeń; zmiany zakresu wymagają usunięcia innego elementu albo nowego budżetu.
- Pojedyncza VM jest najtańsza, ale stanowi pojedynczy punkt awarii; pełne HA wymaga później drugiej instancji lub platformy zarządzanej.
- SSR wymaga procesu Node i kontroli jego gotowości; błąd SSR nie może ukryć problemu SEO.
- Tabela zgodności nie zastępuje lockfile, skanu podatności ani testu hostingu.
- Kopie startera rozchodzą się. P0: wersja źródłowa i rejestr wdrożeń; P1: cykliczne PR-y aktualizujące. Ekstrakcja wspólnej paczki dopiero po potwierdzeniu stabilnego API.

## Checklista: gotowość dla pierwszego klienta

- [ ] Człowiek zaakceptował scope, ADR-y, budżet operacyjny i odpowiedzialność za support.
- [ ] Projekt odtwarza się z dokumentacji i lockfile; staging oraz produkcja mają oddzielne dane i sekrety.
- [ ] MFA administratora, reset, weryfikacja e-mail, policies i przypadki odmowy przechodzą testy.
- [ ] Pierwszy CRUD i publikacja działają; draft nie wycieka przez URL, API, sitemap ani cache.
- [ ] Formularz kontaktowy obsługuje walidację, spam, awarię wysyłki i ponowienie.
- [ ] CI spełnia progi z dokumentu 03, w tym `ui-contract`, oba motywy i rejestr wyjątków; niezależny człowiek przeczytał diff.
- [ ] Monitor wykrywa awarię; backup odtworzono w izolacji w uzgodnionym RTO/RPO.
- [ ] Ręczny approval technicznie blokuje produkcję; przetestowano odmowę dla konta agenta.
- [ ] Smoke, SEO i manualne a11y zakończone; ustalono retencję i odbiór treści przez klienta.
- [ ] Człowiek wykonał pierwsze wydanie i próbę rollbacku, istnieje kontakt alarmowy.

## Otwarte pytania wymagające decyzji

| ID | Pytanie | Rekomendacja robocza | Kto / kiedy |
| --- | --- | --- | --- |
| Q1 | Czy pusty katalog jest właściwym miejscem, czy istnieje repo do analizy? | Traktować jako greenfield dopiero po potwierdzeniu | Właściciel / D0 |
| Q2 | Jaki jest pierwszy klient i jego najważniejszy proces? | Strona firmowa z prostą edycją stron | Product owner / D0 |
| Q3 | Jaki zakres SSR i które trasy mogą być z niego wyłączone? | SSR dla wszystkich publicznych tras; wyjątki wymagają uzasadnienia SEO | Tech lead / P0-A |
| Q4 | Jaki dostawca VM w UE i jakie parametry hosta? | PostgreSQL na jednej ekonomicznej VM P0 | Właściciel operacji / D1 |
| Q5 | Budżet GitHub i infrastruktury; kto zatwierdza produkcję? | Zewnętrzna wobec agenta bramka z nazwanym człowiekiem | Właściciel / D1 |
| Q6 | RTO/RPO i godziny wsparcia? | Dla pilotażu RPO 24 h, RTO 4 h; dopasować do danych | Biznes / przed stagingiem |
| Q7 | Monitoring SaaS i wysyłka danych poza hosting? | Jeden dostawca po akceptacji retencji, DPA i kosztu | Administrator danych / P0-C |
| Q8 | Rejestracja publiczna, dodatkowe role i uploady użytkowników? | Rejestracja wyłączona; admin/editor i DAM P0; uploady użytkowników później z osobną specyfikacją | Product owner / P0-B |
| Q9 | Jakie patche PHP 8.5/Node 24 LTS i przeglądarki są wspierane? | Najnowsze stabilne patche po teście rozszerzeń i toolchainu | Tech lead / D1 |
| Q10 | Kto utrzymuje kopie startera i płaci za aktualizacje? | Jeden opiekun i lista wersji klientów | Właściciel / przed pierwszym klientem |

## Mapa dokumentacji

- [01 — architektura](01-architecture-and-modularity.md)
- [02 — bezpieczeństwo](02-security-and-access.md)
- [03 — testy i jakość](03-testing-and-quality.md)
- [04 — AI SDLC](04-ai-sdlc.md)
- [05 — frontend](05-design-system-and-frontend.md)
- [06 — DevOps i release](06-devops-observability-and-release.md)
- [07 — decyzje narzędziowe i źródła](07-package-decision-record.md)
- [08 — roadmapa](08-implementation-roadmap.md)
- [09 — agenci, skille i proporcjonalne przepływy Claude/Codex](09-agents-skills-and-workflows.md)
- [10 — lokalne środowisko, zero-downtime, automatyczny rollback i logi](10-local-environment-deployment-and-logs.md)
- [Instrukcja Claude Code](../../CLAUDE.md)
