<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Language-independent identity of a content page. Everything visible to
 * visitors (title, slug, body, SEO, publication) lives in PageTranslation.
 *
 * @property int $id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property string $layout_mode
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at In the trash since.
 * @property-read Collection<int, PageTranslation> $translations
 * @property-read Collection<int, PageSection> $sections
 */
#[Fillable(['created_by', 'updated_by', 'layout_mode'])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return HasMany<PageTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(PageTranslation::class);
    }

    /**
     * @return HasMany<PageSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class);
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
    public function translation(string $locale): ?PageTranslation
    {
        return $this->translations->firstWhere('locale', $locale);
    }
}
