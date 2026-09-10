# Bezpieczeństwo i autoryzacja

Glob: `app/Http/Middleware/**`, `app/Policies/**`, `app/Actions/Fortify/**`, `app/Models/User.php`, `app/Providers/FortifyServiceProvider.php`, `config/auth.php`, `config/fortify.php`, `routes/web.php`, `routes/settings.php`

## Klasyfikacja ryzyka

- Zmiany auth, ról/policies, migracji, sekretów, płatności i security policies to **HIGH-RISK**. Wymagają wyraźnej akceptacji człowieka na konkretny zakres przed implementacją.
- Przygotuj wcześniej reviewowalną propozycję i listę skutków.

## Zasady autoryzacji

- Default deny. Policies na backendzie dla każdej operacji. UI otrzymuje flagi `can`, ale backend zawsze sprawdza dostęp.
- Minimalne pola payloadu. Identyfikatory nie stanowią zabezpieczenia przed IDOR — stosuj scope zapytań.
- FormRequest nie zastępuje policy. Policy nie zastępuje walidacji.
- Role statyczne P0: admin i editor. Dynamiczny RBAC (Spatie Permission) dopiero przy potwierdzonej potrzebie.

## Auth i sesje

- Fortify: login, reset, e-mail verification, TOTP. Rejestracja publiczna wyłączona.
- MFA administratora obowiązkowe. Middleware blokuje panel do potwierdzenia konfiguracji MFA.
- Regeneracja ID sesji przy loginie; unieważnienie sesji i odświeżenie CSRF po logout.
- Cookies sesyjne: Secure, HttpOnly, SameSite=Lax.
- Nie implementuj własnej kryptografii.

## Dane wrażliwe

- Sekrety nie trafiają do Git, logów, buildu, odpowiedzi ani do zewnętrznych narzędzi/delegatów.
- Używaj syntetycznych reprodukcji i danych. `#[SensitiveParameter]` na metodach z hasłami/tokenami.
- Logi, błędy i props nie zawierają sekretów/PII. Stack trace tylko w chronionym monitoringu.
- `.env` nie trafia do Git, obrazu, build cache ani do agenta. `.env.example` zawiera jedynie nazwy i fikcyjne wartości.

## Walidacja i wejście

- CSRF na wszystkich stanowych operacjach web. Brak mutacji przez GET.
- Rich text: zamknięty schema (Tiptap), backend waliduje strukturę i sanitizuje HTML. Zakaz dowolnego HTML/JS.
- SQL parametryzowany. Masowe przypisanie wyłącznie z pól dopuszczonych.

## Testy bezpieczeństwa

- Każdy uprzywilejowany endpoint ma test odmowy (403/404) sprawdzający brak mutacji w DB/mail/queue.
- Testy: konto nieweryfikowane, wyłączone, brak MFA, błędny TOTP, zużyty recovery code, rate limits, próby enumeracji.
- Zmiana ID w URL i body; role nie mogą wejść przez mass assignment.
