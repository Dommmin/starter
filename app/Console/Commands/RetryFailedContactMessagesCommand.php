<?php

namespace App\Console\Commands;

use App\Actions\Contact\RetryContactMessage;
use App\Repositories\Contact\ContactMessageRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('contact:retry-failed')]
#[Description('Re-dispatch failed contact messages and pending ones whose delivery job was lost')]
class RetryFailedContactMessagesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ContactMessageRepository $contactMessages, RetryContactMessage $retryContactMessage): int
    {
        $staleMinutes = (int) config('contact.stale_pending_minutes');
        $maxAttempts = (int) config('contact.max_total_attempts');

        if ($staleMinutes < 1 || $maxAttempts < 1) {
            $this->error('contact.stale_pending_minutes and contact.max_total_attempts must be at least 1.');

            return self::INVALID;
        }

        $dispatched = 0;

        foreach ($contactMessages->awaitingRecovery(now()->subMinutes($staleMinutes), $maxAttempts) as $contactMessage) {
            if ($retryContactMessage->handle($contactMessage)) {
                $dispatched++;
            }
        }

        $this->info(sprintf('Re-dispatched %d contact message(s).', $dispatched));

        return self::SUCCESS;
    }
}
