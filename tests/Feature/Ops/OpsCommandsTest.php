<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

function scheduledEvent(string $command): ?Event
{
    return collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, $command));
}

test('the heartbeat command stores the current timestamp', function () {
    $this->freezeSecond();

    $this->artisan('ops:heartbeat')->assertSuccessful();

    expect((int) Cache::get('ops:heartbeat:scheduler'))->toBe(now()->getTimestamp());
});

test('operational commands are scheduled on one server', function (string $command, string $expression) {
    $event = scheduledEvent($command);

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe($expression)
        ->and($event->onOneServer)->toBeTrue();
})->with([
    ['ops:heartbeat', '* * * * *'],
    ['ops:check-backup', '0 * * * *'],
    ['horizon:snapshot', '*/5 * * * *'],
]);

test('the backup check is skipped without a configured status file', function () {
    config(['ops.backup.status_file' => null]);
    Log::spy();

    $this->artisan('ops:check-backup')->assertSuccessful();

    Log::shouldNotHaveReceived('critical');
});

test('a missing backup status raises a critical alert', function () {
    config(['ops.backup.status_file' => storage_path('framework/testing/missing-backup-status')]);
    Log::spy();

    $this->artisan('ops:check-backup')->assertFailed();

    Log::shouldHaveReceived('critical')->once()->with('ops.backup.missing', ['max_age_hours' => 26]);
});

test('a stale backup raises a critical alert and a fresh one passes', function () {
    $this->freezeSecond();
    $statusFile = tempnam(sys_get_temp_dir(), 'backup-status');
    config(['ops.backup.status_file' => $statusFile, 'ops.backup.max_age_hours' => 26]);
    Log::spy();

    file_put_contents($statusFile, now()->subHours(27)->toIso8601String());
    $this->artisan('ops:check-backup')->assertFailed();
    Log::shouldHaveReceived('critical')->once()->with('ops.backup.stale', ['age_hours' => 27, 'max_age_hours' => 26]);

    file_put_contents($statusFile, now()->subHours(2)->toIso8601String());
    $this->artisan('ops:check-backup')->assertSuccessful();

    unlink($statusFile);
});
