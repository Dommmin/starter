# Reguły projektu — indeks

Przed edycją lub planowaniem zmian agent MUSI:

1. Otworzyć ten plik i zidentyfikować wszystkie reguły, których globy pasują do zmienianych ścieżek.
2. Przeczytać każdy pasujący plik reguł w całości.
3. W razie wątpliwości uruchomić `grep -rin 'słowo_kluczowe' .ai/rules`, aby złapać kontekst pominięty przez same globy.

Nie pisz kodu, dopóki nie przeczytałeś i nie stosujesz wszystkich pasujących reguł.

## Mapowanie globów → pliki reguł

| Glob                                                       | Plik reguł                                        |
| ---------------------------------------------------------- | ------------------------------------------------- |
| `app/**`, `routes/**`, `database/**`, `config/**`          | [backend.md](backend.md)                          |
| `resources/js/**`, `resources/css/**`                      | [frontend.md](frontend.md)                        |
| `tests/**`                                                 | [testing.md](testing.md)                          |
| `app/Http/Middleware/**`, `app/Policies/**`, `app/Actions/Fortify/**`, `app/Models/User.php`, `app/Providers/FortifyServiceProvider.php`, `config/auth.php`, `config/fortify.php`, `routes/web.php`, `routes/settings.php` | [security-auth.md](security-auth.md) |
| `lefthook.yml`, `commitlint.config.mjs`, `.githooks/**`, `.github/workflows/**`, `pint.json`, `phpstan.neon`, `vite.config.ts`, `tsconfig.json` | [quality-git.md](quality-git.md) |
| `compose.yaml`, `docker/**`, `Makefile`, `.env.example`, `.env.testing`, `deploy.php`, `scripts/**` | [runtime.md](runtime.md)          |
| `.agents/**`, `.claude/**`, `.codex/**`, `AGENTS.md`, `CLAUDE.md`, `tests/ai/**`, `.ai/rules/**` | [agents.md](agents.md)              |
