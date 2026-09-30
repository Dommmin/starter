# Routing i lokalizacja — przewodnik

## Podział routów

- `routes/web.php` rejestruje grupy routów i alias domyślnego języka.
- `routes/front.php` definiuje publiczne trasy o stałych adresach (strona główna, kontakt, lista i widok artykułów `/articles`).
- `routes/content.php` zawiera jednoczłonowy catch-all stron CMS (`/{slug}`); jest ładowany na końcu, a pierwsze segmenty pozostałych tras są zarezerwowane dla slugów stron.
- `routes/admin.php` zawiera panel pod `/admin`, nazwy `admin.*` i middleware panelu.
- `routes/settings.php` pozostaje poza prefiksem `/admin`, aby zachować adresy konta.
- `routes/auth.php` definiuje routy Fortify dla języka domyślnego oraz dla dodatkowych języków publicznych.

Publiczna trasa dodana w `front.php` lub `content.php` jest ładowana bez prefiksu dla domyślnego języka i jako `/{locale}` dla pozostałych aktywnych języków. Nowy CRUD panelu należy dodać do grupy w `admin.php`; otrzyma `/admin/...` i nazwy `admin.*`.

## Kontrakt lokalizacji

- Język strony publicznej wynika z adresu URL; domyślny język nie ma prefiksu.
- Język panelu jest niezależny: profil użytkownika, potem sesja, potem `en`.
- Backend przekazuje przez Inertia `i18n.locale`, `defaultLocale`, `availableLocales` i katalog `messages`.
- React używa `i18next` oraz `react-i18next`; komponenty pobierają `t`, `locale` i `defaultLocale` przez `useTranslation()`.
- Wspólny generator URL obsługuje linki publiczne i alternaty językowe. Trasy z parametrem tłumaczonym per język (slug strony lub artykułu) same udostępniają alternaty przez `Inertia::share('i18n.alternateUrls', ...)`.

## Weryfikacja

Po zmianie routingu lub i18n uruchom:

```sh
make test ARGS='tests/Feature/DefaultDePublicRoutingTest.php tests/Feature/LocalizationCatalogGateTest.php'
make npm ARGS='run types:check'
make npm ARGS='run test:ssr'
make npm ARGS='run check:ui-contract'
```

Następnie sprawdź w przeglądarce stronę główną: musi pokazywać wartości tłumaczeń oraz przy wielu aktywnych językach przycisk wyboru języka.
