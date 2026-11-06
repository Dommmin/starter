<?php

use App\Ai\Agents\FaqGeneratorAgent;
use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Faq;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\StructuredTextResponse;

beforeEach(function () {
    config([
        'fortify.require_two_factor_for_admin' => false,
        'assistant.enabled' => true,
        'assistant.features.faq_generator' => true,
        'assistant.monthly_token_budget' => 1_000,
        'ai.default' => 'anthropic',
        'ai.providers.anthropic.key' => 'synthetic-test-key',
    ]);
});

/**
 * @param  list<mixed>  $items
 */
function faqGeneratorResponse(array $items): StructuredTextResponse
{
    $structured = ['items' => $items];

    return new StructuredTextResponse(
        $structured,
        json_encode($structured, JSON_THROW_ON_ERROR),
        new TextUsage(300, 200),
        new Meta('anthropic', 'claude-haiku-5-5'),
    );
}

/**
 * @return array<string, string>
 */
function faqGeneratorInput(array $overrides = []): array
{
    return [
        'locale' => 'en',
        'text' => 'Our bakery bakes sourdough bread before sunrise and delivers in the city.',
        ...$overrides,
    ];
}

/**
 * @return array{locale: string, items: list<array{question: string, answer: string}>}
 */
function faqSelection(array $overrides = []): array
{
    return [
        'locale' => 'en',
        'items' => [
            ['question' => 'When is the bread baked?', 'answer' => 'Before sunrise.'],
            ['question' => 'Do you deliver?', 'answer' => 'Yes, in the city.'],
        ],
        ...$overrides,
    ];
}

test('an editor gets normalized proposals, nothing is saved and the usage is audited', function () {
    FaqGeneratorAgent::fake([faqGeneratorResponse([
        ['question' => '  When is the bread baked?  ', 'answer' => ' Before sunrise. '],
        ['question' => str_repeat('q', 300), 'answer' => str_repeat('a', 1_200)],
        ['question' => '', 'answer' => 'An answer without a question.'],
        'not an item',
    ])])->preventStrayPrompts();
    $editor = User::factory()->editor()->create();

    $response = $this->actingAs($editor)
        ->postJson(route('admin.assistant.faqs'), faqGeneratorInput())
        ->assertOk()
        ->assertJsonCount(2, 'items')
        ->assertJsonPath('items.0', ['question' => 'When is the bread baked?', 'answer' => 'Before sunrise.']);

    expect(mb_strlen($response->json('items.1.question')))->toBe(FaqGeneratorAgent::QUESTION_MAX)
        ->and(mb_strlen($response->json('items.1.answer')))->toBe(FaqGeneratorAgent::ANSWER_MAX)
        ->and(Faq::query()->count())->toBe(0);

    FaqGeneratorAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('sourdough bread'));

    $entry = AuditLog::query()->sole();
    expect($entry->action)->toBe(AuditAction::AssistantSuggested)
        ->and($entry->actor_id)->toBe($editor->id)
        ->and($entry->subject_type)->toBe((new Faq)->getMorphClass())
        ->and($entry->subject_id)->toBeNull()
        ->and($entry->changes['feature'])->toBe(['old' => null, 'new' => 'faq_generator'])
        ->and(json_encode($entry->changes))->not->toContain('sourdough');
});

test('more proposals than the limit are cut to eight', function () {
    $items = [];
    for ($i = 1; $i <= 12; $i++) {
        $items[] = ['question' => "Question {$i}?", 'answer' => "Answer {$i}."];
    }
    FaqGeneratorAgent::fake([faqGeneratorResponse($items)]);

    $this->actingAs(User::factory()->editor()->create())
        ->postJson(route('admin.assistant.faqs'), faqGeneratorInput())
        ->assertOk()
        ->assertJsonCount(FaqGeneratorAgent::MAX_ITEMS, 'items')
        ->assertJsonPath('items.7.question', 'Question 8?');
});

test('the endpoint does not exist while the feature is unavailable', function (array $config) {
    config($config);
    FaqGeneratorAgent::fake();

    $this->actingAs(User::factory()->editor()->create())
        ->postJson(route('admin.assistant.faqs'), faqGeneratorInput())
        ->assertNotFound();

    FaqGeneratorAgent::assertNeverPrompted();
    expect(AuditLog::query()->count())->toBe(0);
})->with([
    'assistant disabled' => [['assistant.enabled' => false]],
    'feature disabled' => [['assistant.features.faq_generator' => false]],
    'no API key' => [['ai.providers.anthropic.key' => null]],
]);

