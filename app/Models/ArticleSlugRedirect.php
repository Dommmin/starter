<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permanent (301) redirect from a former slug of an article translation.
 * Like page redirects, it targets the translation, so a chain of slug
 * changes always resolves in a single hop to the current slug.
 *
 * @property int $id
 * @property string $locale
 * @property string $old_slug
 * @property int $article_translation_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ArticleTranslation $translation
 */
#[Fillable(['locale', 'old_slug', 'article_translation_id'])]
class ArticleSlugRedirect extends Model
{
    /**
     * @return BelongsTo<ArticleTranslation, $this>
     */
    public function translation(): BelongsTo
    {
        return $this->belongsTo(ArticleTranslation::class, 'article_translation_id');
    }
}
