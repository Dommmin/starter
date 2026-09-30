<?php

namespace Database\Seeders;

use App\Enums\HomeSectionAnchor;
use App\Enums\MenuItemType;
use App\Enums\MenuLocation;
use App\Models\MenuItem;
use App\Models\PageTranslation;
use App\Services\Localization\LocalizationConfig;
use App\Services\Navigation\PublicNavigation;
use Illuminate\Database\Seeder;

/**
 * Default public menus for every public locale, so a fresh installation
 * has a navigation: header — the home page "features" anchor and the
 * article list; footer — the privacy policy when that sample page exists
 * (PageSeeder, slug `privacy-policy`).
 *
 * Idempotent: a menu (location + locale) that already has any item is left
 * untouched, so editor changes are never overwritten. The definition is
 * public (self::defaults()); seeded items have no author.
 */
class NavigationMenuSeeder extends Seeder
{
    public const string PRIVACY_POLICY_SLUG = 'privacy-policy';

    public const string FEATURES_ANCHOR = HomeSectionAnchor::Features->value;

    public function run(LocalizationConfig $config, PublicNavigation $navigation): void
    {
        $privacyPageId = self::privacyPolicyPageId();

        foreach ($config->getPublicLocales() as $locale) {
            foreach (self::defaults($locale, $privacyPageId) as $location => $items) {
                $this->seedMenu(MenuLocation::from($location), $locale, $items);
            }
        }

        $navigation->forget();
    }

    /**
     * Definition of the default menus of one locale, keyed by location. A
     * demo-content provider can use it to recognize the seeded items
     * (type + anchor / page_id per position) without heuristics. The footer
     * link is included only when the privacy policy page is translated in
     * the locale.
     *
     * @param  int|null  $privacyPageId  See self::privacyPolicyPageId().
     * @return array<string, list<array{type: MenuItemType, anchor?: string, page_id?: int, label?: string}>>
     */
    public static function defaults(string $locale, ?int $privacyPageId): array
    {
        $menus = [
            MenuLocation::Header->value => [
                [
                    'type' => MenuItemType::Anchor,
                    'anchor' => self::FEATURES_ANCHOR,
                    'label' => __('common.nav.features', [], $locale),
                ],
                [
                    'type' => MenuItemType::ArticleIndex,
                    'label' => __('common.nav.articles', [], $locale),
                ],
            ],
        ];

        $hasPrivacyTranslation = $privacyPageId !== null && PageTranslation::query()
            ->where('page_id', $privacyPageId)
            ->where('locale', $locale)
            ->exists();

        if ($privacyPageId !== null && $hasPrivacyTranslation) {
            $menus[MenuLocation::Footer->value] = [
                ['type' => MenuItemType::Page, 'page_id' => $privacyPageId],
            ];
        }

        return $menus;
    }

    /**
     * The sample privacy policy page (PageSeeder), if it exists.
     */
    public static function privacyPolicyPageId(): ?int
    {
        $pageId = PageTranslation::query()
            ->where('slug', self::PRIVACY_POLICY_SLUG)
            ->value('page_id');

        return is_int($pageId) ? $pageId : null;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function seedMenu(MenuLocation $location, string $locale, array $items): void
    {
        $exists = MenuItem::query()
            ->where('location', $location->value)
            ->where('locale', $locale)
            ->exists();

        if ($exists) {
            return;
        }

        foreach ($items as $index => $attributes) {
            MenuItem::query()->create([
                ...$attributes,
                'location' => $location,
                'locale' => $locale,
                'position' => $index + 1,
            ]);
        }
    }
}
