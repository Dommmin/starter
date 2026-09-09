# Zatwierdzony kierunek fundamentu

Status: decyzje kierunkowe zatwierdzone przez właściciela 2026-09-09. Dokument nie jest zgodą na scaffold, instalację pakietów ani wdrożenie.

## Runtime i wdrożenie

- Laravel 13 na PHP 8.5 oraz oficjalny starter kit React/Inertia/shadcn; Node 24 LTS obsługuje build i SSR. Konkretne patche, tagi i lockfile wymagają kwalifikacji w P0-A.
- PostgreSQL jest jedynym silnikiem P0; hosting i dane pozostają w regionie UE.
- P0 działa na jednej ekonomicznej VM: Nginx, PHP-FPM, PostgreSQL, Redis, Horizon, scheduler oraz Node SSR.
- GitHub Actions wykonuje CI/CD. Deployer utrzymuje pięć release'ów i atomowo przełącza `current`; przed przełączeniem uruchamia nowy SSR obok starego, kontroluje readiness/smoke i przeładowuje Horizon. Usługi działają natywnie bez Dockera.
- Jest to zero-downtime dla przełączenia kodu aplikacji, nie HA: awaria pojedynczej VM nadal powoduje niedostępność. Aktualizacja decyzji właściciela: rollback w oknie deployu jest automatyczny, do poprzedniej zgodnej wersji kodu/SSR, bez cofania danych. Szczegółowa proponowana recepta i lokalne środowisko: [dokument 10](../../foundation/10-local-environment-deployment-and-logs.md).

## Frontend, SEO i wydajność

- Wszystkie trasy są React/Inertia. Publiczne trasy mają obowiązkowy SSR, ponieważ SEO jest funkcją P0.
- Publiczne strony używają `Head` do title, description, canonical, Open Graph i robots; sitemap obejmuje wyłącznie opublikowane URL-e.
- Nginx długo cache'uje hashowane assety Vite (`immutable`). Odpowiedzi HTML/SSR i Inertia nie są współdzielone domyślnie; Redis cache'uje wyłącznie jawnie bezpieczne odczyty, z invalidacją po publikacji i zmianie sluga.
- Prefetch Inertia jest ograniczony do prawdopodobnych, tanich nawigacji GET i ma krótki TTL; nie uruchamia się go dla mutacji, formularzy ani dużych list administracyjnych.
- Design system ma jeden zestaw tokenów i prymitywów, ale dwa charaktery: editorialny frontend publiczny oraz utility-first panel. shadcn/Radix są źródłem bazowego kodu, a nie publicznym API aplikacji.

## Treści i media

- P0 zawiera strony oraz minimalne artykuły/aktualności: title, slug, excerpt, body, cover asset, draft/published, publikacja i metadata SEO.
- Edytor P0 to Tiptap z zamkniętym zestawem bloków: nagłówki, akapity, listy, linki, cytat, code inline, podstawowe formatowanie oraz obraz wybierany z DAM. Treść strukturalna jest walidowana; renderowany HTML jest sanitizowany przed publikacją.
- DAM P0 jest wyłącznie dla panelu administratora. Assety są domyślnie prywatne; publiczny wariant powstaje dopiero przy podpięciu do opublikowanej treści.
- DAM może przechowywać różne typy plików, ale każdy plik ma limit 50 MB, prywatną kwarantannę, walidację typu po zawartości i skan. Awaria skanera blokuje udostępnienie. Tylko obrazy dostają warianty WebP/AVIF przez Intervention Image i joby Horizon; inne pliki nie mają automatycznego preview lub konwersji w P0.
- Storage P0 jest lokalny na VM, poza katalogami release'ów i objęty backupem poza hostem. Model `MediaAsset` musi pozwolić później przejść na storage obiektowy lub Cloudinary.
- Uploady zwykłych użytkowników oraz płatności są poza P0. Kiedy pojawi się proces biznesowy, dostaną osobne modele własności, policies, endpointy i storage; nie uzyskują dostępu do DAM.

## Nierozstrzygnięte

- Marka, referencyjna Figma, finalna typografia i konkretna paleta.
- Dostawca VM, koszt, operator i zastępca, RTO/RPO oraz retencja.
- Konkretne tagi startera, pakiet sanitizera i skanera malware oraz dostępność bibliotek AVIF/WebP na docelowej VM.
- Czy późniejszy DAM wymaga Cloudinary lub EU object storage; decyzja zależy od wolumenu, DPA/regionu i kosztu.
