<?php

namespace App\Models;

use App\Enums\MediaStatus;
use Carbon\CarbonImmutable;
use Database\Factories\MediaAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * File in the administrative DAM. The original always stays on the private
 * media disk; only clean images get public, content-hashed variants.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $owner_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property string $checksum
 * @property MediaStatus $status
 * @property string|null $scan_error
 * @property string|null $alt
 * @property list<array{format: string, width: int, height: int, path: string, size: int}>|null $variants
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $owner
 */
#[Fillable(['alt'])]
class MediaAsset extends Model
{
    /** @use HasFactory<MediaAssetFactory> */
    use HasFactory;

    /**
     * MIME types decoded as raster images (variants, dimensions, picker).
     *
     * @var list<string>
     */
    public const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'status' => MediaStatus::class,
            'variants' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function isImage(): bool
    {
        return in_array($this->mime, self::IMAGE_MIMES, true);
    }

    public function hasPassedScan(): bool
    {
        return $this->status === MediaStatus::Clean;
    }

    /**
     * Clean images that already have their public variants.
     *
     * @param  Builder<MediaAsset>  $query
     */
    public function scopeUsableImages(Builder $query): void
    {
        $query->where('status', MediaStatus::Clean->value)
            ->whereIn('mime', self::IMAGE_MIMES)
            ->whereNotNull('variants');
    }
}
