<?php

use App\Enums\ContactMessageStatus;
use App\Jobs\SendContactMessage;
use App\Mail\ContactMessageMail;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function () {
    config(['contact.recipient' => 'office@example.test']);
});

test('the job sends the mailable to the configured recipient with reply-to of the sender', function () {
    Mail::fake();
    $message = ContactMessage::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.test',
        'locale' => 'de',
    ]);

    app()->call([new SendContactMessage($message->id), 'handle']);

    Mail::assertSent(ContactMessageMail::class, function (ContactMessageMail $mail) use ($message) {
        return $mail->hasTo('office@example.test')
            && $mail->hasReplyTo('ada@example.test', 'Ada Lovelace')
            && $mail->envelope()->from === null
            && $mail->locale === 'en'
            && $mail->contactMessage->is($message);
    });

    $message->refresh();
    expect($message->status)->toBe(ContactMessageStatus::Sent)
        ->and($message->attempts)->toBe(1)
        ->and($message->sent_at)->not->toBeNull()
        ->and($message->last_error)->toBeNull();
});

test('the rendered mail carries the site name and escapes the message', function () {
    config(['seo.site_name' => 'Starter Site']);
    $message = ContactMessage::factory()->create(['message' => '<script>alert(1)</script>']);

    $mailable = (new ContactMessageMail($message))->locale('en');

    $mailable->assertHasSubject('New contact message — Starter Site');
    $mailable->assertSeeInHtml('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    $mailable->assertDontSeeInHtml('<script>alert(1)</script>', false);
});

test('an already sent message is not sent again', function () {
    Mail::fake();
    $message = ContactMessage::factory()->sent()->create();

    app()->call([new SendContactMessage($message->id), 'handle']);

    Mail::assertNothingSent();
    expect($message->refresh()->attempts)->toBe(1);
});

test('a mailer exception records a pii-free error and is rethrown for retry', function () {
    $message = ContactMessage::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.test',
    ]);
    Mail::shouldReceive('to')->andThrow(
        new TransportException('Expected response code 250 but got 550 for ada@example.test (Ada Lovelace)', 550),
    );

    expect(fn () => app()->call([new SendContactMessage($message->id), 'handle']))
        ->toThrow(TransportException::class);

    $message->refresh();
    expect($message->status)->toBe(ContactMessageStatus::Pending)
        ->and($message->attempts)->toBe(1)
        ->and($message->last_error)->toBe('TransportException (code 550)');
});

test('exhausted attempts mark the message failed without pii in the error', function () {
    $message = ContactMessage::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.test',
        'attempts' => 4,
    ]);

    (new SendContactMessage($message->id))->failed(
        new TransportException('Connection to ada@example.test failed for Ada Lovelace'),
    );

    $message->refresh();
    expect($message->status)->toBe(ContactMessageStatus::Failed)
        ->and($message->last_error)->toBe('TransportException')
        ->and($message->last_error)->not->toContain('ada@example.test')
        ->and($message->last_error)->not->toContain('Ada');
});

test('a late failure never downgrades a sent message', function () {
    $message = ContactMessage::factory()->sent()->create();

    (new SendContactMessage($message->id))->failed(new RuntimeException('late'));

    expect($message->refresh()->status)->toBe(ContactMessageStatus::Sent);
});

test('the job has bounded retries, backoff, timeout and a per-message unique id', function () {
    $job = new SendContactMessage(42);

    expect($job->tries)->toBe(4)
        ->and($job->timeout)->toBe(30)
        ->and($job->backoff())->toBe([60, 300, 900])
        ->and($job->uniqueId())->toBe('42');
});
