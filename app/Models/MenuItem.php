<?php

namespace App\Models;

use App\Enums\MenuItemType;
use App\Enums\MenuLocation;
use Carbon\CarbonImmutable;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One entry of a public navigation menu (header or footer) in one locale.
 * Menus are at most two levels deep: a first-level item may have children,
 * a child never has children of its own.
 *
 * @property int $id
 * @property MenuLocation $location
 * @property string $locale
 * @property int|null $parent_id
 * @property int $position
 * @property MenuItemType $type
 * @property int|null $page_id
 * @property int|null $article_id
 * @property string|null $anchor
 * @property string|null $url
 * @property string|null $label
 * @property bool $open_in_new_tab
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read MenuItem|null $parent
 * @property-read Collection<int, MenuItem> $children
 * @property-read Page|null $page
 * @property-read Article|null $article
 * @property-read Collection<int, PageTranslation> $pageTranslations
 * @property-read Collection<int, ArticleTranslation> $articleTranslations
 */
#[Fillable([
    'location', 'locale', 'parent_id', 'position', 'type', 'page_id', 'article_id',
    'anchor', 'url', 'label', 'open_in_new_tab', 'created_by', 'updated_by',
])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'open_in_new_tab' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'location' => MenuLocation::class,
            'type' => MenuItemType::class,
            'position' => 'integer',
            'open_in_new_tab' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return BelongsTo<Article, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Translations of the target page, joined directly on `page_id` so a
     * menu can be resolved without loading the pages themselves.
     *
     * @return HasMany<PageTranslation, $this>
     */
    public function pageTranslations(): HasMany
    {
        return $this->hasMany(PageTranslation::class, 'page_id', 'page_id');
    }

    /**
     * Translations of the target article, joined directly on `article_id`.
     *
     * @return HasMany<ArticleTranslation, $this>
     */
    public function articleTranslations(): HasMany
    {
        return $this->hasMany(ArticleTranslation::class, 'article_id', 'article_id');
    }

    /**
     * Whether the page/article this item points to has been deleted (the
     * foreign key was nulled by the database).
     */
    public function isTargetMissing(): bool
    {
        return match ($this->type) {
            MenuItemType::Page => $this->page_id === null,
            MenuItemType::Article => $this->article_id === null,
            default => false,
        };
    }
}
