<?php

namespace App\Models;

use App\Casts\HomeSectionContent;
use App\Enums\HomeSectionType;
use Carbon\CarbonImmutable;
use Database\Factories\HomeSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;

/**
 * One section of the public home page in one locale. Every public locale has
 * exactly one section of each type (unique locale + type); `content` is the
 * closed Data schema of the type (see HomeSectionType::contentClass()).
 *
 * `type` must be listed before `content` when filling, because the cast
 * picks the content schema by type.
 *
 * @property int $id
 * @property string $locale
 * @property HomeSectionType $type
 * @property int $position
 * @property bool $enabled
 * @property Data $content
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['locale', 'type', 'position', 'enabled', 'content'])]
class HomeSection extends Model
{
    /** @use HasFactory<HomeSectionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
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
