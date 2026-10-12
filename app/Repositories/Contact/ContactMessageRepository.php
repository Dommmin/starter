<?php

namespace App\Repositories\Contact;

use App\Data\Admin\Dashboard\ContactCountsData;
use App\Data\Admin\Dashboard\ContactDayData;
use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use Carbon\CarbonImmutable;
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
    public function dashboardCounts(CarbonInterface $now, int $days, int $chartDays = 30): ContactCountsData
    {
        $now = $now->toImmutable();

        /** @var object{recent: int|string, previous: int|string, failed: int|string}|null $row */
        $row = ContactMessage::query()
            ->toBase()
            ->selectRaw('count(case when created_at >= ? then 1 end) as recent', [$now->subDays($days)])
            ->selectRaw('count(case when created_at >= ? and created_at < ? then 1 end) as previous', [$now->subDays($days * 2), $now->subDays($days)])
            ->selectRaw('count(case when status = ? then 1 end) as failed', [ContactMessageStatus::Failed->value])
            ->first();

        return new ContactCountsData(
            recent: (int) ($row->recent ?? 0),
            failed: (int) ($row->failed ?? 0),
            recentDays: $days,
            previous: (int) ($row->previous ?? 0),
            daily: $this->daily($now, $chartDays),
            chartDays: $chartDays,
        );
    }

    /**
     * Messages per day of the last `$days` days (today included), oldest
     * first, with empty days present. Counted in PHP over the window so the
     * query stays portable across databases.
     *
     * @return list<ContactDayData>
     */
    private function daily(CarbonImmutable $now, int $days): array
    {
        $start = $now->startOfDay()->subDays($days - 1);
        $days = collect(range(0, $days - 1))
            ->mapWithKeys(fn (int $offset): array => [$start->addDays($offset)->toDateString() => ['delivered' => 0, 'undelivered' => 0]])
            ->all();

        ContactMessage::query()
            ->where('created_at', '>=', $start)
            ->get(['created_at', 'status'])
            ->each(function (ContactMessage $message) use (&$days): void {
                $date = $message->created_at?->toDateString();

                if ($date === null || ! isset($days[$date])) {
                    return;
                }

                $days[$date][$message->status === ContactMessageStatus::Sent ? 'delivered' : 'undelivered']++;
            });

        return array_map(
            fn (string $date, array $counts): ContactDayData => new ContactDayData($date, $counts['delivered'], $counts['undelivered']),
            array_keys($days),
            $days,
        );
    }

    public function failedCount(): int
    {
        return ContactMessage::query()->where('status', ContactMessageStatus::Failed->value)->count();
    }
}
