<?php

namespace App\Repositories\Contact;

use App\Data\Admin\Dashboard\ContactCountsData;
use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

/**
 * Named read queries of the Contact module.
 */
class ContactMessageRepository
{
    /**
     * Base query of the admin message list (list columns only; the message
     * body is loaded on the detail screen).
     *
     * @return Builder<ContactMessage>
     */
    public function adminListQuery(): Builder
    {
        return ContactMessage::query()
            ->select(['id', 'name', 'email', 'locale', 'status', 'attempts', 'sent_at', 'created_at', 'updated_at']);
    }

    /**
     * Failed messages and pending messages untouched since `$staleBefore`
     * (their job was lost), below the total attempt cap.
     *
     * @return LazyCollection<int, ContactMessage>
     */
    public function awaitingRecovery(CarbonInterface $staleBefore, int $maxTotalAttempts): LazyCollection
    {
        return ContactMessage::query()
            ->where('attempts', '<', $maxTotalAttempts)
            ->where(function (Builder $query) use ($staleBefore): void {
                $query->where('status', ContactMessageStatus::Failed->value)
                    ->orWhere(function (Builder $query) use ($staleBefore): void {
                        $query->where('status', ContactMessageStatus::Pending->value)
                            ->where('updated_at', '<', $staleBefore);
                    });
            })
            ->orderBy('id')
            ->lazyById(200);
    }

    /**
     * Messages received in the last `$days` days and failed deliveries,
     * computed in a single aggregate query.
     */
    public function dashboardCounts(CarbonInterface $now, int $days): ContactCountsData
    {
        /** @var object{recent: int|string, failed: int|string}|null $row */
        $row = ContactMessage::query()
            ->toBase()
            ->selectRaw('count(case when created_at >= ? then 1 end) as recent', [$now->toImmutable()->subDays($days)])
            ->selectRaw('count(case when status = ? then 1 end) as failed', [ContactMessageStatus::Failed->value])
            ->first();

        return new ContactCountsData(
            recent: (int) ($row->recent ?? 0),
            failed: (int) ($row->failed ?? 0),
            recentDays: $days,
        );
    }
}
