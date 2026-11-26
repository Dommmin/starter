<?php

namespace App\Actions\Faqs;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\Faq;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Copy a faq as a new, unpublished record placed after every existing one,
 * with an audit entry that names the source record. The source is not
 * modified.
 */
class DuplicateFaq
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(Faq $source, User $actor): Faq
    {
        return DB::transaction(function () use ($source, $actor): Faq {
            $copy = $source->replicate(['published', 'position']);
            $copy->published = false;
            $copy->position = $this->nextPosition();
            $copy->save();

            $this->audit->handle(AuditAction::FaqDuplicated, $copy, $actor, [
                'duplicated_from' => RecordAuditEvent::change(null, $source->id),
            ]);

            return $copy;
        });
    }

    /**
     * One past the highest position of all faqs, so the copy is last in every
     * public locale list. Concurrent copies may share a position (it is not
     * unique); the list then falls back to its secondary order.
     */
    private function nextPosition(): int
    {
        $max = Faq::query()->max('position');

        return is_numeric($max) ? (int) $max + 1 : 1;
    }
}
