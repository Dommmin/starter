<?php

return [
    'title' => 'Administration',
    'dashboard' => 'Dashboard',
    'welcome' => 'Welcome to the administration panel.',
    'language' => 'Language',
    'languageUpdated' => 'Language preference updated.',

    'platformBadge' => 'Platform Cockpit',
    'platformDescription' => 'Operational status, platform services, and administration settings.',

    'systemStatus' => [
        'title' => 'Platform & Runtime',
        'description' => 'Active application infrastructure and runtime drivers.',
        'environment' => 'Environment',
        'phpVersion' => 'PHP Version',
        'framework' => 'Framework',
        'database' => 'Database',
        'cache' => 'Cache Driver',
        'queue' => 'Queue Driver',
        'debugEnabled' => 'Debug: ON',
        'debugDisabled' => 'Debug: OFF',
    ],

    'security' => [
        'title' => 'Access & Security Policy',
        'description' => 'Global administrative security rules and session controls.',
        'admin2fa' => 'Admin 2FA Policy',
        'enforced' => 'Enforced',
        'optional' => 'Optional',
        'sessionLifetime' => 'Session Lifetime',
        'sessionMinutes' => ':minutes min',
        'https' => 'Secure Connection',
        'httpsActive' => 'Encrypted (HTTPS)',
        'httpsInactive' => 'Unencrypted (HTTP)',
    ],

    'platformSettings' => [
        'title' => 'Admin Platform Settings',
        'description' => 'Administrative preferences independent of the public website.',
        'localeLabel' => 'Admin Panel Language',
        'localeHelp' => 'Controls administrative views, system alerts, and notification emails for administrators.',
        'fallbackLabel' => 'System Fallback',
        'fallbackBadge' => 'Fallback locale',
    ],

    'modules' => [
        'title' => 'Platform Modules',
        'description' => 'Core architectural domains ready for custom business workflows.',
        'status' => [
            'active' => 'Operational',
            'ready' => 'Ready to extend',
            'planned' => 'Planned',
        ],
        'identity' => [
            'title' => 'Identity & Access',
            'description' => 'Authentication, roles, permissions, 2FA policies, and passkeys.',
        ],
        'content' => [
            'title' => 'Content Management (CMS)',
            'description' => 'Multi-locale routing, structured page publishing, and editorial workflow.',
        ],
        'media' => [
            'title' => 'Media Asset Manager (DAM)',
            'description' => 'Private storage, virus scanning, quarantined assets, and WebP transformations.',
        ],
        'audit' => [
            'title' => 'Audit & Observability',
            'description' => 'Immutable security event logging, actor auditing, and execution traces.',
        ],
    ],

    'nav' => [
        'platform' => 'Platform',
        'workspace' => 'Workspace',
        'dashboard' => 'Dashboard',
        'modules' => 'Business Modules',
        'settings' => 'Platform Settings',
    ],

    'users' => [
        'title' => 'Users',
        'description' => 'Search, filter, and review the accounts registered on this platform.',
        'searchLabel' => 'Search users',
        'searchPlaceholder' => 'Search by name or email',
        'searchClear' => 'Clear search',
        'filterVerifiedLabel' => 'Verification status',
        'filterAll' => 'All statuses',
        'filterVerified' => 'Verified',
        'filterUnverified' => 'Unverified',
        'clearFilters' => 'Clear filters',
        'columnName' => 'Name',
        'columnEmail' => 'Email',
        'columnStatus' => 'Status',
        'columnCreatedAt' => 'Registered',
        'statusVerified' => 'Verified',
        'statusUnverified' => 'Unverified',
        'rowActionsLabel' => 'Actions for :name',
        'actionCopyEmail' => 'Copy email address',
        'actionEmailUser' => 'Email user',
        'emptyTitle' => 'No users found',
        'emptyDescription' => 'Try a different search term or clear the active filters.',
        'errorTitle' => 'Could not load users',
        'errorRetry' => 'Try again',
        'tableCaption' => 'Platform users',
        'previousPage' => 'Previous page',
        'nextPage' => 'Next page',
        'paginationSummary' => 'Showing :from–:to of :total users',
    ],
];
