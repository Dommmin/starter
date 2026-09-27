<?php

use App\Jobs\SendContactMessage;
use App\Listeners\ReportFailedJob;
use App\Listeners\ReportLongQueueWait;
use App\Mail\OpsAlertMail;
use App\Services\Ops\OpsAlerter;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Horizon\Events\LongWaitDetected;

function failedJobEvent(): JobFailed
{
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('resolveName')->andReturn(SendContactMessage::class);
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('uuid')->andReturn('00000000-0000-4000-8000-000000000001');

    return new JobFailed('redis', $job, new RuntimeException('SMTP rejected jane@example.test'));
}

/*
 * Listeners are invoked directly: Horizon's own listeners for the same
 * events write to Redis, which the test environment does not provide.
 */
test('the failed job and long wait listeners are registered', function () {
    Event::fake();

    Event::assertListening(JobFailed::class, ReportFailedJob::class);
    Event::assertListening(LongWaitDetected::class, ReportLongQueueWait::class);
});

test('a failed job is logged as critical without the payload or exception message', function () {
    Mail::fake();
    Log::spy();

    app(ReportFailedJob::class)->handle(failedJobEvent());

    Log::shouldHaveReceived('critical')->once()->withArgs(fn (string $message, array $context): bool => $message === 'ops.queue.job_failed'
        && $context['job'] === SendContactMessage::class
        && $context['queue'] === 'default'
        && $context['exception'] === RuntimeException::class
        && ! str_contains(json_encode($context), 'jane@example.test'));
    Mail::assertNothingSent();
});

test('with OPS_ALERT_EMAIL a deduplicated alert mail is sent synchronously', function () {
    config(['ops.alerts.mail_to' => 'ops@example.test']);
    Mail::fake();

    app(ReportFailedJob::class)->handle(failedJobEvent());
    app(ReportFailedJob::class)->handle(failedJobEvent());

    Mail::assertSent(OpsAlertMail::class, 1);
    Mail::assertSent(OpsAlertMail::class, fn (OpsAlertMail $mail): bool => $mail->hasTo('ops@example.test')
        && $mail->event === 'ops.queue.job_failed');
    Mail::assertNothingQueued();

    expect((new OpsAlertMail('ops.queue.job_failed', ['job' => 'X'], 'testing', null))->render())
        ->toContain('ops.queue.job_failed')
        ->toContain('job: X');
});

test('a horizon long wait raises an operator alert', function () {
    Log::spy();

    app(ReportLongQueueWait::class)->handle(new LongWaitDetected('redis', 'default', 420));

    Log::shouldHaveReceived('critical')->once()->with('ops.queue.long_wait', [
        'connection' => 'redis',
        'queue' => 'default',
        'wait_seconds' => 420,
    ]);
});

test('a failing alert mail never breaks the caller', function () {
    config(['ops.alerts.mail_to' => 'ops@example.test']);
    Mail::shouldReceive('to')->andThrow(new RuntimeException('mailer down'));
    Log::spy();

    app(OpsAlerter::class)->critical('backup.failed');

    Log::shouldHaveReceived('critical')->once()->with('ops.backup.failed', []);
    Log::shouldHaveReceived('warning')->once()->with('ops.alert_mail_failed', [
        'alert' => 'ops.backup.failed',
        'exception' => RuntimeException::class,
    ]);
});
