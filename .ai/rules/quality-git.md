# Jakość kodu i Git

Glob: `lefthook.yml`, `commitlint.config.mjs`, `.githooks/**`, `.github/workflows/**`, `pint.json`, `phpstan.neon`, `vite.config.ts`, `tsconfig.json`

## Conventional Commits

- Format: `type(scope): description` — typ małymi literami, tytuł do 72 znaków, bez końcowej kropki, opis po angielsku w trybie rozkazującym (imperative mood).
- Dozwolone typy: `feat`, `fix`, `refactor`, `perf`, `test`, `docs`, `build`, `ci`, `chore`, `style`, `revert`.
- `style` = formatowanie bez zmiany zachowania; zmiana wyglądu/interakcji UI to `feat` lub `fix`.
- Scope małymi literami, nazwany obszarem (np. `auth`, `pages`, `media`, `ui`, `deps`, `docker`, `agents`).
- Breaking change wymaga `!` i footera `BREAKING CHANGE: ...`. `revert` wskazuje pełny SHA w body.
- Tytuł PR ma ten sam format. Squash merge wykonuje człowiek.
- Nie dopuszczaj `WIP`, `fixup!`, `squash!` w nowych commitach.

## Staging i indeks Git

- Przed stagingiem przeczytaj `git status --short`, `git diff` i `git diff --cached`.
- Do indeksu dodawaj wyłącznie pliki/hunki bieżącego zadania. Cudzy lub nieznany staged diff pozostaw i zgłoś blokadę.
- Nie używaj `git add .`, `git add -A`, `git commit -a`, automatycznego stash/reset ani przepisywania historii.
- Przed zgłoszeniem sukcesu sprawdź SHA, staged diff i status; podaj wykonane i niewykonane kontrole.

## Zakaz obejść

- Nie używaj `--no-verify`, `LEFTHOOK=0`, zmiennych skip/exclude, zmiany `core.hooksPath`, usuwania hooków ani osłabiania CI/skanerów/progów.
- Po błędzie hooka/CI napraw przyczynę i uruchom kontrolę ponownie.
- Nie obniżaj progu, nie dodawaj ignores/baseline i nie osłabiaj `ui-contract`, aby uzyskać PASS.
- Zmiany plików hooków, CI i skanerów wymagają autoryzowanego zakresu i niezależnego review.

## Lefthook i hooki

- Hooki Git działają na hoście i wywołują kontrole przez Docker/Makefile.
- `pre-commit`: kontrola brancha, konfliktów, `git diff --cached --check`, staged secrets (Gitleaks), format/lint dla zmienionych plików.
- `commit-msg`: walidacja wiadomości przez commitlint.
- Hooki działają w trybie check — bez `--fix`, automatycznego `git add` i stashowania. `stage_fixed: false`.
- Brak narzędzia/Dockera/timeout blokuje commit z instrukcją naprawy, nie cichym pominięciem.

## Pint

- Po edycji PHP: `docker compose exec app vendor/bin/pint --dirty --format agent`.
- Nie uruchamiaj `--test` do naprawy — używaj `--format agent` bez `--test`.

## Frontend check

- `npm run check` (Vite Plus) dla TS/TSX/CSS. `npm run types:check` (tsc --noEmit).
- `resources/js/components/ui/*` wykluczony z lint/fmt (vendored shadcn).
- Wygenerowane pliki Wayfinder (`actions/`, `routes/`, `wayfinder/`) wykluczone.

## CI

- PR do `develop`/`main`: commit-convention, secrets (Gitleaks), quality (Pint, PHPStan, testy, build, testy AI).
- CI jest niezależne od lokalnych hooków — obowiązkowe również po ich pominięciu.
