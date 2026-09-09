# Agenci, skille i przepływy Claude Code / Codex

## Cel

Dobrać koszt procesu do ryzyka zadania. Poprawka na 5–10 minut nie powinna uruchamiać discovery, architekta, kilku reviewerów i dokumentowania releasu. Role to kompetencje dostępne na żądanie, nie obowiązkowa kolejka agentów.

Status: plan konfiguracji i przykłady do przyszłego wdrożenia, 2026-09-09. Nie utworzono aktywnych agentów/skilli, nie instalowano narzędzi ani nie zmieniano ustawień użytkownika. W repo są dokumenty i `.claude/rules`; brak projektowych `.codex` i `.agents`. Polecenie `codex` nie było dostępne w PATH tego środowiska, więc przykłady zweryfikowano dokumentacyjnie, nie uruchomieniem CLI. Nie oznacza to braku funkcji w aplikacji Codex.

## Decyzje i uzasadnienie

### ADR-020: domyślnie jeden agent, specjalista tylko z uzasadnieniem

Status: wymaganie użytkownika dotyczące proporcjonalności; progi poniżej to propozycja operacyjna. Główna sesja jest jednocześnie koordynatorem i wykonawcą. Nie uruchamiać osobnego „router agenta”, żeby zdecydował, jakiego agenta uruchomić. Klasyfikacja ma zająć około 30 sekund i najwyżej jedno zdanie aktualizacji.

Warianty: jeden agent do wszystkiego (najmniejszy narzut, gorsza niezależna analiza trudnego ryzyka); zawsze pełny zespół (duplikacja kontekstu i długi czas); **jeden agent + selektywna delegacja** (rekomendacja). Nie trzeba używać jednocześnie Claude i Codex. Drugi produkt jest alternatywnym wykonawcą lub jednym niezależnym reviewerem, nie dodatkową obowiązkową bramką.

### Tryby pracy

| Tryb | Kiedy | Przepływ i liczba agentów | Budżet roboczy |
| --- | --- | --- | --- |
| FAST — domyślny | Jasna, lokalna poprawka, znany wzorzec; brak zmian wrażliwych | 1 agent: odczyt miejsca → poprawka → adekwatny test/check → raport. Bez delegacji, osobnej spec i ADR | Cel 5–10 min; kontekst wejściowy dobrany do zadania ok. 2–6k tokenów; maks. 1 skill zadaniowy |
| STANDARD | Feature w jednym module, kilka współpracujących elementów lub trudniejszy bug | 1 wykonawca; opcjonalnie 1 specjalista do konkretnego pytania albo review. Plan 3–5 punktów w rozmowie/PR | Cel 15–40 min; ok. 6–15k dobranego kontekstu; maks. 2 skille zadaniowe |
| HIGH-RISK | Migracje, auth, role/policies, sekrety, płatności, release, istotna integralność danych; także nowa architektura | Plan i jawna zgoda na zakres wrażliwy; wykonawca + 1 adekwatny reviewer. Drugi specjalista tylko przy innej niezależnej domenie ryzyka | Osobno uzgodniony budżet; punkt kontrolny co 20–30 min. Do 2 subagentów łącznie, bez kaskadowania |

Liczba plików jest wskazówką, nie klasyfikatorem bezpieczeństwa: jedna zmiana policy to HIGH-RISK, 20 zmian copy może pozostać FAST. Mała nowa decyzja UI wymaga propozycji/akceptacji zgodnie z ADR-019, ale nie pełnego audytu architektury. W razie istotnej niejasności zadać jedno konkretne pytanie; nie zaczynać wywiadu od nowa.

Podane tokeny są **celem wielkości dobranego kontekstu**, nie limitem całego rozliczenia. Koszt obejmuje też historię, narzędzia, reasoning, ponowne wejścia i pracę subagentów. Nie deklarować dokładnego zużycia, jeśli runtime go nie udostępnia. Cele czasowe nie obejmują oczekiwania na człowieka i długiego CI; raportować te czasy oddzielnie.

FAST nie omija required CI, review człowieka przed merge ani ręcznej produkcji. Zlecenie konkretnej drobnej poprawki stanowi akceptację jej celu; nie wymaga ponownego pytania o każdy krok. Wrażliwa zmiana nadal wymaga wyraźnej akceptacji. Nie wykonywać staging/release dla każdego taska: łączyć ukończone zmiany w zaplanowane wydanie.

