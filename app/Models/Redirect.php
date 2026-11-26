<?php

namespace App\Models;

use App\Enums\RedirectStatus;
use App\Repositories\Redirects\RedirectRepository;
use Carbon\CarbonImmutable;
use Database\Factories\RedirectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Redirect of a public path (e.g. an address of the client's former site)
 * to a page of this site or an external URL, served by RedirectManagedPaths
 * before routing. `source_path` is stored normalized (RedirectPath::source());
 * a save or delete drops the cached redirect map after the commit.
 *
 * @property int $id
 * @property string $source_path
 * @property string $target Relative path (`/offer?x=1`) or absolute http(s) URL.
 * @property RedirectStatus $status_code
 * @property bool $is_active
 * @property int $hits
 * @property CarbonImmutable|null $last_hit_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['source_path', 'target', 'status_code', 'is_active'])]
class Redirect extends Model
{
    /** @use HasFactory<RedirectFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        $forget = static function (): void {
            DB::afterCommit(static fn () => app(RedirectRepository::class)->forget());
        };

        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_code' => RedirectStatus::class,
            'is_active' => 'boolean',
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }
}
