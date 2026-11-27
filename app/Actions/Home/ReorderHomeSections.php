<?php

namespace App\Actions\Home;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\HomeSection;
use App\Models\User;
use App\Services\Ordering\ReorderRecords;
use Illuminate\Database\Eloquent\Collection;
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
        private readonly ReorderRecords $reorder,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  list<int>  $ids  New order, first shown first.
     *
     * @throws ValidationException When `ids` is not exactly the current set (`conflict`).
     */
    public function handle(string $locale, array $ids, User $actor): void
    {
        $this->reorder->handle(
            HomeSection::class,
            ['locale' => $locale],
            $ids,
            __('admin.homeSections.orderConflict'),
            function (Collection $sections) use ($ids, $actor): void {
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
            },
        );
    }
}
