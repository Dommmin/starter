<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Aggregated 404 hits for public paths so administrators can spot broken
 * links and create 301 redirects in one click.
 *
 * @property int $id
 * @property string $path
 * @property int $hits
 * @property string|null $last_referer
 * @property CarbonImmutable $last_seen_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['path', 'hits', 'last_referer', 'last_seen_at'])]
class NotFoundLog extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hits' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }
}
