<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Language-independent identity of an article: its cover image and authors.
 * Everything visible to visitors (title, slug, excerpt, body, SEO,
 * publication) lives in ArticleTranslation.
 *
 * @property int $id
 * @property int|null $cover_media_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, ArticleTranslation> $translations
 * @property-read MediaAsset|null $cover
 */
#[Fillable(['cover_media_id', 'created_by', 'updated_by'])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    /**
     * @return HasMany<ArticleTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(ArticleTranslation::class);
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the translation for the given locale from the eager loaded
     * `translations` relation (strict mode forbids lazy loading here).
     */
    public function translation(string $locale): ?ArticleTranslation
    {
        return $this->translations->firstWhere('locale', $locale);
    }
}
