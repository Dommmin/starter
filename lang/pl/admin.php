<?php

return [
    'title' => 'Administracja',
    'dashboard' => 'Pulpit',
    'welcome' => 'Witaj w panelu administracyjnym.',
    'language' => 'Język',
    'languageUpdated' => 'Preferencja języka została zaktualizowana.',

    'platformBadge' => 'Cockpit platformy',
    'platformDescription' => 'Stan operacyjny, usługi platformy i ustawienia administracyjne.',

    'systemStatus' => [
        'title' => 'Platforma i środowisko',
        'description' => 'Aktywna infrastruktura aplikacji i sterowniki runtime.',
        'environment' => 'Środowisko',
        'phpVersion' => 'Wersja PHP',
        'framework' => 'Framework',
        'database' => 'Baza danych',
        'cache' => 'Pamięć podręczna',
        'queue' => 'Kolejka zadań',
        'debugEnabled' => 'Debug: włączony',
        'debugDisabled' => 'Debug: wyłączony',
    ],

    'security' => [
        'title' => 'Polityka dostępu i bezpieczeństwo',
        'description' => 'Globalne reguły bezpieczeństwa i kontrola sesji administracyjnej.',
        'admin2fa' => 'Wymóg 2FA dla administratorów',
        'enforced' => 'Wymuszone',
        'optional' => 'Opcjonalne',
        'sessionLifetime' => 'Czas życia sesji',
        'sessionMinutes' => ':minutes min',
        'https' => 'Połączenie HTTPS',
        'httpsActive' => 'Szyfrowane (HTTPS)',
        'httpsInactive' => 'Nieszyfrowane (HTTP)',
    ],

    'platformSettings' => [
        'title' => 'Ustawienia platformy panelu',
        'description' => 'Zarządzaj ustawieniami panelu niezależnie od publicznej strony.',
        'localeLabel' => 'Język panelu administracyjnego',
        'localeHelp' => 'Steruje widokami panelu, alertami systemowymi i powiadomieniami dla administratorów.',
        'fallbackLabel' => 'Język awaryjny',
        'fallbackBadge' => 'Język zapasowy',
    ],

    'modules' => [
        'title' => 'Moduły platformy',
        'description' => 'Kluczowe domeny architektoniczne gotowe na dedykowane procesy biznesowe.',
        'status' => [
            'active' => 'Aktywny',
            'ready' => 'Gotowy do rozbudowy',
            'planned' => 'Planowany',
        ],
        'identity' => [
            'title' => 'Tożsamość i Dostęp',
            'description' => 'Uwierzytelnianie, role, uprawnienia, polityki 2FA i klucze dostępu passkeys.',
        ],
        'content' => [
            'title' => 'Zarządzanie Treścią (CMS)',
            'description' => 'Wielojęzyczny routing, publikacja stron i obieg redakcyjny.',
        ],
        'media' => [
            'title' => 'Biblioteka Mediów (DAM)',
            'description' => 'Prywatny magazyn zasobów, skanowanie antywirusowe i transformacje WebP.',
        ],
        'audit' => [
            'title' => 'Dziennik Audytu i Zdarzeń',
            'description' => 'Niezmienny rejestr zdarzeń bezpieczeństwa, audyt działań i śledzenie zapytań.',
        ],
    ],

    'nav' => [
        'platform' => 'Platforma',
        'workspace' => 'Przestrzeń robocza',
        'dashboard' => 'Pulpit',
        'modules' => 'Moduły biznesowe',
        'settings' => 'Ustawienia platformy',
    ],

    'users' => [
        'title' => 'Użytkownicy',
        'description' => 'Wyszukuj, filtruj i przeglądaj konta zarejestrowane na platformie.',
        'searchLabel' => 'Szukaj użytkowników',
        'searchPlaceholder' => 'Szukaj po nazwie lub adresie e-mail',
        'searchClear' => 'Wyczyść wyszukiwanie',
        'filterVerifiedLabel' => 'Status weryfikacji',
        'filterAll' => 'Wszystkie statusy',
        'filterVerified' => 'Zweryfikowani',
        'filterUnverified' => 'Niezweryfikowani',
        'clearFilters' => 'Wyczyść filtry',
        'columnName' => 'Nazwa',
        'columnEmail' => 'E-mail',
        'columnStatus' => 'Status',
        'columnCreatedAt' => 'Zarejestrowano',
        'statusVerified' => 'Zweryfikowany',
        'statusUnverified' => 'Niezweryfikowany',
        'rowActionsLabel' => 'Akcje dla: :name',
        'actionCopyEmail' => 'Skopiuj adres e-mail',
        'actionEmailUser' => 'Wyślij e-mail',
        'emptyTitle' => 'Nie znaleziono użytkowników',
        'emptyDescription' => 'Spróbuj innego zapytania lub wyczyść aktywne filtry.',
        'errorTitle' => 'Nie udało się wczytać użytkowników',
        'errorRetry' => 'Spróbuj ponownie',
        'tableCaption' => 'Użytkownicy platformy',
        'previousPage' => 'Poprzednia strona',
        'nextPage' => 'Następna strona',
        'paginationSummary' => 'Pokazano :from–:to z :total użytkowników',
    ],
];
