<?php

namespace App\Actions\Faqs;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\Faq;
use App\Models\User;
use App\Services\Ordering\ReorderRecords;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Store a new shared order of all faqs (one sequence for every language;
 * each public locale list is a subset of it). The submitted ids must be
 * exactly the current faqs (read under lock); a stale or forged set is a
 * `conflict` and nothing is stored. Positions become 1..N in the new order;
 * a changed order is audited once as `faq.reordered` on the first faq of
 * the new order.
 */
class ReorderFaqs
{
    public function __construct(
        private readonly ReorderRecords $reorder,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  list<int>  $ids  New order of every faq id, first shown first.
     *
     * @throws ValidationException When `ids` differ from the current faqs (`conflict`).
     */
    public function handle(User $actor, array $ids): void
    {
        $this->reorder->handle(
            Faq::class,
            [],
            $ids,
            __('admin.faqs.orderConflict'),
            function (Collection $faqs, array $current) use ($actor, $ids): void {
                foreach ($ids as $index => $id) {
                    /** @var Faq $faq */
                    $faq = $faqs->get($id);
                    $position = $index + 1;

                    if ($faq->position !== $position) {
                        $faq->forceFill(['position' => $position])->save();
                    }
                }

                if ($current === $ids) {
                    return;
                }

                /** @var Faq $subject */
                $subject = $faqs->get($ids[0]);

                $this->audit->handle(AuditAction::FaqsReordered, $subject, $actor, [
                    'order' => RecordAuditEvent::change($current, $ids),
                ]);
            },
        );
    }
}
