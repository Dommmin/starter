# Bezpieczeństwo i dostęp

## Cel

Zapewnić sprawdzalny zestaw kontroli dla małej aplikacji klienta. To proponowany threat model, nie wynik pentestu istniejącej aplikacji. Brak kodu i konfiguracji oznacza brak podstaw do deklarowania zgodności lub bezpieczeństwa wdrożenia.

## Decyzje i uzasadnienie

### ADR-008: sesja serwerowa, Fortify, MFA administratora

Status: proponowany; obowiązkowa akceptacja człowieka dla zmian bezpieczeństwa wynika z wymagań użytkownika. E-mail/hasło, reset i weryfikacja e-mail z oficjalnego scaffoldu; TOTP i recovery codes dla admina. Rejestracja publiczna wyłączona. Dodanie admina przez kontrolowaną procedurę z jednorazowym zaproszeniem lub ustanowieniem hasła; bez hasła w seederze, logach i PR. MFA nie może być wyłącznie opcją ukrytą w ustawieniach: middleware blokuje panel do potwierdzenia konfiguracji, a dostępne są jedynie enrollment/logout/recovery. [Fortify](https://laravel.com/framework/docs/13.x/fortify).

Alternatywy: zewnętrzny IdP (mniej odpowiedzialności za auth, koszt i zależność); passkeys (odporność na phishing, proces odzyskiwania i urządzenia); magic link (zależność od skrzynki); OTP e-mail/SMS (limity, koszt, phishing). Social login wyłącznie po analizie potrzeby; account linking wymaga potwierdzenia istniejącej tożsamości, nie samej zgodności adresu. TOTP nie jest odporny na phishing, dlatego nie traktować go jako końca rozwoju auth.

### ADR-009: minimalne dane, jawna autoryzacja i audyt

Status: proponowany. Uprawnienia poprzez Laravel policies, default deny, oddzielne operacje publikacji i zarządzania kontami. Role statyczne P0; Permission dopiero dla dynamicznego RBAC. Zmiana uprawnień od razu unieważnia odpowiedni cache; odebranie dostępu nie czeka na ponowny login. Identyfikatory nie stanowią zabezpieczenia przed IDOR. Administrator nie dostaje automatycznie dostępu do sekretów infrastruktury.

## Threat model

Zasoby: konta i sesje, treści przed publikacją, dane kontaktowe, przyszłe dokumenty klienta, backupy, obrazy, sekrety deployu i dostępność. Aktorzy: gość, bot, editor, admin, developer, agent AI, dostawca, atakujący z przejętym kontem lub zależnością. Granice zaufania: Internet → proxy → Laravel → DB/queue/storage; aplikacja → mail/monitoring; PR → CI → GHCR → hosting; dokumenty i wyniki narzędzi → agent. Osobne środowiska i osobne projekty klientów nie współdzielą credentiali.

| Zagrożenie / ścieżka | Priorytet | Kontrola | Dowód i właściciel |
| --- | --- | --- | --- |
| Editor zmienia ID i publikuje cudzy/chroniony zasób | P0 | Policy, ograniczony query scope, jawne pola zapisu | Test 403/404 oraz brak zmiany DB; backend reviewer |
| Bot przejmuje konto, resetuje hasło lub brute-force TOTP | P0 | Limity, ogólne odpowiedzi, MFA, alerty | Feature/E2E reset/MFA/429; security reviewer |
| Treść strony/formularza uruchamia skrypt | P0 | Sanitizer rich textu, zamknięty schema Tiptap, CSP | Payload XSS nie wykonuje się; frontend reviewer |
| Zgłoszenia zalewają skrzynkę/kolejkę | P0 | Limity per IP i globalne, deduplikacja, rozmiar payloadu | Test burst, metryka backlog; operator |
| Agent/PR przejmuje sekret produkcji | P0 | Brak credentiali u agenta/PR, chronione workflows, approval | Próba deployu agenta odrzucona; właściciel GitHub |
| Pakiet/skrypt instalacyjny lub obraz jest złośliwy | P0 | Pinning, review scripts/plugins, audyty, archiwum z manifestem i sumą SHA-256 | Raport supply-chain; reviewer CI |
| Uszkodzona migracja lub awaria DB niszczy dane | P0 | Expand/contract, backup poza hostem, restore drill | Odtworzenie i pomiar RTO/RPO; operator |
| Plik polyglot/SVG/PDF/ZIP wykonuje kod lub wyczerpuje zasoby | Przed uploadem | Kwarantanna, allowlista, izolowane konwersje i skan | Test pliku niebezpiecznego, timeoutu skanera; security |
| Webhook odtworzony lub import URL prowadzi do SSRF | Przed integracją | Podpis, deduplikacja, ograniczenia egress | Zły podpis/replay/prywatny IP odrzucony; backend |
| Log, backup lub zewnętrzny AI ujawnia PII | P0 | Redakcja, szyfrowanie, retencja, minimalny dostęp | Przegląd próbek i próbne usuwanie; administrator danych |

## Mapowanie OWASP Top 10

Stosujemy aktualną edycję [OWASP Top 10:2025](https://owasp.org/Top10/2025/). Poniższe kontrole są projektem dla tego startera, nie certyfikacją OWASP.

| Kategoria | Kontrola w tym planie |
| --- | --- |
| A01 — kontrola dostępu | Policies, negatywna macierz ról, scope zapytań i plików; ochrona SSRF |
| A02 — konfiguracja | Debug off, HTTPS, prywatne DB, brak otwartych dashboardów, bezpieczne cookies |
| A03 — łańcuch dostaw | Lockfile, audyty, przegląd plugins/scripts, przypięte actions i obrazy |
| A04 — kryptografia | TLS, bezpieczne haszowanie haseł, osobne klucze i kopie szyfrowane |
| A05 — injection | Parametryzacja SQL, allowlista sortowania, escaping, brak dowolnych poleceń |
| A06 — projekt | Threat model i analiza nadużyć dla każdego nowego procesu |
| A07 — auth | MFA, sesje, reset, rate limits, anti-enumeration |
| A08 — integralność | Podpis webhooka/artefaktu, deduplikacja, kontrola importów |
| A09 — logi i alerty | Audit, redakcja, alarm i przypisany operator |
| A10 — sytuacje wyjątkowe | Fail closed, timeouts, retry z limitem, transakcje i odzyskiwanie |

## Auth, sesje i urządzenia

- Hasła: proponowane minimum 15 znaków, maksymalnie co najmniej 64, menedżery haseł i wklejanie dozwolone. Nie wymuszać sztucznych cyklicznych zmian; zmiana przy kompromitacji. Używać hashera Laravel, parametry sprawdzić pomiarem obciążenia. Nie implementować własnej kryptografii.
- Rate limiting startowo: login 5 prób/min dla kombinacji znormalizowany identyfikator + IP oraz limit IP; reset 3/15 min dla identyfikatora i limit globalny; TOTP 5/min dla wyzwania/sesji; kontakt 5/10 min/IP plus budżet globalny. To propozycje do testu NAT/DoS, nie domyślne wartości Laravel.
- Unikać trwałej blokady po kilku próbach, bo umożliwia DoS na cudze konto. Stosować narastające opóźnienie i czasowe ograniczenia; ręczne zawieszenie konta osobną operacją z audytem.
- Reset/rejestracja zwracają ogólny komunikat i zbliżone zachowanie czasowe dla istniejącego i nieistniejącego adresu. Dostarczenie asynchroniczne nie powinno ujawniać istnienia konta. Token resetu jednorazowy, wygasa; po resecie unieważnienie pozostałych sesji.
- TOTP aktywne dopiero po weryfikacji kodu; recovery code jednorazowy i chroniony na dysku zgodnie z mechanizmem Fortify. Brak kodów w logach, monitoringu i analityce. Zmiana MFA, hasła, e-maila i recovery codes wymaga świeżego potwierdzenia tożsamości. Awaryjne odzyskanie admina przez nazwanych ludzi, z weryfikacją i audytem.
- Regeneracja ID sesji przy loginie; unieważnienie sesji i odświeżenie CSRF po logout. Startowo idle admina 30 min, maksymalna sesja 12 h, brak remember-me dla admina. UI listy urządzeń P1; możliwość centralnego odebrania wszystkich sesji P0. Opis urządzenia i IP orientacyjny, z minimalną retencją.
- Cookies sesyjne: Secure, HttpOnly, SameSite=Lax, możliwie host-only. Token cookie wymagający odczytu JS nie dostaje mechanicznie HttpOnly. Każda integracja cross-site wymaga osobnej analizy; CORS domyślnie bez dodatkowych originów.

## Web, pliki i sekrety

CSRF na wszystkich stanowych operacjach web, także uploadach; brak mutacji przez GET. Webhook może mieć wyłączenie CSRF wyłącznie na konkretnej trasie i z własną weryfikacją podpisu. Rich text P0 korzysta z zamkniętego schema Tiptap; backend waliduje strukturę i sanitizuje HTML przez zatwierdzony sanitizer z allowlistą elementów, atrybutów i protokołów URL. Zakaz dowolnego HTML/JS z CMS. SQL parametryzowany; nazwy sortowania i kolumn z allowlisty. Masowe przypisanie wyłącznie z pól dopuszczonych, nigdy `request()->all()` dla uprzywilejowanych modeli.

CSP: zacząć od pomiaru Report-Only na stagingu, przejść do enforce przed produkcją. Własne originy, nonce/hash dla wymaganych skryptów, `object-src 'none'`, `base-uri 'self'`, `frame-ancestors 'none'`, restrykcyjne form-action i connect-src. Nie dodawać globalnego unsafe-inline/unsafe-eval, żeby uciszyć raport. Nagłówki: nosniff, Referrer-Policy, Permissions-Policy dopasowane do funkcji. HSTS dopiero po gotowym TLS; includeSubDomains/preload tylko przy kontroli wszystkich subdomen. Zaufane proxy/hosty jawne, aby nie podrobić adresu resetu lub rate limitu.

P0 obejmuje wyłącznie upload do adminowego DAM. Każdy plik ma limit 50 MB, uprawnienie do uploadu i pobrania, limit requestu zgodny w proxy/PHP/aplikacji, losową nazwę oraz kontrolę MIME po zawartości, rozszerzeniu i — gdy dotyczy — dekoderze. Plik jest prywatny: kwarantanna → skan antymalware z aktualnymi sygnaturami → status clean albo rejected. Awaria lub brak skanera pozostawia plik niedostępny. Obrazy dostają asynchroniczne warianty po usunięciu EXIF; PDF/Office/archiwa, SVG i inne ryzykowne formaty nie są automatycznie previewowane ani konwertowane bez osobnej, izolowanej ścieżki i limitów CPU/RAM/czasu. Pliki wykonywalne i HTML nie stają się publiczne. Uploady zwykłych użytkowników są poza P0. [OWASP File Upload](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html).

Pobrania prywatne przez policy i krótko ważny signed URL; publiczne media z osobnego origin bez cookies sesji, poprawny Content-Type, dokumenty jako attachment. Usunięcie rekordów sprząta oryginały i warianty asynchronicznie; retencja kwarantanny np. 24 h i alert. Limity przestrzeni per klient.

`.env` nie trafia do Git, obrazu, build cache ani agenta. `.env.example` zawiera jedynie nazwy i fikcyjne wartości. Sekrety w zarządzanym magazynie hostingu, oddzielne dla środowisk. Runtime DB bez DDL; migrator osobną tożsamością. Klucze do backupu niezależne od APP_KEY. Rotacja APP_KEY ma plan: zabezpieczenie poprzednich kluczy, obsługa starego szyfrogramu, re-encryption, test odczytu i unieważnienie sesji według potrzeby; nie wykonywać `key:generate` podczas zwykłego deployu. Rotacja po wycieku obejmuje wszystkie kopie i systemy zależne.

## PII, RODO i audit

Projekt techniczny wspiera minimalizację, eksport i usuwanie, ale cel i podstawę przetwarzania ustala administrator danych. Przed klientem: rejestr kategorii danych, procesorzy i umowy, regiony, retencja, obsługa żądań i incydentów. [Tekst rozporządzenia](https://eur-lex.europa.eu/eli/reg/2016/679/oj).

Propozycje retencji do zatwierdzenia, nie wymogi prawne: zgłoszenia kontaktowe 90 dni po zamknięciu, logi techniczne 14 dni, audit admina 180 dni, backupy 30 dni. Ograniczyć adresy IP i treść pól w logach. Eksport po potwierdzeniu tożsamości, przez prywatny plik o krótkim TTL; usunięcie uwzględnia relacje, media, indeksy, cache i procesory. Legal hold obsługiwany jawnie. Backupy wygasają wg retencji; po restore należy ponownie zastosować rejestr żądań usunięcia, aby nie przywrócić usuniętych danych do działania.

Audit P0: logowanie/odmowy, zmiana auth/MFA, nadanie/odebranie dostępu, publikacja/usunięcie strony, operacje administracyjne. Lista dopuszczonych pól before/after; nigdy hasła, tokeny, pełne formularze kontaktowe. Zapis krytycznej mutacji i audytu w jednej transakcji; audyty nieudanych prób osobno. Aplikacja nie oferuje edycji audytu. Activitylog sam nie gwarantuje niezmienności wobec administratora DB — jeśli klient wymaga odporności na manipulację, P1 obejmuje kopię append-only poza aplikacją i ograniczenia tożsamości DB.

## GitHub Organization, Actions i GHCR

Organizacja: MFA, minimalne role, przegląd członków i integracji, dwóch nazwanych opiekunów odzyskiwania dostępu. Chroniony główny branch: PR, required checks, review człowieka, ponowne review po istotnej zmianie, brak force push/deletion; ochrona workflow i CODEOWNERS przez uprawnienia i review. Agent bez bypass i admina. Deploy keys tylko jeśli niezbędne, per repo i read-only; nie są tokenem do pobierania GHCR.

CI dla PR bez sekretów produkcji i write tokena; brak uruchamiania niezaufanego head PR w uprzywilejowanym `pull_request_target`. Actions przypięte do pełnych SHA; input PR nigdy wstawiany jako kod shell. P0: archiwum kodu/vendor/bundle z manifestem, skanem i sumą SHA-256; publikacja tylko po zaufanym merge, hosting pobiera read-only. Usługi natywne z minimalnymi uprawnieniami; GHCR i obrazy nie należą do P0. OIDC tam, gdzie dostawca wspiera, z ograniczeniem repo/ref/environment; w przeciwnym razie osobny ograniczony i rotowany credential. [GitHub secure use](https://docs.github.com/en/actions/reference/security/secure-use).

Production environment musi wymagać człowieka, chronionego ref i ujawniać credentials dopiero po approval. **Sprawdzić plan GitHub:** required reviewers nie są dostępni dla każdego prywatnego repo. Jeśli plan nie wspiera tej kontroli, produkcja pozostaje manualnym wdrożeniem człowieka przez osobną tożsamość hostingu albo niezależną bramką dostawcy. `workflow_dispatch` sam nie jest approval. [Ograniczenia environments](https://docs.github.com/en/actions/how-tos/deploy/configure-and-manage-deployments/manage-environments).

## Ryzyka

Pozostają phishing TOTP, nadużycie konta admina, podatności nieznane skanerom i ryzyko dostawcy. Automatyczne testy nie oceniają podstaw prawnych, sensu uprawnień ani całego threat modelu. Zmiany migracji, płatności, ról, sekretów i policies bezpieczeństwa wymagają wyraźnej, zapisanej akceptacji zakresu przez człowieka przed zmianą.

## Checklista

Automatycznie w CI:

- [ ] Testy auth/MFA/IDOR/CSRF/walidacji i sanitizacji wejścia.
- [ ] Skan sekretów, zależności PHP/JS i finalnego obrazu; brak niezaakceptowanych high/critical.
- [ ] Testy braku prywatnych pól w props, logach, cache i błędach.
- [ ] Testy uploadów/webhooków jeśli funkcja jest aktywna.

Ręcznie przed produkcją:

- [ ] Review threat modelu, ról, odzyskiwania MFA i konfiguracji hostingu.
- [ ] Sprawdzenie faktycznej ochrony branch/environment i odmowy uprawnień agenta.
- [ ] Test restore, próbki logów, e-mail resetu, cookies i nagłówki.
- [ ] Retencja, procesorzy, zgody analityki i procedura żądań użytkowników zaakceptowane.
- [ ] Odbiór bezpieczeństwa przez wskazanego człowieka zapisany przy release.

## Otwarte pytania

Jaki model danych klienta i ich wrażliwość? Kto zatwierdza role i odzyskuje MFA? Czy potrzebne są passkeys/SSO? Jaka retencja i region procesorów? Jaki plan GitHub i kto ma uprawnienia do produkcji? Szczegółowe wartości limitów wymagają pomiaru na docelowym ruchu.
