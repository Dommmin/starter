# Runtime — Docker Compose, Makefile i deploy

Glob: `compose.yaml`, `docker/**`, `Makefile`, `.env.example`, `.env.testing`, `deploy.php`, `scripts/**`

## Lokalny runtime

- Jedyny wspierany lokalny workflow to **Docker Compose sterowany przez Makefile**. Nie uruchamiaj PHP, Node, Composer ani Artisan bezpośrednio na hoście.
- Podstawowym i preferowanym interfejsem lokalnym jest `Makefile`: `make setup`, `make up`, `make doctor`, `make test`, `make artisan`, `make composer`, `make npm` (ewentualnie `docker compose exec app <command>` w razie potrzeby).

| Czynność                | Komenda                                        |
| ----------------------- | ---------------------------------------------- |
| Pierwsze uruchomienie   | `make setup`                                   |
| Start kontenerów        | `make up`                                      |
| Diagnostyka środowiska  | `make doctor`                                  |
| Testy                   | `make test ARGS='...'`                         |
| Pełne kontrole jakości  | `make check`                                   |
| Instalacja hooków       | `make hooks`                                   |
| Artisan                 | `make artisan ARGS='...'`                      |
| Composer                | `make composer ARGS='...'`                     |
| npm                     | `make npm ARGS='...'`                          |
| Shell w kontenerze      | `make shell`                                   |
| Build assetów + SSR     | `make assets`                                  |

- `make down` zachowuje wolumeny. Nie ma automatycznego resetu ani kasowania danych.
- `make setup` nie seeduje kont automatycznie.

## Usługi

- PHP 8.5 FPM/CLI, Nginx, PostgreSQL 18, Redis 8.2, queue:work, schedule:work, Vite (dev SSR), Mailpit.
- Horizon pozostaje planowany — aktualnie `queue:work`.
- `.env` jest jedynym lokalnym źródłem konfiguracji — Compose czyta je bezpośrednio. Nie nadpisuj hostowego `.env`.

## Deploy (Deployer)

- Recepta w `deploy.php`. Deployer publikuje archiwum, utrzymuje 5 release'ów i atomowo przełącza `current`.
- Człowiek zatwierdza manifest i inicjuje `dep deploy`. Agent nie deployuje, nie rollbackuje i nie ma credentiali produkcyjnych.
- Automatyczny rollback w ograniczonym oknie deployu wykonuje deterministyczna recepta — nie jest to samodzielna decyzja agenta. Poza oknem decyduje operator.
- Produkcja: natywne Nginx + PHP-FPM, nie Docker. Nie kopiuj konfiguracji Docker na produkcję.

## Artefakt

- Archiwum: kod + vendor + bundle klienta/SSR z lockfile, z manifestem commit SHA + SHA-256. Bez sekretów w archiwum.
- Ten sam artefakt trafia na staging i produkcję; config cache powstaje na hoście.
- Nie rozwiązuj zależności na produkcji.

## Co nie zmieniać

- Nie zmieniaj `.env*` plików (poza `.env.example` za zgodą).
- Git komendy (`git status`, `git diff`, `gh`) działają na hoście — Docker nie jest potrzebny.
