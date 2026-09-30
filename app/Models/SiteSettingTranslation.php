<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SiteSettingTranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Translated texts of the site settings for one public locale. The site
 * name itself is not translatable.
 *
 * @property int $id
 * @property int $site_setting_id
 * @property string $locale
 * @property string|null $tagline
 * @property string|null $footer_text
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read SiteSetting $siteSetting
 */
#[Fillable(['locale', 'tagline', 'footer_text', 'seo_title', 'seo_description'])]
class SiteSettingTranslation extends Model
{
    /** @use HasFactory<SiteSettingTranslationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'site_setting_id' => 'integer',
            'locale' => 'string',
            'tagline' => 'string',
            'footer_text' => 'string',
            'seo_title' => 'string',
            'seo_description' => 'string',
        ];
    }

    /**
     * @return BelongsTo<SiteSetting, $this>
     */
    public function siteSetting(): BelongsTo
    {
        return $this->belongsTo(SiteSetting::class);
    }
}
