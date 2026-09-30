<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SiteSettingFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Site-wide settings managed by administrators: brand name, logo, public
 * contact details, social profiles, default SEO image and the recipient of
 * contact form messages. Singleton: the only row has id
 * {@see self::SINGLETON_ID}; the application never inserts another one.
 * Attributes are written by UpdateSiteSettings only (no mass assignment).
 *
 * @property int $id
 * @property string $site_name
 * @property int|null $logo_media_id
 * @property int|null $og_image_media_id
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $address_line
 * @property string|null $postal_code
 * @property string|null $city
 * @property string|null $country_code
 * @property string|null $contact_recipient_email
 * @property array<string, string>|null $social_links Keyed by SocialNetwork value.
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, SiteSettingTranslation> $translations
 * @property-read MediaAsset|null $logo
 * @property-read MediaAsset|null $ogImage
 */
class SiteSetting extends Model
{
    /** @use HasFactory<SiteSettingFactory> */
    use HasFactory;

    public const int SINGLETON_ID = 1;

    public $incrementing = false;

    protected $keyType = 'int';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'site_name' => 'string',
            'logo_media_id' => 'integer',
            'og_image_media_id' => 'integer',
            'contact_email' => 'string',
            'contact_phone' => 'string',
            'address_line' => 'string',
            'postal_code' => 'string',
            'city' => 'string',
            'country_code' => 'string',
            'contact_recipient_email' => 'string',
            'social_links' => 'array',
            'updated_by' => 'integer',
        ];
    }

    /**
     * @return HasMany<SiteSettingTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(SiteSettingTranslation::class);
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'logo_media_id');
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'og_image_media_id');
    }
}