### Kiedy eskalować i kiedy skończyć

Po 10 minutach FAST lub dwóch nieudanych próbach naprawy tej samej przyczyny: nazwać nowy fakt, który zwiększa zakres. Jeśli przyczyna jest jasna, dokończyć w istniejącym upoważnieniu; jeśli potrzebny większy zakres, decyzja biznesowa lub ryzykowna zmiana, przedstawić konkretną propozycję. Nie uruchamiać automatycznie pięciu agentów. STANDARD po 40 minutach dostaje analogiczny punkt kontrolny.

Koniec zadania: spełnione AC, wymagane kontrole wykonane albo jawnie wskazana blokada, diff gotowy do review. Nie dokładać „przy okazji” audytu SEO, refaktoru i dodatkowej rundy review bez nowego dowodu. Jedna runda review + poprawki + ponowna kontrola zmienionych miejsc; nowe rzeczywiste ryzyko może uzasadnić kolejną rundę, samo poczucie niepewności nie uzasadnia nieskończonej pętli.

## Role i kontrakty odpowiedzialności

Pełne obowiązki security/release pozostają w [04](04-ai-sdlc.md). Każdy delegat dostaje konkretny cel, scope odczytu/zapisu, AC, dowody, budżet i warunek zakończenia. Nie wolno mu delegować dalej.

| Rola / proponowana nazwa            | Włączać gdy                                                  | Wejście → wyjście                                                             | Dostęp i limit                                                                    |
|-------------------------------------|--------------------------------------------------------------|-------------------------------------------------------------------------------|-----------------------------------------------------------------------------------|
| Discovery `foundation-discovery`    | Nie wiadomo, czego klient potrzebuje; nie do jasnego bugfixa | Brief i fakty → maks. 5 pytań/decyzji, AC i non-goals                         | Read-only; bez decyzji za biznes                                                  |
| Architecture `foundation-architect` | Nowa granica modułu, integracja, trwały kontrakt             | AC i odpowiednie ADR → 2 warianty, rekomendacja, ryzyko                       | Read-only; bez implementacji i pełnego skanu repo bez potrzeby                    |
| Backend `foundation-backend`        | Proces Laravel, query, job, błąd backendu                    | AC, pliki, testy → minimalny diff i dowody                                    | Workspace write tylko przy autoryzacji; wrażliwe zmiany po zgodzie                |
| Frontend `foundation-frontend`      | Ekran lub interakcja w istniejącym DS                        | AC, komponenty, makieta → UI zgodne z ADR-019 i test                          | Workspace write; nowe warianty najpierw proponuje                                 |
| QA/security `foundation-reviewer`   | Konkretne ryzyko albo niezależny przegląd STANDARD/HIGH-RISK | AC, diff i raport testów → findings z reprodukcją/plikami                     | Domyślnie read-only; nie poprawia własnych findings i nie zatwierdza za człowieka |
| Release `foundation-release`        | Przygotowanie rzeczywistego wydania                          | Manifest i suma archiwum, CI, staging, migracje → checklisty i plan rollbacku | Read-only do artefaktów; bez credentiali i działań produkcyjnych                  |

Nie trzeba tworzyć sześciu procesów ani nawet sześciu plików w P0. Na start główna sesja realizuje backend/frontend zależnie od taska; skonfigurować jednego `foundation-reviewer`. Pozostałe definicje dodawać po powtarzalnym użyciu. Nazwy nie są mechanizmem izolacji: zakaz zapisu wymaga ograniczenia narzędzi/sandboxa, a zakaz dostępu do danych — tożsamości i uprawnień.

### Delegacja, która ma sens

Przykład: główny agent naprawia frontend, a jeden read-only delegat sprawdza kontrakt backendu. Przykład review: po gotowym diffie reviewer czyta AC i kod, podczas gdy główny agent dopina opis PR. Nie wysyłać review nieukończonego kodu i nie delegować zależnych kroków jako rzekomo równoległych.

