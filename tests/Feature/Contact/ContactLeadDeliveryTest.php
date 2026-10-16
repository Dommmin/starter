<?php

use App\Actions\Contact\SubmitContactMessage;
use App\Enums\ContactMessageStatus;
use App\Enums\ContactWebhookStatus;
use App\Jobs\DeliverContactWebhook;
use App\Jobs\SendContactAutoReply;
use App\Jobs\SendContactMessage;
use App\Mail\ContactAutoReplyMail;
use App\Mail\ContactMessageMail;
use App\Models\ContactMessage;
use App\Models\SiteSetting;
use App\Models\SiteSettingTranslation;
use App\Services\Webhooks\HostResolver;
use App\Support\Webhooks\WebhookSignature;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'contact.recipient' => 'office@example.test',
        'contact.webhook.url' => 'https://hooks.example.test/leads',
        'contact.webhook.secret' => 'test-secret',
    ]);

    app()->instance(HostResolver::class, new class extends HostResolver
    {
        /** @var array<string, list<string>> */
        public array $records = ['hooks.example.test' => ['93.184.216.34']];

        public function resolve(string $host): array
        {
            return $this->records[$host] ?? [];
        }
    });
});

function deliverWebhook(ContactMessage $message): void
{
    app()->call([new DeliverContactWebhook($message->id), 'handle']);
}

test('a submitted message queues the owner mail, the auto-reply and the webhook', function () {
    Queue::fake();

    $message = app(SubmitContactMessage::class)->handle('Ada', 'ada@example.test', 'Hello', 'en');

    expect($message->webhook_status)->toBe(ContactWebhookStatus::Pending);
    Queue::assertPushed(SendContactMessage::class);
    Queue::assertPushed(SendContactAutoReply::class);
    Queue::assertPushed(DeliverContactWebhook::class, fn (DeliverContactWebhook $job) => $job->contactMessageId === $message->id);
});

test('without a configured webhook nothing is queued for it', function () {
    Queue::fake();
    config(['contact.webhook.secret' => '']);

    $message = app(SubmitContactMessage::class)->handle('Ada', 'ada@example.test', 'Hello', 'en');

    expect($message->webhook_status)->toBeNull();
    Queue::assertNotPushed(DeliverContactWebhook::class);
});

test('the webhook posts a signed payload with an idempotency key and is not sent twice', function () {
    Http::fake(['hooks.example.test/*' => Http::response('', 204)]);
    $message = ContactMessage::factory()->create(['name' => 'Ada', 'email' => 'ada@example.test']);

    deliverWebhook($message);
    deliverWebhook($message->fresh());

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) use ($message) {
        $timestamp = (int) $request->header('X-Starter-Timestamp')[0];

        return $request->url() === 'https://hooks.example.test/leads'
            && $request->header('X-Starter-Delivery')[0] === "contact-message-{$message->id}"
            && $request['email'] === 'ada@example.test'
            && WebhookSignature::verify('test-secret', $request->header('X-Starter-Signature')[0], $timestamp, $request->body(), now()->getTimestamp());
    });

    $message->refresh();
    expect($message->webhook_status)->toBe(ContactWebhookStatus::Delivered)
        ->and($message->webhook_attempts)->toBe(1)
        ->and($message->webhook_delivered_at)->not->toBeNull();
});

test('a failing receiver is retried later and never touches the message or its mail', function () {
    Http::fake(['hooks.example.test/*' => Http::response('down', 503)]);
    Mail::fake();
    $message = ContactMessage::factory()->create(['message' => 'Keep me']);

    expect(fn () => deliverWebhook($message))->toThrow(RuntimeException::class);
    (new DeliverContactWebhook($message->id))->failed(new RuntimeException('down'));
    app()->call([new SendContactMessage($message->id), 'handle']);

    $message->refresh();
    expect($message->webhook_status)->toBe(ContactWebhookStatus::Failed)
        ->and($message->webhook_delivered_at)->toBeNull()
        ->and($message->status)->toBe(ContactMessageStatus::Sent)
        ->and($message->message)->toBe('Keep me');
    Mail::assertSent(ContactMessageMail::class);

    Queue::fake();
    $this->artisan('contact:retry-failed')->assertSuccessful();
    Queue::assertPushed(DeliverContactWebhook::class, fn (DeliverContactWebhook $job) => $job->contactMessageId === $message->id);
});

