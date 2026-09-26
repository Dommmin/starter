<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PageTranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One language version of a page with its own slug and publication state.
 *
 * @property int $id
 * @property int $page_id
 * @property string $locale
 * @property string $title
 * @property string $slug
 * @property string|null $meta_description
 * @property array<string, mixed>|null $body
 * @property PublicationStatus $status
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Page $page
 */
#[Fillable(['locale', 'title', 'slug', 'meta_description', 'body', 'status', 'published_at'])]
class PageTranslation extends Model
{
    /** @use HasFactory<PageTranslationFactory> */
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
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Limit the query to translations visible to visitors.
     *
     * @param  Builder<PageTranslation>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PublicationStatus::Published->value);
    }

    public function isPublished(): bool
    {
        return $this->status === PublicationStatus::Published;
    }
}
