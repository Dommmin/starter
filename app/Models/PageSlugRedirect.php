<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permanent (301) redirect from a former slug of a page translation.
 *
 * The redirect targets the translation, not a slug, so a chain of slug
 * changes always resolves in a single hop to the current slug.
 *
 * @property int $id
 * @property string $locale
 * @property string $old_slug
 * @property int $page_translation_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read PageTranslation $translation
 */
#[Fillable(['locale', 'old_slug', 'page_translation_id'])]
class PageSlugRedirect extends Model
{
    /**
     * @return BelongsTo<PageTranslation, $this>
     */
    public function translation(): BelongsTo
    {
        return $this->belongsTo(PageTranslation::class, 'page_translation_id');
    }
}
