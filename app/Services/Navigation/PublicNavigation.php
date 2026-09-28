<?php

namespace App\Services\Navigation;

use App\Data\Navigation\NavigationData;
use App\Data\Navigation\NavigationItemData;
use App\Enums\MenuItemType;
use App\Enums\MenuLocation;
use App\Models\MenuItem;
use App\Repositories\Navigation\MenuItemRepository;
use App\Services\Localization\LocalizationConfig;
use App\Services\Localization\LocalizedUrlGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Resolves the public navigation menus of a locale into visible entries
 * with localized URLs, cached per menu as plain arrays.
 *
 * Hidden: items whose page/article target is deleted, unpublished,
 * scheduled for later or untranslated in the locale, external links that
 * fail the URL check, children of hidden first-level items and groups
 * without visible children. The cache lives at most one hour, and only
 * until the nearest scheduled publication of a linked article; mutations
 * of menus and content drop it (NavigationCacheObserver).
 */
class PublicNavigation
{
    public const int MAX_TTL_SECONDS = 3600;

    public function __construct(
        private readonly MenuItemRepository $items,
        private readonly LocalizedUrlGenerator $urls,
        private readonly LocalizationConfig $config,
        private readonly Cache $cache,
    ) {}

    /**
     * The shared `navigation` prop of public pages.
     */
    public function shared(string $locale): NavigationData
    {
        return new NavigationData(
            header: self::toData($this->for(MenuLocation::Header, $locale)),
            footer: self::toData($this->for(MenuLocation::Footer, $locale)),
        );
    }

    /**
     * Visible entries of one menu (cached).
     *
     * @return list<array<string, mixed>>
     */
    public function for(MenuLocation $location, string $locale): array
    {
        $key = self::cacheKey($location, $locale);
        $cached = $this->cache->get($key);

        if (is_array($cached)) {
            /** @var list<array<string, mixed>> $cached */
            return $cached;
        }

        [$entries, $ttl] = $this->resolve($location, $locale);
        $this->cache->put($key, $entries, $ttl);

        return $entries;
    }

    /**
     * Drop the cached menus of every location and public locale.
     */
    public function forget(): void
    {
        foreach (MenuLocation::cases() as $location) {
            foreach ($this->config->getPublicLocales() as $locale) {
                $this->cache->forget(self::cacheKey($location, $locale));
            }
        }
    }

    public static function cacheKey(MenuLocation $location, string $locale): string
    {
        return "navigation:{$location->value}:{$locale}";
    }

    /**
     * An absolute http(s) URL with a host and without whitespace or control
     * characters. Rejects `javascript:`, `data:` and protocol-relative
     * `//host` links; used on input and again on output.
     */
    public static function isSafeExternalUrl(?string $url): bool
    {
        if ($url === null || $url === '' || strlen($url) > 2048 || preg_match('/[\s\x00-\x1F\x7F]/', $url) === 1) {
            return false;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || $parts['host'] === '') {
            return false;
        }

        $scheme = strtolower($parts['scheme']);

        return in_array($scheme, ['http', 'https'], true)
            && str_starts_with(strtolower($url), $scheme.'://');
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: int} Entries and cache TTL in seconds.
     */
    private function resolve(MenuLocation $location, string $locale): array
    {
        $now = CarbonImmutable::now();
        $nextPublication = null;
        $items = $this->items->publicMenu($location, $locale);
        $childrenByParent = $items->whereNotNull('parent_id')->groupBy('parent_id');

        $entries = [];
        foreach ($items->whereNull('parent_id') as $root) {
            $entry = $this->resolveItem($root, $locale, $now, $nextPublication);

            if ($entry === null) {
                continue;
            }

            $children = [];
            foreach ($childrenByParent->get($root->id) ?? [] as $child) {
                $childEntry = $this->resolveItem($child, $locale, $now, $nextPublication);

                if ($childEntry !== null && $childEntry['kind'] !== 'group') {
                    $children[] = $childEntry;
                }
            }

            if ($entry['kind'] === 'group' && $children === []) {
                continue;
            }

            $entry['children'] = $children;
            $entries[] = $entry;
        }

        $ttl = self::MAX_TTL_SECONDS;
        if ($nextPublication !== null) {
            $ttl = max(1, min($ttl, (int) ceil($now->diffInSeconds($nextPublication))));
        }

        return [$entries, $ttl];
    }

    /**
     * @param-out CarbonImmutable|null $nextPublication
     *
     * @return array<string, mixed>|null
     */
    private function resolveItem(MenuItem $item, string $locale, CarbonImmutable $now, ?CarbonImmutable &$nextPublication): ?array
    {
        $label = trim((string) $item->label);
        $href = null;
        $kind = 'internal';

        switch ($item->type) {
            case MenuItemType::Page:
                $translation = $item->page_id === null ? null : $item->pageTranslations->first();
                if ($translation === null) {
                    return null;
                }
                $href = $this->urls->url('pages.show', ['slug' => $translation->slug], $locale);
                $label = $label !== '' ? $label : $translation->title;
                break;

            case MenuItemType::Article:
                $translation = $item->article_id === null ? null : $item->articleTranslations->first();
                if ($translation === null || $translation->published_at === null) {
                    return null;
                }
                if ($translation->published_at->greaterThan($now)) {
                    $publishesAt = CarbonImmutable::instance($translation->published_at);
                    if ($nextPublication === null || $publishesAt->lessThan($nextPublication)) {
                        $nextPublication = $publishesAt;
                    }

                    return null;
                }
                $href = $this->urls->url('articles.show', ['slug' => $translation->slug], $locale);
                $label = $label !== '' ? $label : $translation->title;
                break;

            case MenuItemType::ArticleIndex:
                $href = $this->urls->url('articles.index', [], $locale);
                break;

            case MenuItemType::Anchor:
                if ($item->anchor === null || preg_match('/^[a-z0-9-]+$/', $item->anchor) !== 1) {
                    return null;
                }
                if ($item->page_id === null) {
                    $base = $this->urls->url('home', [], $locale);
                } else {
                    $translation = $item->pageTranslations->first();
                    if ($translation === null) {
                        return null;
                    }
                    $base = $this->urls->url('pages.show', ['slug' => $translation->slug], $locale);
                }
                $href = $base.'#'.$item->anchor;
                $kind = 'anchor';
                break;

            case MenuItemType::External:
                if (! self::isSafeExternalUrl($item->url)) {
                    return null;
                }
                $href = $item->url;
                $kind = 'external';
                break;

            case MenuItemType::Group:
                $kind = 'group';
                break;
        }

        if ($label === '') {
            return null;
        }

        $entry = [
            'id' => $item->id,
            'label' => $label,
            'kind' => $kind,
            'newTab' => $item->type === MenuItemType::External && $item->open_in_new_tab,
            'children' => [],
        ];

        if ($href !== null) {
            $entry['href'] = $href;
        }

        return $entry;
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return list<NavigationItemData>
     */
    private static function toData(array $entries): array
    {
        return array_map(NavigationItemData::fromArray(...), $entries);
    }
}
