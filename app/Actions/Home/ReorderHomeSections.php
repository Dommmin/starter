<?php

namespace App\Actions\Home;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\HomeSection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Apply a complete new order to the sections of one locale in a single
 * transaction. `ids` must be exactly the locale's current set of sections
 * (no foreign, missing or duplicate id); otherwise nothing is written and a
 * `conflict` error is raised (the list changed or the request is forged).
 * Each moved section is audited as `home_section.reordered`.
 */
class ReorderHomeSections
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  list<int>  $ids  New order, first shown first.
     *
     * @throws ValidationException When `ids` is not exactly the current set (`conflict`).
     */
    public function handle(string $locale, array $ids, User $actor): void
    {
        DB::transaction(function () use ($locale, $ids, $actor): void {
            $sections = HomeSection::query()
                ->where('locale', $locale)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $current = $sections->keys()->map(intval(...))->sort()->values()->all();
            $requested = collect($ids)->sort()->values()->all();

            if ($current !== $requested) {
                throw ValidationException::withMessages([
                    'conflict' => __('admin.homeSections.orderConflict'),
                ]);
            }

            foreach ($ids as $index => $id) {
                /** @var HomeSection $section */
                $section = $sections->get($id);
                $position = $index + 1;

                if ($section->position === $position) {
                    continue;
                }

                $old = $section->position;
                $section->forceFill(['position' => $position])->save();

                $this->audit->handle(AuditAction::HomeSectionsReordered, $section, $actor, [
                    'position' => RecordAuditEvent::change($old, $position),
                ]);
            }
        });
    }
}