Delegat otrzymuje krótki pakiet, nie kopię całej rozmowy: cel 1–2 zdania, dokładne pliki/symbole, maks. kilka AC, ograniczenia, polecenie „bez dalszej delegacji”, wynik do 300 słów + istotne dowody. Główny agent czyta raport i weryfikuje kluczowe wnioski; nie czyta ponownie wszystkich plików tylko dlatego, że analizował je ktoś inny. Równoległe zapisy wyłącznie przy rozłącznych plikach lub osobnych worktrees; jeden właściciel integracji.

## Jak skonfigurować Claude Code

Projektowe subagenty umieszcza się w `.claude/agents/*.md`; `/agents` służy do zarządzania nimi. Definicja ma frontmatter i instrukcje. Ograniczyć narzędzia, a do krótkich zadań ustawić `maxTurns`; ten limit nie jest budżetem tokenów ani gwarancją czasu. Pole `skills` ładuje pełną treść wskazanych skilli, więc nie wpisywać całego katalogu. [Oficjalna dokumentacja subagentów](https://code.claude.com/docs/en/sub-agents).

Przykładowa przyszła definicja `.claude/agents/foundation-reviewer.md`:

```markdown
---
name: foundation-reviewer
description: Wąskie review gotowego diffu na jawne zlecenie; nie do każdej drobnej zmiany.
tools: Read, Glob, Grep
model: inherit
maxTurns: 6
---
Sprawdź AC, wskazany diff i sąsiednie kontrakty.
Przed pracą przeczytaj ograniczenia projektu w CLAUDE.md.
Nie zmieniaj plików, nie deleguj i nie inicjuj releasu.
Zwróć do 300 słów: finding, plik/symbol, skutek, reprodukcja.
Jeśli brak findings, napisz to wraz z zakresem i ograniczeniami.
Nie zgaduj wyników testów; polegaj na dostarczonych dowodach.
```

Ten wariant nie ma Bash, więc nie uruchamia `git diff` i testów; rodzic przekazuje patch lub plik raportu. Nie dodawać Bash jako „read-only”, bo shell może zapisywać. Przy potrzebie testów użyć wydzielonego środowiska i osobnego zakresu uprawnień.

Skill/komenda: `.claude/skills/foundation-fast/SKILL.md`, wywołanie `/foundation-fast <task>`. `disable-model-invocation: true` daje świadome wywołanie użytkownika; nie dodawać `context: fork` do FAST. Obecne `.claude/rules` pozostają stałymi zasadami. [Claude skills](https://code.claude.com/docs/en/skills).

Przykładowy frontmatter komendy FAST:

```yaml
---
name: foundation-fast
description: Lokalna poprawka według istniejącego wzorca, bez zespołu agentów.
disable-model-invocation: true
---
```

Po frontmatter umieścić kontrakt FAST opisany niżej. Dla pozostałych ról skopiować strukturę definicji reviewera i zmienić nazwę, opis, scope oraz narzędzia zgodnie z tabelą ról. Nie dawać dostępu do edycji discovery/architecture/release. Nie ustawiać trybu pomijającego permissions.

## Jak skonfigurować Codex

Projektowe instrukcje to `AGENTS.md`; powinien zawierać krótki routing FAST/STANDARD/HIGH-RISK i wskazanie dokumentów, zamiast automatycznie wczytywać całą dokumentację. Zachować istniejące instrukcje użytkownika, w tym RTK. `CLAUDE.md` nie zastępuje automatycznie instrukcji Codex. [AGENTS.md](https://learn.chatgpt.com/docs/agent-configuration/agents-md).

Projektowe custom agents definiuje się w `.codex/agents/*.toml`. Delegację zlecać jawnie, podając rolę i ograniczony cel; bez niej działa główna sesja. Dostępność sprawdzić w używanym kliencie. [Oficjalna dokumentacja subagentów](https://learn.chatgpt.com/docs/agent-configuration/subagents).

Przykładowy przyszły `.codex/agents/foundation-reviewer.toml`:

```toml
name = "foundation-reviewer"
description = "Wąskie review gotowego diffu na jawne zlecenie."
sandbox_mode = "read-only"
developer_instructions = """
Sprawdź AC, wskazany diff i sąsiednie kontrakty.
Przestrzegaj ograniczeń projektu w AGENTS.md.
Nie zapisuj plików, nie deleguj, nie merge'uj i nie inicjuj produkcji.
Zwróć do 300 słów: finding, plik/symbol, skutek i reprodukcja.
Nie deklaruj uruchomionych testów bez dowodów.
"""
```

Nie wpisano modelu: ustawienie dziedziczy konfigurację nadrzędną. Model można dopasować później do pomiaru jakości/czasu, bez utrwalania przypadkowego aliasu w starterze. Read-only filesystem nie wyłącza automatycznie zdalnych narzędzi zapisu — konta/MCP muszą mieć odpowiednio ograniczony dostęp.

Skille projektowe Codex: `.agents/skills/<name>/SKILL.md`; wywołanie `$foundation-fast` tam, gdzie klient obsługuje tę składnię, albo wybór z selektora skilli. Wymagane metadane to `name` i `description`. Dla ręcznego uruchamiania ustawić w `agents/openai.yaml` danego skillu:

```yaml
policy:
  allow_implicit_invocation: false
```

To metadane **skillu**, nie definicja subagenta. [Codex skills](https://learn.chatgpt.com/docs/build-skills).

Nie projektować komendy `/agents` w Codex na podstawie Claude. Gdy klient nie obsługuje custom agents, użyć tej samej roli jako jawnego promptu w jednej sesji; dla niezależnego review człowiek otwiera osobną sesję ze spec i diffem. Nie tworzyć automatycznie nowych zadań w sidebarze dla każdego kroku.

## Wartościowe skille — mały zestaw

Skill opisuje metodę wykonania powtarzalnego zadania; agent opisuje rolę i uprawnienia. Skill nie powinien sam wywoływać całego zespołu. Rekomendowane poniżej `foundation-*` są nazwami planowanych lokalnych skilli, nie paczkami już dostępnymi do instalacji.

| Skill                     | Zakres i wynik                                         | Trigger / nie używać                                                     |
|---------------------------|--------------------------------------------------------|--------------------------------------------------------------------------|
| `foundation-fast`         | Lokalna poprawka, cel i dowód; bez osobnej spec        | Jawna komenda lub domyślny routing; nie dla wrażliwej zmiany             |
| `foundation-verify`       | Dobór realnych komend z repo do diffu i raport wyników | Gdy dobór testów się powtarza; nie uruchamia całego audytu               |
| `foundation-ui`           | Wyszukanie komponentu, zamknięte API i ui-contract     | Zmiana UI; bez propozycji nowej palety lub lokalnych klas                |
| `foundation-review`       | Błędy poprawności/security w wskazanym diffie          | Jawne review, domyślnie bez edycji; nie do każdej zmiany copy            |
| `foundation-design`       | Krótka propozycja rozszerzenia DS lub architektury     | Brakuje zaakceptowanego kontraktu; nie przy użyciu istniejącego wariantu |
| `foundation-release-plan` | Zebranie dowodów i plan wydania                        | Tylko ręcznie przy release; nigdy wykonanie produkcji                    |

P0: `foundation-fast` i `foundation-ui`; `verify` może początkowo być krótką sekcją FAST, a reviewer korzystać z własnej instrukcji. Pozostałe skille dopiero po kilku powtórzeniach pracy. Docelowo 4–6 krótkich skilli, nie kilkadziesiąt automatycznych triggerów.

### Co zrobić z istniejącymi skillami użytkownika

W tej sesji odczytano `laravel-senior-review`, `ux-ui-review` i `brainstorming`; `architecture-review` odczytano przy pierwotnym planie. Są osobistymi zasobami poza repo, nie częścią startera.

- `laravel-senior-review`: wartościowa checklista backendu. Stosować wąsko do diffu; przed współdzieleniem usunąć założenia e-commerce/Next.js niepasujące do tego projektu i ograniczyć raport do rzeczywistych findings.
- `architecture-review`: tylko dla granic modułów/istotnego refaktoru. Nie jako obowiązkowa bramka zmiany kontrolera.
- `ux-ui-review`: sensowny przegląd formularza i dostępności; dopasować do ADR-019 i nie wymagać przykładowej implementacji przy każdym read-only review.
- `brainstorming`: odczytana wersja narzuca pełny proces nawet prostym zadaniom. To koliduje z celem FAST. Nie włączać jej jako domyślnej zależności startera; przygotować w przyszłości wąski `foundation-design` zamiast kopiować te instrukcje.
- `agent-browser`, `shadcn`, `github-actions-docs`, `openai-docs`: kandydaci do użycia na żądanie dla odpowiedniego zadania; pełną treść, uprawnienia i koszt sprawdzić przed dodaniem do wspólnego startera. Nie instalować kompletu na podstawie samych nazw.

Nie modyfikowano ani nie wyłączano osobistych skilli. Jeśli globalny skill nadal narzuca rozbudowany proces, trzeba świadomie zmienić jego trigger/ustawienie lub zastąpić go po decyzji użytkownika; sam nowy routing nie gwarantuje, że globalna instrukcja przestanie się ładować. Instrukcje systemowe, bezpieczeństwa i ograniczenia uprawnień pozostają nadrzędne.

### Treść wspólnego skillu FAST

Poniższy krótki kontrakt stanowi źródło dla obu adapterów:

> Wykonaj jasno opisane zadanie według istniejącego wzorca. Najpierw sprawdź lokalne instrukcje i miejsce zmiany. Jeśli dotyczy auth, ról/policies, migracji, sekretów, płatności lub produkcji, przejdź do bramki ryzyka. W pozostałych przypadkach pracuj samodzielnie, bez subagentów i nowego dokumentu planu. Czytaj tylko potrzebne pliki; nie wczytuj wszystkich docs i skilli. Zmień minimalny zakres, uruchom kontrole adekwatne do diffu i wymagane przez repo. UI wyłącznie przez istniejące API DS; brakujący wariant najpierw zaproponuj. Po dwóch nieudanych próbach wyjaśnij nową przeszkodę zamiast powtarzać ten sam cykl. Zakończ wynikiem, weryfikacją i ograniczeniem. Nie merge'uj ani nie wykonuj produkcji.

Utrzymywać jedno źródło treści procedur w repo, a adaptery Claude/Codex mają jedynie własne metadane i odniesienie do tej treści. Przy przyszłym generowaniu adapterów CI sprawdza zgodność źródła i obu kopii. Nie zakładać, że jeden produkt automatycznie wykryje katalog skilli drugiego; nie powielać pełnych dokumentów w obu SKILL.md. Krótki body skillu 30–60 linii, odnośniki do dłuższych materiałów tylko na żądanie.

## Polecenia użytkownika i oczekiwany przepływ

Poniższe komendy skilli będą działać **po ich utworzeniu**. Już teraz można wkleić treść jako zwykły prompt.

| Zadanie       | Claude Code                                                                                                                                           | Codex                                                                                              | Oczekiwany koszt procesu                                                |
|---------------|-------------------------------------------------------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------|
| Mała poprawka | `/foundation-fast Popraw label przycisku zapisu, użyj istniejącego komponentu.`                                                                       | `$foundation-fast Popraw label przycisku zapisu, użyj istniejącego komponentu.`                    | Jeden agent, sprawdzenie miejsca i ui-contract, bez planu/review agenta |
| Bug backendu  | `STANDARD: napraw błąd filtra statusu. Najpierw reprodukcja, potem minimalna poprawka i test. Bez delegacji, chyba że pojawi się niezależny problem.` | Ten sam prompt                                                                                     | Jeden wykonawca i celowany test                                         |
| Review        | `Użyj foundation-reviewer tylko do kontroli uprawnień w tym diffie. Bez zmian, do 300 słów.`                                                          | `Deleguj do foundation-reviewer tylko kontrolę uprawnień w tym diffie. Bez zmian, do 300 słów.`    | Jeden ograniczony delegat, nie przegląd całego repo                     |
| Feature UI    | `STANDARD: dodaj filtr przez istniejące API DS. Brakujący wariant najpierw zaproponuj.`                                                               | Ten sam prompt                                                                                     | Krótki plan; bez projektowania systemu na nowo                          |
| Migracja      | `HIGH-RISK: zaproponuj migrację i plan kompatybilności. Nie zmieniaj schematu przed moją akceptacją.`                                                 | Ten sam prompt                                                                                     | Analiza i jawna zgoda; następnie implementacja i odpowiednie review     |
| Release       | `/foundation-release-plan Przygotuj checklistę dla wskazanego manifestu release’u, bez wdrożenia.`                                                    | `$foundation-release-plan Przygotuj checklistę dla wskazanego manifestu release’u, bez wdrożenia.` | Jedna procedura dla wydania, nie dla każdej poprawki                    |

## Kontrola kosztu, jakości i konfiguracji

- FAST: żadnego przeglądu wszystkich dokumentów, nowych tasków, dodatkowego worktree dla samego planu ani „pełnego researchu” znanego lokalnego wzorca. Browsing tylko gdy wymaga go pytanie, niepewne API/wersja lub obowiązująca instrukcja.
- Ustawienia modeli nie zmieniają się automatycznie przy każdym tasku. Używać ustawienia użytkownika; osobny szybszy profil można wybrać po porównaniu jakości na własnych zadaniach. Maksymalny reasoning nie jest obowiązkowy do copy/edit.
- Każda delegacja ma jeden powód: niezależny wynik albo skrócenie realnej ścieżki pracy. Limit polityki: FAST 0, STANDARD 1, HIGH-RISK 2 subagentów łącznie, głębokość 1. To limity projektu, nie gwarantowane uniwersalne parametry obu produktów.
- Nie przekazywać całej historii delegatom, nie preładowywać wszystkich skills. Nie przekładać zadania Claude→Codex→Claude tylko po to, żeby mieć trzy opinie.
- Nie przerywać wymaganych testów, żeby zmieścić się w czasie. Po ich zaliczeniu nie powtarzać bez zmian. Długi CI działa jako bramka PR, nie powód wielokrotnego odpytywania i ponownej analizy.
- Limity opisane w promptach są miękkie. `maxTurns` Claude ogranicza iteracje subagenta, nie opłatę. Twardy budżet/timeout tylko przez mechanizm faktycznie wspierany przez runner; jeśli brak, monitorować czas i raportować ograniczenie, nie wymyślać pól config.

Pilotaż po konfiguracji: 10 drobnych, 5 średnich i 2 wrażliwe zadania syntetyczne. Mierzyć czas pracy, czas oczekiwania, liczbę agentów, odczytanych skilli, iteracji naprawy oraz faktyczne usage jeśli dostępne. Cel FAST: mediana ≤10 min, zero delegacji, brak regresji; HIGH-RISK: zero pominiętych zgód. Sprawdzić osobno w Claude i Codex: wykrywanie roli/skillu, odmowę zapisu reviewera, brak auto-uruchomienia ręcznych workflows, trigger ryzyka i brak obejścia DS. Nie uznawać „ładnego raportu” za test konfiguracji.

## Ryzyka

Za dużo globalnych skilli, zbyt szerokie opisy triggerów i powielone instructions zwiększają koszt każdego zadania. Niezależne review AI może mieć te same błędy co wykonawca. Limity tokenów w instrukcji nie gwarantują limitu rachunku. Brak aktywnej konfiguracji w tym repo oznacza, że dzisiejsze przykłady nie zostały przetestowane wykonawczo. Szybkość nie usprawiedliwia pominięcia security, ui-contract ani review człowieka.

## Checklista

- [ ] FAST jest domyślny i nie uruchamia agentów automatycznie.
- [ ] Role mają wąski scope, wejście/wyjście i zakaz dalszej delegacji.
- [ ] Drobny task nie wymaga osobnego dokumentu spec, ADR i release planu.
- [ ] Projektowe skille nie dziedziczą pełnego workflow z globalnego brainstorming.
- [ ] Reviewer ma technicznie ograniczone narzędzia i nie posiada prod credentiali.
- [ ] Adaptery obu produktów korzystają ze wspólnych procedur i przechodzą pilotaż.
- [ ] Budżety są mierzone, a brak danych o usage jawny.

## Otwarte pytania

Przed aktywacją wskazać właściciela review/DS, docelowe wersje klientów Claude/Codex oraz zdecydować, czy adaptować osobiste skille do repo czy utrzymywać nowe krótkie `foundation-*`. Domyślna rekomendacja: dwa projektowe skille + jeden reviewer; resztę dodawać na podstawie realnej pracy. Nie wymaga to wyboru wieloagentowego frameworka ani używania obu produktów do każdego taska.
