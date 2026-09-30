<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ArticleTranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One language version of an article with its own slug and publication
 * state. A published translation becomes visible at `published_at`, which
 * may be scheduled in the future.
 *
 * @property int $id
 * @property int $article_id
 * @property string $locale
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string|null $meta_description
 * @property array<string, mixed>|null $body
 * @property PublicationStatus $status
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Article $article
 * @property-read Collection<int, ArticleSlugRedirect> $slugRedirects
 */
#[Fillable(['locale', 'title', 'slug', 'excerpt', 'meta_description', 'body', 'status', 'published_at'])]
class ArticleTranslation extends Model
{
    /** @use HasFactory<ArticleTranslationFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'body' => 'array',
            'status' => PublicationStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Article, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Former slugs that permanently redirect to this translation.
     *
     * @return HasMany<ArticleSlugRedirect, $this>
     */
    public function slugRedirects(): HasMany
    {
        return $this->hasMany(ArticleSlugRedirect::class);
    }

    /**
     * Limit the query to translations visible to visitors now: published
     * and with a publication date that is not in the future.
     *
     * @param  Builder<ArticleTranslation>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PublicationStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Whether the translation is marked as published (it may still be
     * scheduled for a future date).
     */
    public function isPublished(): bool
    {
        return $this->status === PublicationStatus::Published;
    }
}