test('viewers may not generate proposals', function () {
    FaqGeneratorAgent::fake();

    $this->actingAs(User::factory()->viewer()->create())
        ->postJson(route('admin.assistant.faqs'), faqGeneratorInput())
        ->assertForbidden();

    FaqGeneratorAgent::assertNeverPrompted();
    expect(AuditLog::query()->count())->toBe(0);
});

test('the source is validated before the provider is called', function (array $overrides, string $field) {
    FaqGeneratorAgent::fake();

    $this->actingAs(User::factory()->editor()->create())
        ->postJson(route('admin.assistant.faqs'), faqGeneratorInput($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    FaqGeneratorAgent::assertNeverPrompted();
})->with([
    'unknown locale' => [['locale' => 'xx'], 'locale'],
    'missing text' => [['text' => ''], 'text'],
    'text too long' => [['text' => str_repeat('a', 20_001)], 'text'],
]);

test('a used up monthly budget stops generation before the provider is called', function () {
    Cache::put('assistant:tokens:'.now()->format('Y-m'), 1_000);
    FaqGeneratorAgent::fake();

    $this->actingAs(User::factory()->editor()->create())
        ->postJson(route('admin.assistant.faqs'), faqGeneratorInput())
        ->assertTooManyRequests()
        ->assertJsonPath('reason', 'budget');

    FaqGeneratorAgent::assertNeverPrompted();
    expect(AuditLog::query()->count())->toBe(0);
});

test('a provider failure is reported without an audit entry', function () {
    FaqGeneratorAgent::fake(fn () => throw new ProviderOverloadedException('Overloaded.'));

    $this->actingAs(User::factory()->editor()->create())
        ->postJson(route('admin.assistant.faqs'), faqGeneratorInput())
        ->assertServiceUnavailable()
        ->assertJsonPath('reason', 'unavailable');

    expect(AuditLog::query()->count())->toBe(0)
        ->and(Faq::query()->count())->toBe(0);
});

test('saving creates exactly the selected entries, unpublished in the chosen language', function () {
    $this->actingAs(User::factory()->editor()->create())
        ->post(route('admin.faqs.store-many'), faqSelection(['locale' => 'pl']))
        ->assertRedirect(route('admin.faqs.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('admin.faqs.createdMany', ['count' => 2])]);

    $faqs = Faq::query()->orderBy('id')->get();
    expect($faqs->pluck('question')->all())->toBe(['When is the bread baked?', 'Do you deliver?'])
        ->and($faqs->pluck('answer')->all())->toBe(['Before sunrise.', 'Yes, in the city.'])
        ->and($faqs->pluck('locale')->unique()->all())->toBe(['pl'])
        ->and($faqs->pluck('published')->unique()->all())->toBe([false])
        ->and($faqs->pluck('position')->unique()->all())->toBe([null]);
});

test('viewers may not save entries', function () {
    $this->actingAs(User::factory()->viewer()->create())
        ->post(route('admin.faqs.store-many'), faqSelection())
        ->assertForbidden();

    expect(Faq::query()->count())->toBe(0);
});

test('the selection is validated and nothing is saved on error', function (array $overrides, string $field) {
    $this->actingAs(User::factory()->editor()->create())
        ->post(route('admin.faqs.store-many'), faqSelection($overrides))
        ->assertSessionHasErrors($field);

    expect(Faq::query()->count())->toBe(0);
})->with([
    'nothing selected' => [['items' => []], 'items'],
    'more than eight' => [['items' => array_fill(0, 9, ['question' => 'Q?', 'answer' => 'A.'])], 'items'],
    'question too long' => [['items' => [['question' => str_repeat('q', 256), 'answer' => 'A.']]], 'items.0.question'],
    'missing answer' => [['items' => [['question' => 'Q?', 'answer' => '']]], 'items.0.answer'],
    'unknown locale' => [['locale' => 'xx'], 'locale'],
]);

test('the list offers the generator only while the feature is available to the user', function (string $role, bool $enabled, bool $expected) {
    config(['assistant.enabled' => $enabled]);

    $this->actingAs(User::factory()->{$role}()->create())
        ->get(route('admin.faqs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia->where('can.suggestFaqs', $expected));
})->with([
    'editor, available' => ['editor', true, true],
    'editor, disabled' => ['editor', false, false],
    'viewer, available' => ['viewer', true, false],
]);
