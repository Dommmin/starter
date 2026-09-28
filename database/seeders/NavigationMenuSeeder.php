<?php

namespace Database\Seeders;

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
 * untouched, so editor changes are never overwritten. Seeded items have no
 * author (`created_by` null).
 */
class NavigationMenuSeeder extends Seeder
{
    public const string PRIVACY_POLICY_SLUG = 'privacy-policy';

    public function run(LocalizationConfig $config, PublicNavigation $navigation): void
    {
        $privacyPageId = PageTranslation::query()
            ->where('slug', self::PRIVACY_POLICY_SLUG)
            ->value('page_id');

        foreach ($config->getPublicLocales() as $locale) {
            $this->seedMenu(MenuLocation::Header, $locale, [
                [
                    'type' => MenuItemType::Anchor,
                    'anchor' => 'features',
                    'label' => __('common.nav.features', [], $locale),
                ],
                [
                    'type' => MenuItemType::ArticleIndex,
                    'label' => __('common.nav.articles', [], $locale),
                ],
            ]);

            $hasPrivacyTranslation = is_int($privacyPageId) && PageTranslation::query()
                ->where('page_id', $privacyPageId)
                ->where('locale', $locale)
                ->exists();

            if ($hasPrivacyTranslation) {
                $this->seedMenu(MenuLocation::Footer, $locale, [
                    ['type' => MenuItemType::Page, 'page_id' => $privacyPageId],
                ]);
            }
        }

        $navigation->forget();
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
