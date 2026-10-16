<?php

use App\Enums\AuditAction;
use App\Enums\ContactFieldType;
use App\Models\AuditLog;
use App\Models\ContactForm;
use App\Models\ContactMessage;
use App\Models\User;
use Database\Seeders\ContactFormSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function contactFormPayload(array $overrides = []): array
{
    return [
        'key' => 'event',
        'enabled' => true,
        'title' => ['en' => 'Book an event', 'pl' => 'Zarezerwuj termin', 'de' => ''],
        'fields' => [
            ['key' => 'guests', 'type' => 'text', 'required' => true, 'label' => ['en' => 'Guests', 'pl' => 'Goście', 'de' => ''], 'options' => ''],
            ['key' => 'room', 'type' => 'select', 'required' => false, 'label' => ['en' => 'Room', 'pl' => 'Sala', 'de' => ''], 'options' => "small | Small | Mała\nlarge | Large"],
        ],
        ...$overrides,
    ];
}

test('an administrator creates a form with ordered fields and parsed options', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.contact-forms.store'), contactFormPayload())->assertSessionHasNoErrors();

    $form = ContactForm::query()->with('fields')->sole();
    expect($form->key)->toBe('event')
        ->and($form->title)->toBe(['en' => 'Book an event', 'pl' => 'Zarezerwuj termin'])
        ->and($form->fields->pluck('key')->all())->toBe(['guests', 'room'])
        ->and($form->fields[1]->type)->toBe(ContactFieldType::Select)
        ->and($form->fields[1]->options)->toBe([
            ['value' => 'small', 'label' => ['en' => 'Small', 'pl' => 'Mała']],
            ['value' => 'large', 'label' => ['en' => 'Large']],
        ]);

    expect(AuditLog::query()->sole()->action)->toBe(AuditAction::ContactFormCreated);
});

test('saving replaces the fields and a stale version is a conflict', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('admin.contact-forms.store'), contactFormPayload());
    $form = ContactForm::query()->sole();

    $this->actingAs($admin)
        ->put(route('admin.contact-forms.update', $form), contactFormPayload([
            'updated_at' => $form->updated_at?->toIso8601String(),
            'fields' => [['key' => 'notes', 'type' => 'textarea', 'required' => false, 'label' => ['en' => 'Notes'], 'options' => '']],
        ]))
        ->assertSessionHasNoErrors();

    expect($form->fields()->pluck('key')->all())->toBe(['notes']);

    $this->actingAs($admin)
        ->put(route('admin.contact-forms.update', $form), contactFormPayload(['updated_at' => '2020-01-01T00:00:00+00:00']))
        ->assertSessionHasErrors('conflict');

    expect($form->fields()->pluck('key')->all())->toBe(['notes']);
});

test('invalid forms are rejected without saving', function (Closure $override, string $errorKey) {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.contact-forms.store'), contactFormPayload($override()))
        ->assertSessionHasErrors($errorKey);

    expect(ContactForm::query()->exists())->toBeFalse();
})->with([
    'key with spaces' => [fn () => ['key' => 'my form'], 'key'],
    'missing default-language title' => [fn () => ['title' => ['en' => '', 'pl' => 'Tytuł']], 'title.en'],
    'duplicate field keys' => [fn () => ['fields' => [
        ['key' => 'a1', 'type' => 'text', 'required' => false, 'label' => ['en' => 'A'], 'options' => ''],
        ['key' => 'a1', 'type' => 'text', 'required' => false, 'label' => ['en' => 'B'], 'options' => ''],
    ]], 'fields.0.key'],
    'unknown field type' => [fn () => ['fields' => [['key' => 'file', 'type' => 'upload', 'required' => false, 'label' => ['en' => 'File'], 'options' => '']]], 'fields.0.type'],
    'select without options' => [fn () => ['fields' => [['key' => 'room', 'type' => 'select', 'required' => false, 'label' => ['en' => 'Room'], 'options' => '']]], 'fields.0.options'],
    'option value with spaces' => [fn () => ['fields' => [['key' => 'room', 'type' => 'select', 'required' => false, 'label' => ['en' => 'Room'], 'options' => 'big room | Big']]], 'fields.0.options'],
    'too many fields' => [fn () => ['fields' => array_map(fn (int $i): array => ['key' => "f{$i}", 'type' => 'text', 'required' => false, 'label' => ['en' => "F{$i}"], 'options' => ''], range(1, 21))], 'fields'],
]);

test('editors cannot list or change contact forms', function () {
    $this->seed(ContactFormSeeder::class);
    $editor = User::factory()->editor()->create();
    $form = ContactForm::query()->where('key', 'quote')->sole();

    $this->actingAs($editor)->get(route('admin.contact-forms.index'))->assertForbidden();
    $this->actingAs($editor)->post(route('admin.contact-forms.store'), contactFormPayload())->assertForbidden();
    $this->actingAs($editor)->put(route('admin.contact-forms.update', $form), contactFormPayload())->assertForbidden();
    $this->actingAs($editor)->delete(route('admin.contact-forms.destroy', $form))->assertForbidden();

    expect(ContactForm::query()->count())->toBe(2)->and(AuditLog::query()->exists())->toBeFalse();
});

test('the default form cannot be deleted; another form is deleted and its messages stay', function () {
    $this->seed(ContactFormSeeder::class);
    $admin = User::factory()->admin()->create();
    $default = ContactForm::query()->where('key', ContactForm::DEFAULT_KEY)->sole();
    $quote = ContactForm::query()->where('key', 'quote')->sole();
    $message = ContactMessage::factory()->create(['contact_form_id' => $quote->id, 'fields' => ['budget' => 'under_5k']]);

    $this->actingAs($admin)->delete(route('admin.contact-forms.destroy', $default))->assertForbidden();
    $this->actingAs($admin)->delete(route('admin.contact-forms.destroy', $quote))->assertRedirect(route('admin.contact-forms.index'));

    expect(ContactForm::query()->pluck('key')->all())->toBe([ContactForm::DEFAULT_KEY])
        ->and($message->fresh()->contact_form_id)->toBeNull()
        ->and($message->fresh()->fields)->toBe(['budget' => 'under_5k'])
        ->and(AuditLog::query()->sole()->action)->toBe(AuditAction::ContactFormDeleted);
});

test('the list and the editor show the forms to administrators', function () {
    $this->seed(ContactFormSeeder::class);
    $admin = User::factory()->admin()->create();
    $quote = ContactForm::query()->where('key', 'quote')->sole();

    $this->actingAs($admin)->get(route('admin.contact-forms.index'))
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/contact-forms/index')
            ->has('items', 2)
            ->where('items.1.key', 'quote')
            ->where('items.1.fieldsCount', 3)
            ->where('canCreate', true)
        );

    $this->actingAs($admin)->get(route('admin.contact-forms.edit', $quote))
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/contact-forms/edit')
            ->where('form.fields.0.key', 'budget')
            ->where('form.fields.0.options', "under_5k | under 5k | do 5 tys. zł | unter 5.000\n5k_15k | 5k–15k | 5–15 tys. zł | 5.000–15.000\nover_15k | over 15k | powyżej 15 tys. zł | über 15.000")
            ->where('canDelete', true)
        );
});
