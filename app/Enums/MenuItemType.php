<?php

namespace App\Enums;

/**
 * What a navigation menu item points to.
 *
 * - `Page` / `Article` — a CMS page or article, resolved per locale,
 * - `ArticleIndex` — the public article list,
 * - `Anchor` — a fragment on the home page or on a selected page,
 * - `External` — an absolute http(s) URL,
 * - `Group` — a first-level column heading without a link.
 */
enum MenuItemType: string
{
    case Page = 'page';
    case Article = 'article';
    case ArticleIndex = 'article_index';
    case Anchor = 'anchor';
    case External = 'external';
    case Group = 'group';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Types without a titled target of their own; their label is required.
     */
    public function requiresLabel(): bool
    {
        return ! in_array($this, [self::Page, self::Article], true);
    }
}
