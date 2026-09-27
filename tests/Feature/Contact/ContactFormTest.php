<?php

use App\Enums\ContactMessageStatus;
use App\Http\Requests\Contact\StoreContactMessageRequest;
use App\Jobs\SendContactMessage;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
});

/**
 * A valid submission rendered 10 seconds ago.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function contactPayload(array $overrides = []): array
{
    return [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.test',
        'message' => "Hello,\nI would like to know more.",
        'website' => '',
        'form_token' => StoreContactMessageRequest::issueFormToken(now()->subSeconds(10)),
        ...$overrides,
    ];
}

test('the home page renders the contact form token', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->has('contactForm.token')
        );
});

test('a valid message is stored as pending and its delivery is queued', function () {
    $this->from(route('home'))
        ->post(route('contact.store'), contactPayload())
        ->assertRedirect(route('home'))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success');

    $message = ContactMessage::query()->sole();

    expect($message->name)->toBe('Ada Lovelace')
        ->and($message->email)->toBe('ada@example.test')
        ->and($message->status)->toBe(ContactMessageStatus::Pending)
        ->and($message->attempts)->toBe(0)
        ->and($message->locale)->toBe('en');

    Queue::assertPushed(SendContactMessage::class, fn (SendContactMessage $job) => $job->contactMessageId === $message->id);
});

test('a message sent from a prefixed locale stores that locale', function () {
    $this->post(route('localized.contact.store', ['locale' => 'de']), contactPayload())
        ->assertSessionHasNoErrors();

    expect(ContactMessage::query()->sole()->locale)->toBe('de');
});

test('invalid input is rejected without storing or queueing', function (array $overrides, string $field) {
    $this->from(route('home'))
        ->post(route('contact.store'), contactPayload($overrides))
        ->assertRedirect(route('home'))
        ->assertSessionHasErrors($field);

    expect(ContactMessage::query()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'missing name' => [['name' => ''], 'name'],
    'name too long' => [['name' => str_repeat('a', 121)], 'name'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'missing message' => [['message' => ''], 'message'],
    'message too long' => [['message' => str_repeat('a', 5001)], 'message'],
    'missing token' => [['form_token' => ''], 'form_token'],
    'tampered token' => [['form_token' => 'tampered'], 'form_token'],
]);

test('header injection through the name or email is rejected', function (array $overrides, string $field) {
    $this->post(route('contact.store'), contactPayload($overrides))
        ->assertSessionHasErrors($field);

    expect(ContactMessage::query()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'CRLF in name' => [['name' => "Ada\r\nBcc: victim@example.test"], 'name'],
    'LF in name' => [['name' => "Ada\nBcc: victim@example.test"], 'name'],
    'CRLF in email' => [['email' => "ada@example.test\r\nBcc: victim@example.test"], 'email'],
]);

test('an expired form token asks for a reload', function () {
    $this->post(route('contact.store'), contactPayload([
        'form_token' => StoreContactMessageRequest::issueFormToken(now()->subDays(2)),
    ]))->assertSessionHasErrors('form_token');

    expect(ContactMessage::query()->count())->toBe(0);
});

test('a filled honeypot pretends success without storing', function () {
    $this->from(route('home'))
        ->post(route('contact.store'), contactPayload(['website' => 'https://spam.example']))
        ->assertRedirect(route('home'))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success');

    expect(ContactMessage::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('a submission faster than a person can type is discarded as spam', function () {
    $this->post(route('contact.store'), contactPayload([
        'form_token' => StoreContactMessageRequest::issueFormToken(now()->subSecond()),
    ]))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success');

    expect(ContactMessage::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('the ip rate limit answers 429 and stores nothing more', function () {
    config(['contact.rate_limits.per_ip_per_hour' => 2]);

    $this->post(route('contact.store'), contactPayload(['email' => 'one@example.test']))->assertRedirect();
    $this->post(route('contact.store'), contactPayload(['email' => 'two@example.test']))->assertRedirect();

    $this->post(route('contact.store'), contactPayload(['email' => 'three@example.test']))
        ->assertStatus(429)
        ->assertHeader('Retry-After');

    expect(ContactMessage::query()->count())->toBe(2);
});

test('the email rate limit applies across ip addresses', function () {
    config(['contact.rate_limits.per_email_per_hour' => 1]);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->post(route('contact.store'), contactPayload())
        ->assertRedirect();

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->post(route('contact.store'), contactPayload(['email' => 'ADA@example.test']))
        ->assertStatus(429);

    expect(ContactMessage::query()->count())->toBe(1);
});
