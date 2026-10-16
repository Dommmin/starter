<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Article tag (e.g. `news`, `guides`). The slug is shared by every locale
 * and filters the public article list; the name is translated.
 *
 * @property int $id
 * @property string $slug
 * @property array<string, string> $name Keyed by locale.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Article> $articles
 */
#[Fillable(['slug', 'name'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'array',
        ];
    }

    /**
     * @return BelongsToMany<Article, $this>
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class);
    }

    /**
     * Sorted slugs of the given tags (audit entries name tags by slug).
     *
     * @param  list<int>  $ids
     * @return list<string>
     */
    public static function slugsOf(array $ids): array
    {
        /** @var list<string> */
        return $ids === [] ? [] : self::query()->whereIn('id', $ids)->orderBy('slug')->pluck('slug')->all();
    }

    /**
     * Name in the locale, then the fallback locale, then the slug.
     */
    public function nameIn(string $locale, string $fallback): string
    {
        return $this->name[$locale] ?? $this->name[$fallback] ?? $this->slug;
    }
}
