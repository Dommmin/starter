<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('ops:heartbeat')]
#[Description('Record the scheduler heartbeat checked by the readiness probe')]
class RecordSchedulerHeartbeatCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Cache::forever((string) config('ops.heartbeat.cache_key'), now()->getTimestamp());

        return self::SUCCESS;
    }
}
