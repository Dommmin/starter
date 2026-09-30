<?php

namespace Database\Seeders;

use App\Contracts\DemoContent\DemoContentProvider;
use App\Support\DemoContent\DemoArticles;
use App\Support\DemoContent\DemoHomeSections;
use App\Support\DemoContent\DemoMenuItems;
use App\Support\DemoContent\DemoPages;

/**
 * Registry of the sample content created by the seeders. Seeders use these
 * values instead of literals and `app:init-project --remove-demo` removes
 * exactly these records. A module adding sample content registers its
 * provider in {@see self::PROVIDERS}.
 */
final class DemoContent
{
    /**
     * E-mail address of the seeded sample administrator.
     */
    public const string USER_EMAIL = 'test@example.com';

    public const string USER_NAME = 'Test User';

    /**
     * Seeded pages as `locale => slug` of all their translations.
     *
     * @var array<string, array<string, string>>
     */
    public const array PAGES = [
        'privacy' => ['en' => 'privacy-policy', 'pl' => 'polityka-prywatnosci'],
        'upcoming' => ['en' => 'upcoming-offer'],
    ];

    /**
     * Seeded articles as `locale => slug` of all their translations.
     *
     * @var array<string, array<string, string>>
     */
    public const array ARTICLES = [
        'welcome' => ['en' => 'welcome-to-our-news', 'pl' => 'witamy-w-aktualnosciach'],
        'scheduled' => ['en' => 'coming-next-week'],
        'draft' => ['en' => 'draft-ideas'],
    ];

    /**
     * Providers of removable sample content, in removal order: menu items
     * first (they point at the sample pages), then pages, articles and home
     * sections. The sample account is removed separately, after all of them.
     *
     * @var list<class-string<DemoContentProvider>>
     */
    public const array PROVIDERS = [
        DemoMenuItems::class,
        DemoPages::class,
        DemoArticles::class,
        DemoHomeSections::class,
    ];
}