test('a webhook host on a private, loopback or link-local address is never called', function (string $url, array $records) {
    Http::fake();
    app(HostResolver::class)->records = $records;
    config(['contact.webhook.url' => $url]);
    $message = ContactMessage::factory()->create();

    expect(fn () => deliverWebhook($message))->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
    expect($message->fresh()->webhook_delivered_at)->toBeNull();
})->with([
    'private network' => ['https://hooks.example.test/leads', ['hooks.example.test' => ['10.0.0.5']]],
    'one private record among public ones' => ['https://hooks.example.test/leads', ['hooks.example.test' => ['93.184.216.34', '192.168.1.10']]],
    'loopback literal' => ['https://127.0.0.1/leads', []],
    'cloud metadata' => ['https://169.254.169.254/latest', []],
    'carrier-grade NAT' => ['https://hooks.example.test/leads', ['hooks.example.test' => ['100.64.0.1']]],
    'ipv6 loopback' => ['https://[::1]/leads', []],
    'plain http' => ['http://hooks.example.test/leads', ['hooks.example.test' => ['93.184.216.34']]],
    'credentials in url' => ['https://user:pass@hooks.example.test/leads', ['hooks.example.test' => ['93.184.216.34']]],
    'unresolvable host' => ['https://missing.example.test/leads', []],
]);

test('every saved recipient receives the owner mail', function () {
    Mail::fake();
    SiteSetting::factory()->create(['contact_recipients' => ['one@acme.test', 'two@acme.test']]);
    $message = ContactMessage::factory()->create();

    app()->call([new SendContactMessage($message->id), 'handle']);

    Mail::assertSent(ContactMessageMail::class, fn (ContactMessageMail $mail) => $mail->hasTo('one@acme.test') && $mail->hasTo('two@acme.test'));
});

test('the auto-reply goes to the sender in their language without repeating the message', function () {
    Mail::fake();
    $settings = SiteSetting::factory()->create(['site_name' => 'Acme', 'contact_auto_reply' => true]);
    SiteSettingTranslation::factory()->for($settings)->create(['locale' => 'pl', 'auto_reply_text' => 'Dziękujemy, odezwiemy się jutro.']);
    $message = ContactMessage::factory()->create(['email' => 'ada@example.test', 'locale' => 'pl', 'message' => 'SECRET-BODY']);

    app()->call([new SendContactAutoReply($message->id), 'handle']);
    app()->call([new SendContactAutoReply($message->id), 'handle']);

    Mail::assertSentCount(1);
    Mail::assertSent(ContactAutoReplyMail::class, function (ContactAutoReplyMail $mail) {
        $html = $mail->render();

        return $mail->hasTo('ada@example.test')
            && $mail->locale === 'pl'
            && str_contains($html, 'Dziękujemy, odezwiemy się jutro.')
            && ! str_contains($html, 'SECRET-BODY');
    });
});

test('no auto-reply is sent while it is switched off or has no text', function (bool $enabled, ?string $text) {
    Mail::fake();
    $settings = SiteSetting::factory()->create(['contact_auto_reply' => $enabled]);
    SiteSettingTranslation::factory()->for($settings)->create(['locale' => 'en', 'auto_reply_text' => $text]);
    $message = ContactMessage::factory()->create(['locale' => 'en']);

    app()->call([new SendContactAutoReply($message->id), 'handle']);

    Mail::assertNothingSent();
})->with([
    'switched off' => [false, 'Thanks!'],
    'no text' => [true, null],
]);

test('the delivery jobs have bounded retries, timeouts and per-message unique ids', function () {
    $webhook = new DeliverContactWebhook(7);
    $autoReply = new SendContactAutoReply(7);

    expect($webhook->tries)->toBe(5)->and($webhook->timeout)->toBe(30)->and($webhook->uniqueId())->toBe('7')
        ->and($autoReply->tries)->toBe(3)->and($autoReply->uniqueId())->toBe('7');
});
