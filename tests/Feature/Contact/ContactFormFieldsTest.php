<?php

use App\Data\Home\ContactContentData;
use App\Enums\HomeSectionType;
use App\Http\Requests\Contact\StoreContactMessageRequest;
use App\Jobs\DeliverContactWebhook;
use App\Jobs\SendContactMessage;
use App\Mail\ContactMessageMail;
use App\Models\ContactForm;
use App\Models\ContactFormField;
use App\Models\ContactMessage;
use App\Models\HomeSection;
use App\Models\User;
use Database\Seeders\ContactFormSeeder;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
    $this->seed(ContactFormSeeder::class);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function quotePayload(array $overrides = []): array
{
    return [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.test',
        'message' => 'We need a website.',
        'website' => '',
        'form_token' => StoreContactMessageRequest::issueFormToken(now()->subSeconds(10)),
        'form' => 'quote',
        'fields' => ['budget' => '5k_15k', 'deadline' => 'March', 'phone_ok' => true],
        ...$overrides,
    ];
}

test('a quote stores its form and the extra values', function () {
    $this->post(route('contact.store'), quotePayload())->assertSessionHasNoErrors();

    $message = ContactMessage::query()->sole();
    expect($message->contact_form_id)->toBe(ContactForm::query()->where('key', 'quote')->value('id'))
        ->and($message->fields)->toBe(['budget' => '5k_15k', 'deadline' => 'March', 'phone_ok' => true]);
    Queue::assertPushed(SendContactMessage::class);
});

test('extra values outside the configuration are rejected without storing anything', function (array $overrides, string $errorKey) {
    $this->post(route('contact.store'), quotePayload($overrides))->assertSessionHasErrors($errorKey);

    expect(ContactMessage::query()->exists())->toBeFalse();
    Queue::assertNothingPushed();
})->with([
    'unknown field' => [['fields' => ['budget' => '5k_15k', 'salary' => '1']], 'fields'],
    'option outside the list' => [['fields' => ['budget' => 'unlimited']], 'fields.budget'],
    'missing required field' => [['fields' => ['deadline' => 'soon']], 'fields.budget'],
    'control characters in text' => [['fields' => ['budget' => '5k_15k', 'deadline' => "soon\r\nBcc: x"]], 'fields.deadline'],
    'too long text' => [['fields' => ['budget' => '5k_15k', 'deadline' => str_repeat('a', 256)]], 'fields.deadline'],
    'fields on the default form' => [['form' => null, 'fields' => ['budget' => '5k_15k']], 'fields'],
    'unknown form' => [['form' => 'jobs'], 'form'],
]);

test('a disabled form does not accept submissions', function () {
    ContactForm::query()->where('key', 'quote')->update(['enabled' => false]);

    $this->post(route('contact.store'), quotePayload())->assertSessionHasErrors('form');

    expect(ContactMessage::query()->exists())->toBeFalse();
});

test('a required checkbox must be ticked', function () {
    $form = ContactForm::query()->where('key', 'quote')->sole();
    ContactFormField::factory()->checkbox()->required()->for($form, 'form')->create(['key' => 'terms', 'position' => 9]);

    $this->post(route('contact.store'), quotePayload())->assertSessionHasErrors('fields.terms');
    $this->post(route('contact.store'), quotePayload(['fields' => ['budget' => '5k_15k', 'terms' => '1']]))->assertSessionHasNoErrors();
});

test('the owner mail and the webhook carry the extra values with their labels', function () {
    config(['contact.webhook.url' => 'https://hooks.example.test/x', 'contact.webhook.secret' => 's']);
    $this->post(route('contact.store'), quotePayload())->assertSessionHasNoErrors();
    $message = ContactMessage::query()->with('form.fields')->sole();

    $html = (new ContactMessageMail($message))->locale('en')->render();
    expect($html)->toContain('Budget')->toContain('5k–15k')->toContain('You may call me')->toContain('Yes');

    expect(DeliverContactWebhook::payload($message))
        ->toMatchArray(['form' => 'quote'])
        ->and((array) DeliverContactWebhook::payload($message)['fields'])->toBe(['budget' => '5k_15k', 'deadline' => 'March', 'phone_ok' => true]);
});

test('the home page renders the form chosen in the contact section', function () {
    HomeSection::factory()->ofType(HomeSectionType::Contact)->enabled()->create([
        'content' => new ContactContentData(title: 'Quote', formKey: 'quote'),
    ]);

    $this->get('/')->assertInertia(fn (Assert $inertia) => $inertia
        ->where('contactForm.form.key', 'quote')
        ->has('contactForm.form.fields', 3)
        ->where('contactForm.form.fields.0.key', 'budget')
        ->where('contactForm.form.fields.0.options.1', ['value' => '5k_15k', 'label' => '5k–15k'])
    );
});

test('without a chosen form the home page uses the default form', function () {
    $this->get('/')->assertInertia(fn (Assert $inertia) => $inertia
        ->where('contactForm.form.key', 'contact')
        ->has('contactForm.form.fields', 0)
    );
});

test('a contact section cannot point at a form that does not exist', function () {
    config(['fortify.require_two_factor_for_admin' => false]);
    $section = HomeSection::factory()->ofType(HomeSectionType::Contact)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.home-sections.update', $section), [
            'updated_at' => $section->updated_at?->toIso8601String(),
            'content' => ['title' => 'Contact', 'description' => null, 'formKey' => 'missing'],
        ])
        ->assertSessionHasErrors('content.formKey');
});
