# Nowy klient w 5 dni

Jedna strona dla osoby, która stawia stronę klienta na starterze. Zakres: wizytówka lub mała strona firmowa z panelem, blogiem, formularzami i stronami prawnymi; bez płatności, dynamicznego RBAC, page buildera i multi-tenancy. Szczegóły komend: [README](../../README.md), deploy: [dokument 10](10-local-environment-deployment-and-logs.md), ops: [dokument 06](06-devops-observability-and-release.md).

## Przed dniem 1 — od klienta

- [ ] Nazwa firmy, logo (SVG lub PNG ≥ 512 px), kolor marki, języki strony, domena.
- [ ] Dane firmy: adres, NIP, telefon, e-mail kontaktowy, godziny otwarcia, link do mapy.
- [ ] Teksty: strona główna (hero, oferta, o nas), 3–5 pytań FAQ, opcjonalnie 1–2 artykuły; zdjęcia z prawami do użycia.
- [ ] Kto odbiera leady (do 5 adresów), czy webhook do CRM, treść auto-odpowiedzi.
- [ ] Osoby do panelu i ich role: admin (z 2FA), redaktor, viewer (tylko podgląd).

## Dzień 1 — repo i start lokalny

1. Nowe repo klienta z kopii startera (propagacja aktualizacji: sekcja „Aktualizacje startera” niżej).
2. `make setup`, `make doctor`.
3. `make init-project ARGS='--name="Firma" --locales=pl,en --default-locale=pl --accent=blue --admin-email=… --admin-name=… --write-env --dry-run'`, po przejrzeniu planu bez `--dry-run`. Dane demo usuń później opcją `--remove-demo`.
4. Panel → Ustawienia strony: nazwa, logo i favicon z biblioteki mediów, akcent, typografia, domyślny motyw, dane firmy, godziny, social media, SEO.
5. `make test` i `make npm ARGS='run check'` — punkt odniesienia przed zmianami.

## Dzień 2 — treści

1. Strona główna: Panel → Strona główna (kolejność, widoczność, treść w każdym języku).
2. Strony, FAQ (import CSV dla dłuższych list), tagi i artykuły; menu nagłówka i stopki.
3. Formularze kontaktowe: domyślny „contact” i np. „quote” z polami dodatkowymi; odbiorcy i auto-odpowiedź w ustawieniach.
4. Strony prawne (seedowane jako szkice): uzupełnij wszystkie `[[…]]`, przekaż do przeglądu prawnego, opublikuj.
5. `make artisan ARGS='app:init-project --remove-demo'` — usuwa nieedytowane treści demo i konto demo.

## Dzień 3 — wygląd i wymagania specjalne

- Tylko z publicznego API design systemu (ADR-019). Brak potrzebnego wariantu → propozycja rozszerzenia DS, nie lokalny CSS.
- Nowy moduł danych: `make artisan ARGS='app:make-resource …'` (opcje w [dokumencie 01](01-architecture-and-modularity.md)); migracje i role tylko po zgodzie właściciela.
- Sprawdź telefon (375 px) i oba motywy na stronie głównej, liście artykułów, artykule i formularzu.

## Dzień 4 — staging i odbiór (UAT)

1. Serwer w UE wg szablonów z `deploy/` (Nginx, PHP-FPM, systemd, backup), `shared/.env` produkcji, DNS i TLS.
2. Pierwszy deploy przez `deploy.yml`/Deployer z `DEPLOY_ALLOW_MIGRATIONS=true` (człowiek zatwierdza).
3. Panel → Stan techniczny: tryb „wkrótce” włączony na czas odbioru, `noindex` włączony.
4. Klient testuje: formularze (mail testowy z panelu Stan techniczny, odbiorcy, webhook), treści, język, telefon. Uwagi → poprawki.
5. `make backup` na stagingu i `make restore-drill` — zapisz czas i wynik.

## Dzień 5 — start

- [ ] Checklista startu w panelu Stan techniczny: 2FA wszystkich adminów, testowy e-mail, token Search Console, opublikowane strony prawne.
- [ ] Wyłącz tryb „wkrótce” i `noindex`; sitemap `/sitemap.xml` zgłoszona w Search Console.
- [ ] Monitor dostępności `/health/ready`, kanał alertów, backup poza hostem (dokument 10, „Wymaga operatora”).
- [ ] Konta klienta z właściwymi rolami; przekazanie: jak edytować treści, gdzie są leady, kontakt serwisowy.
- [ ] Wpis w rejestrze wersji klientów (sekcja niżej): domena, wersja startera, data startu.

## Aktualizacje startera

- Repo klienta zakładaj przez `git clone` startera i zmianę `origin` na repo klienta — wspólna historia pozwala potem scalać aktualizacje.
- W repo klienta skopiuj `deploy/client/starter-sync.yml` do `.github/workflows/`, ustaw zmienną `STARTER_REPOSITORY` (i opcjonalnie `STARTER_REF`) oraz sekret `STARTER_SYNC_TOKEN`, jeśli PR ma uruchamiać CI albo starter jest prywatny.
- Co poniedziałek workflow uruchamia `scripts/starter-sync.sh`: nowa wersja startera trafia na gałąź `starter-sync/<wersja>` i PR; konflikt otwiera issue. Ręcznie: `STARTER_REPOSITORY=… sh scripts/starter-sync.sh`.
- PR aktualizacji przegląda człowiek: migracje (expand-only), zmienione domyślne wartości, teksty i DS; CI musi przejść. Automat niczego nie scala.
- `STARTER_VERSION` w starterze (data `RRRR.MM.DD`) podbija PR wydania `develop` → `main`.

## Rejestr wersji klientów (Q10)

Prowadzi jeden opiekun startera, poza repo startera (dane klientów), np. w prywatnym repo agencji:

| Klient | Domena | Repo | `STARTER_VERSION` | Ostatnia aktualizacja | Opiekun |
| ------ | ------ | ---- | ----------------- | --------------------- | ------- |
