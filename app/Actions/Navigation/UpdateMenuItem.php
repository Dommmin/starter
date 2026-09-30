<?php

namespace App\Actions\Navigation;

use App\Actions\Audit\RecordAuditEvent;
use App\Data\Navigation\MenuItemInputData;
use App\Enums\AuditAction;
use App\Models\MenuItem;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Update a menu item with optimistic locking on `updated_at`. Moving it to
 * another parent appends it at the end of the new level. Changed fields are
 * audited as `navigation.item_updated` in the same transaction.
 */
class UpdateMenuItem
{
    /**
     * Fields whose old and new values are stored in the audit log (all of
     * them are editor-visible, non-sensitive values).
     *
     * @var list<string>
     */
    public const array AUDITED_FIELDS = [
        'location', 'locale', 'parent_id', 'type', 'page_id', 'article_id',
        'anchor', 'url', 'label', 'open_in_new_tab',
    ];

    public function __construct(
        private readonly GuardMenuItemParent $guard,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  string  $expectedUpdatedAt  Version the form was loaded with.
     *
     * @throws ValidationException When the item changed in the meantime (`conflict`)
     *                             or the parent breaks the menu structure.
     */
    public function handle(MenuItem $item, User $actor, MenuItemInputData $input, string $expectedUpdatedAt): MenuItem
    {
        return DB::transaction(function () use ($item, $actor, $input, $expectedUpdatedAt): MenuItem {
            $locked = MenuItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ($locked->updated_at?->getTimestamp() !== Carbon::parse($expectedUpdatedAt)->getTimestamp()) {
                throw ValidationException::withMessages([
                    'conflict' => __('admin.navigation.conflict'),
                ]);
            }

            $this->guard->handle($input->parentId, $locked->location, $locked->locale, $input->type, $locked);

            $old = [];
            foreach (self::AUDITED_FIELDS as $field) {
                $old[$field] = self::auditValue($locked, $field);
            }

            $locked->fill($input->attributes());

            if ($locked->isDirty('parent_id')) {
                $locked->position = $this->guard->nextPosition($locked->location, $locked->locale, $input->parentId);
            }

            $changes = [];
            foreach (self::AUDITED_FIELDS as $field) {
                $new = self::auditValue($locked, $field);
                if ($new !== $old[$field]) {
                    $changes[$field] = RecordAuditEvent::change($old[$field], $new);
                }
            }

            $locked->forceFill([
                'updated_by' => $actor->id,
                'updated_at' => $locked->freshTimestamp(),
            ])->save();

            if ($changes !== []) {
                $this->audit->handle(AuditAction::NavigationItemUpdated, $locked, $actor, $changes);
            }

            return $locked;
        });
    }

    /**
     * Scalar audit value of a field (enums as their value).
     */
    public static function auditValue(MenuItem $item, string $field): mixed
    {
        $value = $item->getAttribute($field);

        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
