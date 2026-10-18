<?php

namespace App\Enums;

use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Database\Eloquent\Model;

/**
 * Content that goes to the trash before it is deleted for good.
 */
enum TrashedContentType: string
{
    case Pages = 'pages';
    case Articles = 'articles';

    /**
     * @return class-string<Page|Article>
     */
    public function model(): string
    {
        return match ($this) {
            self::Pages => Page::class,
            self::Articles => Article::class,
        };
    }

    /**
     * Global scope of the translations that hides those of trashed records.
     */
    public function translationScope(): string
    {
        return match ($this) {
            self::Pages => PageTranslation::LIVE_PARENT_SCOPE,
            self::Articles => ArticleTranslation::LIVE_PARENT_SCOPE,
        };
    }

    public function restoredAction(): AuditAction
    {
        return match ($this) {
            self::Pages => AuditAction::PageRestored,
            self::Articles => AuditAction::ArticleRestored,
        };
    }

    public function purgedAction(): AuditAction
    {
        return match ($this) {
            self::Pages => AuditAction::PagePurged,
            self::Articles => AuditAction::ArticlePurged,
        };
    }

    public static function of(Model $record): self
    {
        return $record instanceof Page ? self::Pages : self::Articles;
    }
}
