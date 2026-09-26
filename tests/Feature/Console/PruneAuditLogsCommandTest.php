<?php

use App\Models\AuditLog;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

test('the prune command deletes only entries older than the retention period', function () {
    config(['audit.retention_days' => 30]);
    $this->freezeSecond();

    $expired = AuditLog::factory()->create(['created_at' => now()->subDays(30)->subSecond()]);
    $boundary = AuditLog::factory()->create(['created_at' => now()->subDays(30)]);
    $recent = AuditLog::factory()->create(['created_at' => now()->subDay()]);

    $this->artisan('audit:prune')
        ->expectsOutputToContain('Deleted 1 audit log entries')
        ->assertSuccessful();

    expect(AuditLog::query()->whereKey($expired->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->pluck('id')->all())->toEqualCanonicalizing([$boundary->id, $recent->id]);
});

test('the prune command refuses a retention period below one day', function () {
    config(['audit.retention_days' => 0]);
    $log = AuditLog::factory()->create(['created_at' => now()->subYears(2)]);

    $this->artisan('audit:prune')->assertFailed();

    expect(AuditLog::query()->whereKey($log->id)->exists())->toBeTrue();
});

test('the prune command is scheduled daily on one server without overlapping', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, 'audit:prune'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 0 * * *')
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->withoutOverlapping)->toBeTrue();
});
