<?php

namespace App\Models;

use App\Casts\JsonArray;
use Carbon\CarbonImmutable;
use Database\Factories\ContentRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The content of a page or article as it was before one change. Rows are
 * never edited; only the newest ones of each record are kept.
 *
 * @property int $id
 * @property string $subject_type
 * @property int $subject_id
 * @property int|null $author_id The user whose change replaced this content.
 * @property array<string, mixed> $snapshot
 * @property CarbonImmutable $created_at
 * @property-read User|null $author
 */
#[Fillable(['subject_type', 'subject_id', 'author_id', 'snapshot'])]
class ContentRevision extends Model
{
    /** @use HasFactory<ContentRevisionFactory> */
    use HasFactory;

    public const ?string UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['snapshot' => JsonArray::class];
    }
}
