<?php

namespace App\Models;

use App\Enums\AuditAction;
use Carbon\CarbonImmutable;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only record of an administrative operation. The application never
 * updates or deletes a single entry; only `audit:prune` removes entries past
 * the retention period with a bulk query.
 *
 * `changes` maps a field (e.g. `en.title`, `role`) to either
 * `{old, new}` for non-sensitive values or `{redacted: true}` when only the
 * fact of the change is recorded (e.g. page body).
 *
 * @property int $id
 * @property int|null $actor_id
 * @property AuditAction $action
 * @property string $subject_type
 * @property int|null $subject_id
 * @property array<string, array{old?: mixed, new?: mixed, redacted?: bool}>|null $changes
 * @property CarbonImmutable|null $created_at
 * @property-read User|null $actor
 */
#[Fillable(['actor_id', 'action', 'subject_type', 'subject_id', 'changes'])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'subject_id' => 'integer',
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Audit log entries are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Audit log entries are immutable.');
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
