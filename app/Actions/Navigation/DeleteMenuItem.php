<?php

namespace App\Actions\Navigation;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Permanently delete a menu item together with its children (FK cascade),
 * recording `navigation.item_deleted` in the same transaction.
 */
class DeleteMenuItem
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(MenuItem $item, User $actor): void
    {
        DB::transaction(function () use ($item, $actor): void {
            $locked = MenuItem::query()->whereKey($item->id)->lockForUpdate()->first();

            if ($locked === null) {
                return;
            }

            $changes = [];
            foreach (UpdateMenuItem::AUDITED_FIELDS as $field) {
                $changes[$field] = RecordAuditEvent::change(UpdateMenuItem::auditValue($locked, $field), null);
            }

            $childIds = $locked->children()->orderBy('position')->pluck('id')->all();
            if ($childIds !== []) {
                $changes['children'] = RecordAuditEvent::change($childIds, null);
            }

            $this->audit->handle(AuditAction::NavigationItemDeleted, $locked, $actor, $changes);

            $locked->delete();
        });
    }
}
