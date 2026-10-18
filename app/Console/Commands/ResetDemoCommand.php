<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:reset')]
#[Description('Rebuild the public demo database with fresh sample content (only when APP_DEMO is on)')]
class ResetDemoCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! config('demo.enabled')) {
            $this->error('demo:reset runs only on the public demo (APP_DEMO=true). Nothing was changed.');

            return self::FAILURE;
        }

        $this->call('migrate:fresh', ['--force' => true, '--seed' => true]);
        $this->info('The demo was reset.');

        return self::SUCCESS;
    }
}
