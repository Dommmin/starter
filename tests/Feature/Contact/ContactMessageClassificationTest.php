<?php

use App\Actions\Assistant\Exceptions\AssistantUnavailable;
use App\Actions\Contact\SubmitContactMessage;
use App\Ai\Agents\MessageClassificationAgent;
use App\Enums\AuditAction;
use App\Enums\ContactMessageCategory;
use App\Enums\ContactMessageStatus;
use App\Jobs\ClassifyContactMessage;
use App\Jobs\SendContactMessage;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Services\Webhooks\HostResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\StructuredTextResponse;

beforeEach(function () {
    config([
        'assistant.enabled' => true,
        'assistant.features.message_classification' => true,
        'assistant.monthly_token_budget' => 1_000,
        'ai.default' => 'anthropic',
        'ai.providers.anthropic.key' => 'synthetic-test-key',
        'contact.recipient' => 'office@example.test',
    ]);
});

function classificationResponse(string $category, int $inputTokens = 80, int $outputTokens = 5): StructuredTextResponse
{
    return new StructuredTextResponse(
        ['category' => $category],
        json_encode(['category' => $category], JSON_THROW_ON_ERROR),
        new TextUsage($inputTokens, $outputTokens),
        new Meta('anthropic', 'claude-haiku-5-5'),
    );
}

function classify(ContactMessage $message): void
{
    app()->call([new ClassifyContactMessage($message->id), 'handle']);
}

test('only the redacted subject and body are sent and the category is stored with an audit entry without content', function () {
    MessageClassificationAgent::fake([classificationResponse('lead')])->preventStrayPrompts();
    $message = ContactMessage::factory()->create([
        'name' => 'Grace Hopper',
        'email' => 'grace@example.test',
        'message' => 'We need a quote for 20 cakes. Call me at +48 600 700 800 or write to boss@company.test.',
        'fields' => ['subject' => 'Wedding order', 'phone' => '+48 111 222 333', 'company' => 'Navy Bakery'],
    ]);

    classify($message);

    MessageClassificationAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Wedding order')
        && $prompt->contains('We need a quote for 20 cakes.')
        && $prompt->contains('Call me at [phone] or write to [email].')
        && ! $prompt->contains('Grace')
        && ! $prompt->contains('grace@example.test')
        && ! $prompt->contains('600 700 800')
        && ! $prompt->contains('boss@company.test')
        && ! $prompt->contains('111 222 333')
        && ! $prompt->contains('Navy Bakery'));

    $message->refresh();
    expect($message->category)->toBe(ContactMessageCategory::Lead)
        ->and($message->classified_at)->not->toBeNull();

    $entry = AuditLog::query()->sole();
    expect($entry->action)->toBe(AuditAction::AssistantSuggested)
        ->and($entry->actor_id)->toBeNull()
        ->and($entry->subject_id)->toBe($message->id)
        // toEqual: MySQL JSON does not keep the key order.
        ->and($entry->changes)->toEqual([
            'feature' => ['old' => null, 'new' => 'message_classification'],
            'model' => ['old' => null, 'new' => 'claude-haiku-5-5'],
            'tokens' => ['old' => null, 'new' => 85],
        ]);
});

test('a message without a subject field sends only the body', function () {
    MessageClassificationAgent::fake([classificationResponse('question')]);
    $message = ContactMessage::factory()->create(['message' => 'Are you open on Sundays?', 'fields' => null]);

    classify($message);

    MessageClassificationAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->prompt === "Message:\nAre you open on Sundays?");
    expect($message->refresh()->category)->toBe(ContactMessageCategory::Question);
});

test('a stored message queues the classification only while the feature is available', function (array $config, bool $queued) {
    Queue::fake();
    config($config);

    $message = app(SubmitContactMessage::class)->handle('Ada', 'ada@example.test', 'Hello', 'en');

    Queue::assertPushed(SendContactMessage::class);
    if ($queued) {
        Queue::assertPushed(ClassifyContactMessage::class, fn (ClassifyContactMessage $job): bool => $job->contactMessageId === $message->id);
    } else {
        Queue::assertNotPushed(ClassifyContactMessage::class);
    }
})->with([
    'available' => [[], true],
    'feature disabled' => [['assistant.features.message_classification' => false], false],
    'assistant disabled' => [['assistant.enabled' => false], false],
    'no API key' => [['ai.providers.anthropic.key' => null], false],
]);

test('the feature is off unless explicitly enabled', function () {
    $config = require config_path('assistant.php');

    expect($config['features']['message_classification'])->toBeFalse();
});

