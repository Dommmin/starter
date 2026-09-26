<?php

namespace App\Data\Admin\Audit;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the admin audit list. Only the names of the changed fields are
 * exposed; recorded values stay in the database.
 */
#[TypeScript]
class AuditLogListItemData extends Data
{
    /**
     * @param  string|null  $actorName  Null for console/system entries or a deleted actor.
     * @param  string  $subjectType  Short subject name, e.g. `page` or `user`.
     * @param  list<string>  $changedFields
     */
    public function __construct(
        public int $id,
        public ?string $createdAt,
        public ?string $actorName,
        public AuditAction $action,
        public string $subjectType,
        public ?int $subjectId,
        public array $changedFields,
    ) {}

    /**
     * Requires the `actor` relation to be eager loaded.
     */
    public static function fromAuditLog(AuditLog $log): self
    {
        return new self(
            id: $log->id,
            createdAt: $log->created_at?->toIso8601String(),
            actorName: $log->actor?->name,
            action: $log->action,
            subjectType: strtolower(class_basename($log->subject_type)),
            subjectId: $log->subject_id,
            changedFields: array_map(strval(...), array_keys($log->changes ?? [])),
        );
    }
}
