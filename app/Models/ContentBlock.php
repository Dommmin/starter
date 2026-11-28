<?php

namespace App\Models;

use App\Casts\HomeSectionContent;
use App\Enums\HomeSectionType;
use Carbon\CarbonImmutable;
use Database\Factories\ContentBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\LaravelData\Data;

/**
 * Section content shared by several page sections of one language. Each
 * linked section stores its own copy, kept equal to the block by
 * ContentBlocks, so rendering never needs to know about blocks.
 *
 * @property int $id
 * @property string $name
 * @property string $locale
 * @property HomeSectionType $type
 * @property Data $content
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'locale', 'type', 'content'])]
class ContentBlock extends Model
{
    /** @use HasFactory<ContentBlockFactory> */
    use HasFactory;

    /**
     * @return HasMany<PageSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => HomeSectionType::class,
            'content' => HomeSectionContent::class,
        ];
    }
}
