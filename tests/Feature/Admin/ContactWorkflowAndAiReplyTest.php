<?php

declare(strict_types=1);

use App\Ai\Agents\ContactReplyAgent;
use App\Enums\LeadStatus;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('updates lead status and internal notes for editors and admins', function (): void {
    $editor = User::factory()->editor()->create();
    $message = ContactMessage::factory()->create();

    $this->actingAs($editor)
        ->patch(route('admin.contact.update', $message), [
            'lead_status' => LeadStatus::InProgress->value,
            'internal_notes' => 'Client asked for a quote by Friday.',
        ])
        ->assertRedirect(route('admin.contact.show', $message));

    $message->refresh();
    expect($message->lead_status)->toBe(LeadStatus::InProgress)
        ->and($message->internal_notes)->toBe('Client asked for a quote by Friday.');
});

it('forbids viewer from updating lead workflow', function (): void {
    $viewer = User::factory()->viewer()->create();
    $message = ContactMessage::factory()->create();

    $this->actingAs($viewer)
        ->patch(route('admin.contact.update', $message), [
            'lead_status' => LeadStatus::Archived->value,
        ])
        ->assertForbidden();

    expect($message->fresh()?->lead_status)->toBe(LeadStatus::New);
});

it('generates an AI reply draft for a contact message', function (): void {
    config()->set('assistant.enabled', true);
    config()->set('assistant.features.reply_draft', true);
    config()->set('ai.default', 'openai');
    config()->set('ai.providers.openai.key', 'test-key');

    ContactReplyAgent::fake([
        [
            'subject' => 'Re: Pricing question',
            'body' => 'Thank you for reaching out. We can schedule a quick call this week.',
        ],
    ]);

    $editor = User::factory()->editor()->create();
    $message = ContactMessage::factory()->create(['locale' => 'en']);

    $response = $this->actingAs($editor)
        ->postJson(route('admin.assistant.contact-reply'), [
            'contact_message_id' => $message->id,
            'guidance' => 'Offer a 15-minute call.',
        ]);

    $response->assertOk()
        ->assertJson([
            'subject' => 'Re: Pricing question',
            'body' => 'Thank you for reaching out. We can schedule a quick call this week.',
        ]);

    ContactReplyAgent::assertPrompted(fn ($prompt) => str_contains((string) $prompt->prompt, 'Offer a 15-minute call.'));
});

it('returns 404 and sends nothing to the AI provider while reply drafts are unavailable', function (string $switch): void {
    config()->set('assistant.enabled', true);
    config()->set('assistant.features.reply_draft', true);
    config()->set('ai.default', 'openai');
    config()->set('ai.providers.openai.key', 'test-key');
    config()->set($switch, false);

    ContactReplyAgent::fake();

    $editor = User::factory()->editor()->create();
    $message = ContactMessage::factory()->create();

    $this->actingAs($editor)
        ->postJson(route('admin.assistant.contact-reply'), ['contact_message_id' => $message->id])
        ->assertNotFound();

    ContactReplyAgent::assertNeverPrompted();
})->with([
    'assistant disabled' => 'assistant.enabled',
    'feature disabled' => 'assistant.features.reply_draft',
]);
