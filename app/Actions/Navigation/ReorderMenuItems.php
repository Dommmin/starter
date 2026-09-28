<?php

namespace App\Actions\Navigation;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Enums\MenuLocation;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Store a new order of the siblings of one level of a menu. The submitted
 * ids must be exactly the current siblings (read under lock); a stale or
 * foreign set is a `conflict` and nothing is stored. Audited as
 * `navigation.reordered` on the parent item, or on the first item of the
 * new order for the first level.
 */
class ReorderMenuItems
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    /**
     * @param  list<int>  $ids  New order of the sibling ids.
     *
     * @throws ValidationException When `ids` differ from the current siblings (`conflict`).
     */
    public function handle(User $actor, MenuLocation $location, string $locale, ?int $parentId, array $ids): void
    {
        DB::transaction(function () use ($actor, $location, $locale, $parentId, $ids): void {
            $siblings = MenuItem::query()
                ->where('location', $location->value)
                ->where('locale', $locale)
                ->where('parent_id', $parentId)
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $current = $siblings->keys()->map(intval(...))->all();
            $expected = $current;
            $submitted = $ids;
            sort($expected);
            sort($submitted);

            if ($siblings->isEmpty() || $expected !== $submitted) {
                throw ValidationException::withMessages([
                    'conflict' => __('admin.navigation.reorderConflict'),
                ]);
            }

            if ($current === $ids) {
                return;
            }

            foreach ($ids as $index => $id) {
                /** @var MenuItem $item */
                $item = $siblings->get($id);
                $item->forceFill([
                    'position' => $index + 1,
                    'updated_by' => $actor->id,
                ])->save();
            }

            $subject = $parentId !== null
                ? MenuItem::query()->findOrFail($parentId)
                : $siblings->get($ids[0]);

            /** @var MenuItem $subject */
            $this->audit->handle(AuditAction::NavigationReordered, $subject, $actor, [
                'order' => RecordAuditEvent::change($current, $ids),
            ]);
        });
    }
}
