<?php

namespace Database\Seeders;

use App\Enums\MenuItemType;
use App\Enums\MenuLocation;
use App\Models\ArticleTranslation;
use App\Models\MenuItem;
use App\Models\PageTranslation;
use App\Services\Navigation\PublicNavigation;
use Illuminate\Database\Seeder;

/**
 * Local sample menu items appended after the default menus of
 * {@see NavigationMenuSeeder} in every public language: a page link, a
 * group with a nested submenu (pages, article and external link), an
 * external link opened in a new tab and a footer column of links; German
 * gets shorter menus of its own. Idempotent: an item already present (same
 * menu, top level, type and label) is skipped with its children.
 */
class DemoNavigationSeeder extends Seeder
{
    /**
     * Locales that receive the sample items.
     *
     * @var list<string>
     */
    public const array LOCALES = ['en', 'pl', 'de'];

    /**
     * Seeded top-level items of one locale keyed by location. `page` and
     * `article` are {@see DemoContent} keys.
     *
     * @return array<string, list<array{type: MenuItemType, label: string, url?: string, open_in_new_tab?: bool, page?: string, article?: string, children?: list<array{type: MenuItemType, label: string, url?: string, open_in_new_tab?: bool, page?: string, article?: string}>}>>
     */
    public static function definitions(string $locale): array
    {
        $label = fn (string $en, string $pl): string => $locale === 'pl' ? $pl : $en;

        if ($locale === 'de') {
            return [
                MenuLocation::Header->value => [
                    ['type' => MenuItemType::Page, 'label' => 'Leistungen', 'page' => 'services'],
                    [
                        'type' => MenuItemType::Group,
                        'label' => 'Mehr',
                        'children' => [
                            ['type' => MenuItemType::Page, 'label' => 'Über uns', 'page' => 'about'],
                            ['type' => MenuItemType::Article, 'label' => 'Versionshinweise', 'article' => 'release-notes'],
                        ],
                    ],
                ],
                MenuLocation::Footer->value => [
                    [
                        'type' => MenuItemType::Group,
                        'label' => 'Unternehmen',
                        'children' => [
                            ['type' => MenuItemType::Page, 'label' => 'Leistungen', 'page' => 'services'],
                            ['type' => MenuItemType::Page, 'label' => 'Impressum', 'page' => 'imprint'],
                        ],
                    ],
                ],
            ];
        }

        return [
            MenuLocation::Header->value => [
                ['type' => MenuItemType::Page, 'label' => $label('Services', 'Usługi'), 'page' => 'services'],
                [
                    'type' => MenuItemType::Group,
                    'label' => $label('More', 'Więcej'),
                    'children' => [
                        ['type' => MenuItemType::Page, 'label' => $label('About us', 'O nas'), 'page' => 'about'],
                        ['type' => MenuItemType::Page, 'label' => $label('Pricing', 'Cennik'), 'page' => 'pricing'],
                        ['type' => MenuItemType::Article, 'label' => $label('Case study', 'Studium przypadku'), 'article' => 'case-study'],
                        ['type' => MenuItemType::External, 'label' => 'Laravel', 'url' => 'https://laravel.com', 'open_in_new_tab' => true],
                    ],
                ],
                ['type' => MenuItemType::External, 'label' => 'GitHub', 'url' => 'https://github.com', 'open_in_new_tab' => true],
            ],
            MenuLocation::Footer->value => [
                [
                    'type' => MenuItemType::Group,
                    'label' => $label('Company', 'Firma'),
                    'children' => [
                        ['type' => MenuItemType::Page, 'label' => $label('Terms of service', 'Regulamin'), 'page' => 'terms'],
                        ['type' => MenuItemType::Article, 'label' => $label('Our history', 'Nasza historia'), 'article' => 'history'],
                        ['type' => MenuItemType::Page, 'label' => $label('How we work', 'Jak pracujemy'), 'page' => 'how-we-work'],
                    ],
                ],
            ],
        ];
    }

    public function run(PublicNavigation $navigation): void
    {
        foreach (self::LOCALES as $locale) {
            foreach (self::definitions($locale) as $location => $items) {
                $position = (int) MenuItem::query()
                    ->where('location', $location)
                    ->where('locale', $locale)
                    ->whereNull('parent_id')
                    ->max('position');

                foreach ($items as $definition) {
                    if (self::find($location, $locale, $definition) !== null) {
                        continue;
                    }

                    $parent = self::create($location, $locale, null, ++$position, $definition);

                    foreach ($definition['children'] ?? [] as $index => $child) {
                        self::create($location, $locale, $parent->id, $index + 1, $child);
                    }
                }
            }
        }

        $navigation->forget();
    }

    /**
     * The seeded top-level item of a definition, if it exists.
     *
     * @param  array{type: MenuItemType, label: string}  $definition
     */
    public static function find(string $location, string $locale, array $definition): ?MenuItem
    {
        return MenuItem::query()
            ->where('location', $location)
            ->where('locale', $locale)
            ->whereNull('parent_id')
            ->where('type', $definition['type']->value)
            ->where('label', $definition['label'])
            ->first();
    }

    /**
     * @param  array{type: MenuItemType, label: string, url?: string, open_in_new_tab?: bool, page?: string, article?: string}  $definition
     */
    private static function create(string $location, string $locale, ?int $parentId, int $position, array $definition): MenuItem
    {
        return MenuItem::query()->create([
            'location' => MenuLocation::from($location),
            'locale' => $locale,
            'parent_id' => $parentId,
            'position' => $position,
            'type' => $definition['type'],
            'label' => $definition['label'],
            'url' => $definition['url'] ?? null,
            'open_in_new_tab' => $definition['open_in_new_tab'] ?? false,
            'page_id' => isset($definition['page']) ? self::pageId($definition['page']) : null,
            'article_id' => isset($definition['article']) ? self::articleId($definition['article']) : null,
        ]);
    }

    private static function pageId(string $key): ?int
    {
        $id = PageTranslation::query()->whereIn('slug', DemoContent::PAGES[$key])->value('page_id');

        return is_int($id) ? $id : null;
    }

    private static function articleId(string $key): ?int
    {
        $id = ArticleTranslation::query()->whereIn('slug', DemoContent::ARTICLES[$key])->value('article_id');

        return is_int($id) ? $id : null;
    }
}
