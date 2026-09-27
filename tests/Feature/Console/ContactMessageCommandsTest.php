<?php

use App\Enums\ContactMessageStatus;
use App\Jobs\SendContactMessage;
use App\Models\ContactMessage;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;

test('retry-failed re-dispatches failed and stale pending messages only', function () {
    Queue::fake();
    config(['contact.stale_pending_minutes' => 30, 'contact.max_total_attempts' => 12]);

    $failed = ContactMessage::factory()->failed()->create();
    $stalePending = ContactMessage::factory()->create();
    ContactMessage::query()->whereKey($stalePending->id)->update(['updated_at' => now()->subHour()]);
    $freshPending = ContactMessage::factory()->create();
    $sent = ContactMessage::factory()->sent()->create();
    $exhausted = ContactMessage::factory()->failed()->create(['attempts' => 12]);

    $this->artisan('contact:retry-failed')
        ->expectsOutput('Re-dispatched 2 contact message(s).')
        ->assertSuccessful();

    Queue::assertPushed(SendContactMessage::class, 2);
    Queue::assertPushed(SendContactMessage::class, fn (SendContactMessage $job) => $job->contactMessageId === $failed->id);
    Queue::assertPushed(SendContactMessage::class, fn (SendContactMessage $job) => $job->contactMessageId === $stalePending->id);

    expect($failed->refresh()->status)->toBe(ContactMessageStatus::Pending)
        ->and($freshPending->refresh()->status)->toBe(ContactMessageStatus::Pending)
        ->and($sent->refresh()->status)->toBe(ContactMessageStatus::Sent)
        ->and($exhausted->refresh()->status)->toBe(ContactMessageStatus::Failed);
});

test('prune deletes messages older than the retention period', function () {
    config(['contact.retention_days' => 180]);

    $old = ContactMessage::factory()->create(['created_at' => now()->subDays(181)]);
    $recent = ContactMessage::factory()->create(['created_at' => now()->subDays(179)]);

    $this->artisan('contact:prune')
        ->expectsOutput('Deleted 1 contact messages older than 180 days.')
        ->assertSuccessful();

    expect(ContactMessage::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(ContactMessage::query()->whereKey($recent->id)->exists())->toBeTrue();
});

test('prune refuses an invalid retention period', function () {
    config(['contact.retention_days' => 0]);
    ContactMessage::factory()->create(['created_at' => now()->subYears(2)]);

    $this->artisan('contact:prune')->assertFailed();

    expect(ContactMessage::query()->count())->toBe(1);
});

test('both contact commands are scheduled on one server without overlapping', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'contact:'));

    expect($events)->toHaveCount(2);
    $events->each(fn ($event) => expect($event->onOneServer)->toBeTrue()
        ->and($event->withoutOverlapping)->toBeTrue());
});
