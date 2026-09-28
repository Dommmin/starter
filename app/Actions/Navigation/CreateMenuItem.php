<?php

namespace App\Actions\Navigation;

use App\Actions\Audit\RecordAuditEvent;
use App\Data\Navigation\MenuItemInputData;
use App\Enums\AuditAction;
use App\Enums\MenuLocation;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Add an item at the end of its level in one menu, recording
 * `navigation.item_created` in the same transaction.
 */
class CreateMenuItem
{
    public function __construct(
        private readonly GuardMenuItemParent $guard,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @throws ValidationException When the parent breaks the menu structure.
     */
    public function handle(User $actor, MenuLocation $location, string $locale, MenuItemInputData $input): MenuItem
    {
        return DB::transaction(function () use ($actor, $location, $locale, $input): MenuItem {
            $this->guard->handle($input->parentId, $location, $locale, $input->type);

            $item = MenuItem::query()->create([
                ...$input->attributes(),
                'location' => $location,
                'locale' => $locale,
                'position' => $this->guard->nextPosition($location, $locale, $input->parentId),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $changes = [];
            foreach (UpdateMenuItem::AUDITED_FIELDS as $field) {
                $changes[$field] = RecordAuditEvent::change(null, UpdateMenuItem::auditValue($item, $field));
            }

            $this->audit->handle(AuditAction::NavigationItemCreated, $item, $actor, $changes);

            return $item;
        });
    }
}
