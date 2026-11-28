<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ContentPreviewLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A revocable link that shows the saved translation of a page or article to
 * someone without an account. Only the hash of its token is stored.
 *
 * @property int $id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $locale
 * @property string $token_hash
 * @property int|null $created_by
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable $created_at
 * @property-read User|null $creator
 */
#[Fillable(['subject_type', 'subject_id', 'locale', 'token_hash', 'created_by', 'expires_at'])]
#[Hidden(['token_hash'])]
class ContentPreviewLink extends Model
{
    /** @use HasFactory<ContentPreviewLinkFactory> */
    use HasFactory;

    public const ?string UPDATED_AT = null;

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime'];
    }
}