test('a job queued before the feature was switched off does not prompt', function () {
    MessageClassificationAgent::fake();
    $message = ContactMessage::factory()->create();
    config(['assistant.features.message_classification' => false]);

    classify($message);

    MessageClassificationAgent::assertNeverPrompted();
    expect($message->refresh()->classified_at)->toBeNull();
});

test('a failing classification does not affect the stored message, the mail or the webhook', function () {
    Mail::fake();
    Http::fake(['hooks.example.test/*' => Http::response('', 204)]);
    config(['contact.webhook.url' => 'https://hooks.example.test/leads', 'contact.webhook.secret' => 'test-secret']);
    app()->instance(HostResolver::class, new class extends HostResolver
    {
        public function resolve(string $host): array
        {
            return $host === 'hooks.example.test' ? ['93.184.216.34'] : [];
        }
    });
    Log::spy();
    MessageClassificationAgent::fake(fn () => throw new ProviderOverloadedException('Overloaded: secret prompt echo'));

    $message = app(SubmitContactMessage::class)->handle('Ada', 'ada@example.test', 'Please call me', 'en');

    $message->refresh();
    expect($message->status)->toBe(ContactMessageStatus::Sent)
        ->and($message->webhook_delivered_at)->not->toBeNull()
        ->and($message->category)->toBeNull()
        ->and($message->classified_at)->toBeNull()
        ->and(AuditLog::query()->count())->toBe(0);
    Http::assertSentCount(1);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $text, array $context): bool => ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'Please call me')
        && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'secret prompt echo'));
});

test('a provider failure in the queue is retried without the provider message', function () {
    MessageClassificationAgent::fake(fn () => throw new ProviderOverloadedException('Overloaded: secret prompt echo'));
    $message = ContactMessage::factory()->create();

    expect(fn () => classify($message))->toThrow(function (AssistantUnavailable $exception): void {
        expect($exception->getMessage())->not->toContain('secret prompt echo')
            ->and($exception->getPrevious())->toBeNull();
    });

    expect($message->refresh()->classified_at)->toBeNull();
});

test('spam is only marked and the message is kept', function () {
    MessageClassificationAgent::fake([classificationResponse('spam')]);
    $message = ContactMessage::factory()->create(['message' => 'Cheap SEO links!!!']);

    classify($message);

    expect(ContactMessage::query()->whereKey($message->id)->sole()->category)->toBe(ContactMessageCategory::Spam);
});

test('a classified message is not prompted again', function () {
    MessageClassificationAgent::fake([classificationResponse('lead'), classificationResponse('spam')]);
    $message = ContactMessage::factory()->create();

    classify($message);
    classify($message);

    MessageClassificationAgent::assertPromptedTimes(1);
    expect($message->refresh()->category)->toBe(ContactMessageCategory::Lead)
        ->and(AuditLog::query()->count())->toBe(1);
});

test('a used up monthly budget leaves the message unclassified without a prompt', function () {
    Cache::put('assistant:tokens:'.now()->format('Y-m'), 1_000);
    MessageClassificationAgent::fake();
    $message = ContactMessage::factory()->create();

    classify($message);

    MessageClassificationAgent::assertNeverPrompted();
    expect($message->refresh()->classified_at)->toBeNull()
        ->and(AuditLog::query()->count())->toBe(0);
});

test('beyond the daily limit messages stay unclassified without a prompt', function () {
    config(['assistant.message_classification_daily_limit' => 1]);
    MessageClassificationAgent::fake([classificationResponse('lead')]);
    [$first, $second] = ContactMessage::factory()->count(2)->create()->all();

    classify($first);
    classify($second);

    MessageClassificationAgent::assertPromptedTimes(1);
    expect($first->refresh()->category)->toBe(ContactMessageCategory::Lead)
        ->and($second->refresh()->classified_at)->toBeNull();
});

test('an answer outside the categories is not stored', function () {
    MessageClassificationAgent::fake([classificationResponse('urgent')]);
    $message = ContactMessage::factory()->create();

    classify($message);

    expect($message->refresh()->category)->toBeNull()
        ->and($message->classified_at)->toBeNull();
});

test('the migration adds and removes only the classification columns', function () {
    $migration = require database_path('migrations/2026_11_05_100000_add_classification_to_contact_messages_table.php');
    $before = Schema::getColumnListing('contact_messages');

    $migration->down();

    expect(Schema::hasColumn('contact_messages', 'category'))->toBeFalse()
        ->and(Schema::hasColumn('contact_messages', 'classified_at'))->toBeFalse()
        ->and(array_values(array_diff($before, Schema::getColumnListing('contact_messages'))))->toEqualCanonicalizing(['category', 'classified_at']);

    $migration->up();

    expect(Schema::getColumnListing('contact_messages'))->toEqualCanonicalizing($before);
});
