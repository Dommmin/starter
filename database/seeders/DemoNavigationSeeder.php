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
 * {@see NavigationMenuSeeder}: a group with a nested submenu (page, article
 * and external link), an external link opened in a new tab and a footer
 * link to an article. Idempotent: an item already present (same menu,
 * top level, type and label) is skipped with its children.
 */
class DemoNavigationSeeder extends Seeder
{
    /**
     * Locales that receive the sample items.
     *
     * @var list<string>
     */
    public const array LOCALES = ['en', 'pl'];

    /**
     * Seeded top-level items of one locale keyed by location. `page` and
     * `article` are {@see DemoContent} keys.
     *
     * @return array<string, list<array{type: MenuItemType, label: string, url?: string, open_in_new_tab?: bool, page?: string, article?: string, children?: list<array{type: MenuItemType, label: string, url?: string, open_in_new_tab?: bool, page?: string, article?: string}>}>>
     */
    public static function definitions(string $locale): array
    {
        $polish = $locale === 'pl';

        return [
            MenuLocation::Header->value => [
                [
                    'type' => MenuItemType::Group,
                    'label' => $polish ? 'Więcej' : 'More',
                    'children' => [
                        ['type' => MenuItemType::Page, 'label' => $polish ? 'O nas' : 'About us', 'page' => 'about'],
                        ['type' => MenuItemType::Article, 'label' => $polish ? 'Studium przypadku' : 'Case study', 'article' => 'case-study'],
                        ['type' => MenuItemType::External, 'label' => 'Laravel', 'url' => 'https://laravel.com', 'open_in_new_tab' => true],
                    ],
                ],
                ['type' => MenuItemType::External, 'label' => 'GitHub', 'url' => 'https://github.com', 'open_in_new_tab' => true],
            ],
            MenuLocation::Footer->value => [
                ['type' => MenuItemType::Page, 'label' => $polish ? 'Regulamin' : 'Terms of service', 'page' => 'terms'],
                ['type' => MenuItemType::Article, 'label' => $polish ? 'Nasza historia' : 'Our history', 'article' => 'history'],
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
