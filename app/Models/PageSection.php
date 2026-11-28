<?php

namespace App\Models;

use App\Casts\HomeSectionContent;
use App\Enums\HomeSectionType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\LaravelData\Data;

/**
 * Reusable section attached to a content page (`layout_mode = 'sections'`).
 * Shares the same closed Data schemas and renderer as HomeSection.
 *
 * @property int $id
 * @property int $page_id
 * @property string $locale
 * @property HomeSectionType $type
 * @property int $position
 * @property bool $enabled
 * @property Data $content
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Page $page
 */
#[Fillable(['page_id', 'locale', 'type', 'position', 'enabled', 'content'])]
class PageSection extends Model
{
    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => HomeSectionType::class,
            'position' => 'integer',
            'enabled' => 'boolean',
            'content' => HomeSectionContent::class,
        ];
    }
}
