<?php

return [
    'title' => 'Verwaltung',
    'dashboard' => 'Übersicht',
    'welcome' => 'Willkommen im Administrationsbereich.',
    'language' => 'Sprache',
    'languageUpdated' => 'Spracheinstellung wurde aktualisiert.',

    'platformBadge' => 'Plattform-Cockpit',
    'platformDescription' => 'Betriebsstatus, Plattformdienste und Verwaltungseinstellungen.',

    'systemStatus' => [
        'title' => 'Plattform & Laufzeit',
        'description' => 'Aktive Anwendungsinfrastruktur und Treiber.',
        'environment' => 'Umgebung',
        'phpVersion' => 'PHP-Version',
        'framework' => 'Framework',
        'database' => 'Datenbank',
        'cache' => 'Cache-Treiber',
        'queue' => 'Warteschlange',
        'debugEnabled' => 'Debug: AN',
        'debugDisabled' => 'Debug: AUS',
    ],

    'security' => [
        'title' => 'Zugriffs- und Sicherheitsrichtlinie',
        'description' => 'Globale Sicherheitsregeln und Sitzungssteuerung.',
        'admin2fa' => 'Admin-2FA-Richtlinie',
        'enforced' => 'Erzwungen',
        'optional' => 'Optional',
        'sessionLifetime' => 'Sitzungsdauer',
        'sessionMinutes' => ':minutes min',
        'https' => 'Sichere Verbindung',
        'httpsActive' => 'Verschlüsselt (HTTPS)',
        'httpsInactive' => 'Unverschlüsselt (HTTP)',
    ],

    'platformSettings' => [
        'title' => 'Admin-Plattformeinstellungen',
        'description' => 'Administrative Einstellungen unabhängig von der öffentlichen Website.',
        'localeLabel' => 'Admin-Sprache',
        'localeHelp' => 'Steuert administrative Ansichten, Systemwarnungen und Benachrichtigungen.',
        'fallbackLabel' => 'System-Fallback',
        'fallbackBadge' => 'Ausweichsprache',
    ],

    'modules' => [
        'title' => 'Plattform-Module',
        'description' => 'Kernarchitekturdomänen bereit für Geschäftsabläufe.',
        'status' => [
            'active' => 'Betriebsbereit',
            'ready' => 'Erweiterungsbereit',
            'planned' => 'Geplant',
        ],
        'identity' => [
            'title' => 'Identität & Zugriff',
            'description' => 'Authentifizierung, Rollen, Berechtigungen, 2FA-Richtlinien und Passkeys.',
        ],
        'content' => [
            'title' => 'Content-Management (CMS)',
            'description' => 'Mehrsprachiges Routing, Seitenpublikation und redaktioneller Ablauf.',
        ],
        'media' => [
            'title' => 'Medienverwaltung (DAM)',
            'description' => 'Privater Speicher, Virenprüfung und WebP-Transformationen.',
        ],
        'audit' => [
            'title' => 'Audit & Überwachung',
            'description' => 'Unveränderliche Sicherheitsprotokolle, Benutzer-Audit und Ablaufverfolgung.',
        ],
    ],

    'nav' => [
        'platform' => 'Plattform',
        'workspace' => 'Arbeitsbereich',
        'dashboard' => 'Übersicht',
        'modules' => 'Geschäftsmodule',
        'settings' => 'Plattform-Einstellungen',
    ],

    'users' => [
        'title' => 'Benutzer',
        'description' => 'Durchsuchen, filtern und überprüfen Sie die auf dieser Plattform registrierten Konten.',
        'searchLabel' => 'Benutzer suchen',
        'searchPlaceholder' => 'Suche nach Name oder E-Mail',
        'searchClear' => 'Suche löschen',
        'filterVerifiedLabel' => 'Verifizierungsstatus',
        'filterAll' => 'Alle Status',
        'filterVerified' => 'Verifiziert',
        'filterUnverified' => 'Nicht verifiziert',
        'clearFilters' => 'Filter zurücksetzen',
        'columnName' => 'Name',
        'columnEmail' => 'E-Mail',
        'columnStatus' => 'Status',
        'columnCreatedAt' => 'Registriert',
        'statusVerified' => 'Verifiziert',
        'statusUnverified' => 'Nicht verifiziert',
        'rowActionsLabel' => 'Aktionen für :name',
        'actionCopyEmail' => 'E-Mail-Adresse kopieren',
        'actionEmailUser' => 'Benutzer per E-Mail kontaktieren',
        'emptyTitle' => 'Keine Benutzer gefunden',
        'emptyDescription' => 'Versuchen Sie einen anderen Suchbegriff oder setzen Sie die Filter zurück.',
        'errorTitle' => 'Benutzer konnten nicht geladen werden',
        'errorRetry' => 'Erneut versuchen',
        'tableCaption' => 'Plattformbenutzer',
        'previousPage' => 'Vorherige Seite',
        'nextPage' => 'Nächste Seite',
        'paginationSummary' => ':from–:to von :total Benutzern',
    ],
];
