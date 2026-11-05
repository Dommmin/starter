<?php

use App\Ai\Agents\TranslationAgent;
use App\Enums\AuditAction;
use App\Http\Requests\Admin\Assistant\SuggestTranslationRequest;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Page;
use App\Models\User;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
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
        'assistant.features.translation' => true,
        'assistant.monthly_token_budget' => 10_000,
        'ai.default' => 'anthropic',
        'ai.providers.anthropic.key' => 'synthetic-test-key',
    ]);
});

/**
 * @param  array<string, mixed>  $structured
 */
function assistantTranslationResponse(array $structured, int $inputTokens = 400, int $outputTokens = 300): StructuredTextResponse
{
    return new StructuredTextResponse(
        $structured,
        json_encode($structured, JSON_THROW_ON_ERROR),
        new TextUsage($inputTokens, $outputTokens),
        new Meta('anthropic', 'claude-haiku-5-5'),
    );
}

/**
 * Source body with a heading, a link, a bold word and a list.
 *
 * @return array<string, mixed>
 */
function assistantTranslationBody(string $intro = 'Visit ', string $link = 'our bakery', string $middle = ' every ', string $bold = 'morning', string $item = 'Sourdough', string $heading = 'Fresh bread'): array
{
    return [
        'type' => 'doc',
        'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => $heading]]],
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => $intro],
                ['type' => 'text', 'text' => $link, 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.com/bakery']]]],
                ['type' => 'text', 'text' => $middle],
                ['type' => 'text', 'text' => $bold, 'marks' => [['type' => 'bold']]],
                ['type' => 'text', 'text' => '.'],
            ]],
            ['type' => 'bulletList', 'content' => [
                ['type' => 'listItem', 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $item]]],
                ]],
            ]],
        ],
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function assistantTranslationInput(array $overrides = []): array
{
    return [
        'subject' => 'article',
        'source_locale' => 'en',
        'target_locale' => 'de',
        'title' => 'Fresh bread every morning',
        'excerpt' => 'Sourdough before sunrise.',
        'meta_description' => 'Our bakery bakes bread daily.',
        'body' => assistantTranslationBody(),
        ...$overrides,
    ];
}

test('an editor gets a translated draft that keeps the body structure, marks and links', function () {
    TranslationAgent::fake([assistantTranslationResponse([
        'title' => ' Frisches Brot jeden Morgen ',
        'excerpt' => 'Sauerteig vor Sonnenaufgang.',
        'meta_description' => 'Unsere Bäckerei backt täglich Brot.',
        'body' => ['Frisches Brot', 'Besuchen Sie', 'unsere Bäckerei', 'jeden', 'Morgen', '.', 'Sauerteig'],
    ])])->preventStrayPrompts();
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)
        ->postJson(route('admin.assistant.translation'), assistantTranslationInput())
        ->assertOk()
        ->assertExactJson([
            'title' => 'Frisches Brot jeden Morgen',
            'excerpt' => 'Sauerteig vor Sonnenaufgang.',
            'metaDescription' => 'Unsere Bäckerei backt täglich Brot.',
            'body' => assistantTranslationBody('Besuchen Sie ', 'unsere Bäckerei', ' jeden ', 'Morgen', 'Sauerteig', 'Frisches Brot'),
        ]);

    TranslationAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Fresh bread every morning')
        && $prompt->contains('"body":["Fresh bread","Visit ","our bakery"," every ","morning",".","Sourdough"]')
        && ! $prompt->contains('example.com')
        && ! $prompt->contains('bulletList'));

    $entry = AuditLog::query()->sole();
    expect($entry->action)->toBe(AuditAction::AssistantSuggested)
        ->and($entry->actor_id)->toBe($editor->id)
        ->and($entry->subject_type)->toBe((new Article)->getMorphClass())
        ->and($entry->subject_id)->toBeNull()
        ->and($entry->changes)->toBe([
            'feature' => ['old' => null, 'new' => 'translation'],
            'model' => ['old' => null, 'new' => 'claude-haiku-5-5'],
            'tokens' => ['old' => null, 'new' => 700],
        ])
        ->and(json_encode($entry->changes))->not->toContain('bread')
        ->and(Cache::get('assistant:tokens:'.now()->format('Y-m')))->toBe(700);
});

test('a page translation has no excerpt and keeps empty source fields empty', function () {
    TranslationAgent::fake([assistantTranslationResponse([
        'title' => 'O nas',
        'meta_description' => 'Invented by the model.',
        'excerpt' => 'Not a page field.',
    ])])->preventStrayPrompts();

    $this->actingAs(User::factory()->editor()->create())
        ->postJson(route('admin.assistant.translation'), assistantTranslationInput([
            'subject' => 'page',
            'target_locale' => 'pl',
            'title' => 'About us',
            'meta_description' => '',
            'body' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
        ]))
        ->assertOk()
        ->assertExactJson([
            'title' => 'O nas',
            'excerpt' => null,
            'metaDescription' => '',
            'body' => null,
        ]);

    TranslationAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->prompt === '{"title":"About us","body":[]}'
        && array_keys($prompt->agent->schema(new JsonSchemaTypeFactory)) === ['title']);
    expect(AuditLog::query()->sole()->subject_type)->toBe((new Page)->getMorphClass());
});

test('an answer that does not match the source fragments is rejected', function (mixed $body) {
    TranslationAgent::fake([assistantTranslationResponse([
        'title' => 'Frisches Brot',
        'excerpt' => '',
        'meta_description' => '',
        'body' => $body,
    ])]);

    $this->actingAs(User::factory()->editor()->create())
        ->postJson(route('admin.assistant.translation'), assistantTranslationInput())
        ->assertServiceUnavailable()
        ->assertJsonPath('reason', 'unavailable');
})->with([
    'missing fragment' => [['Frisches Brot', 'Besuchen Sie', 'unsere Bäckerei', 'jeden', 'Morgen', '.']],
    'blank fragment' => [['Frisches Brot', '', 'unsere Bäckerei', 'jeden', 'Morgen', '.', 'Sauerteig']],
    'not a list of strings' => [['Frisches Brot', ['type' => 'text'], 'a', 'b', 'c', '.', 'd']],
    'missing body' => [null],
]);

test('the endpoint does not exist while the feature is unavailable', function (array $config) {
    config($config);
    TranslationAgent::fake();

    $this->actingAs(User::factory()->editor()->create())
        ->postJson(route('admin.assistant.translation'), assistantTranslationInput())
        ->assertNotFound();

    TranslationAgent::assertNeverPrompted();
    expect(AuditLog::query()->count())->toBe(0);
})->with([
    'assistant disabled' => [['assistant.enabled' => false]],
    'feature disabled' => [['assistant.features.translation' => false]],
    'no API key' => [['ai.providers.anthropic.key' => null]],
]);

test('users who may not create the content type are denied', function (string $role, string $subject) {
    TranslationAgent::fake();

    $this->actingAs(User::factory()->{$role}()->create())
        ->postJson(route('admin.assistant.translation'), assistantTranslationInput(['subject' => $subject]))
        ->assertForbidden();

    TranslationAgent::assertNeverPrompted();
    expect(AuditLog::query()->count())->toBe(0);
})->with([
    'viewer, article' => ['viewer', 'article'],
    'viewer, page' => ['viewer', 'page'],
    'editor, unknown subject' => ['editor', 'user'],
]);

test('guests are sent to the login page', function () {
    TranslationAgent::fake();

    $this->post(route('admin.assistant.translation'), assistantTranslationInput())->assertRedirect(route('login'));

    TranslationAgent::assertNeverPrompted();
});

test('the input is validated before the provider is called', function (array $overrides, string $field) {
    TranslationAgent::fake();

    $this->actingAs(User::factory()->editor()->create())
        ->postJson(route('admin.assistant.translation'), assistantTranslationInput($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    TranslationAgent::assertNeverPrompted();
    expect(AuditLog::query()->count())->toBe(0);
})->with([
    'unknown target locale' => [['target_locale' => 'xx'], 'target_locale'],
    'unknown source locale' => [['source_locale' => 'xx'], 'source_locale'],
    'same locale' => [['target_locale' => 'en'], 'target_locale'],
    'missing source title' => [['title' => ''], 'title'],
    'body outside the rich text schema' => [['body' => ['type' => 'doc', 'content' => [['type' => 'script']]]], 'body'],
    'unsafe link in the body' => [['body' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
        ['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]],
    ]]]]], 'body'],
]);

test('a missing source title is reported with a clear message', function () {
    TranslationAgent::fake();

    $this->actingAs(User::factory()->editor()->create())
        ->postJson(route('admin.assistant.translation'), assistantTranslationInput(['title' => '']))
        ->assertJsonValidationErrors(['title' => __('admin.assistant.translation.missingSourceTitle')]);
});

test('text longer than the synchronous limit is rejected with a clear message', function () {
    TranslationAgent::fake();
    $paragraph = str_repeat('a', 4_000);

    $this->actingAs(User::factory()->editor()->create())
        ->postJson(route('admin.assistant.translation'), assistantTranslationInput(['body' => ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $paragraph]]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $paragraph]]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $paragraph]]],
        ]]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['source' => __('admin.assistant.translation.tooLong', ['max' => SuggestTranslationRequest::TEXT_MAX])]);

    TranslationAgent::assertNeverPrompted();
});

test('a used up monthly budget stops translations before the provider is called', function () {
    Cache::put('assistant:tokens:'.now()->format('Y-m'), 10_000);
    TranslationAgent::fake();

    $this->actingAs(User::factory()->editor()->create())
        ->postJson(route('admin.assistant.translation'), assistantTranslationInput())
        ->assertTooManyRequests()
        ->assertJsonPath('reason', 'budget');

    TranslationAgent::assertNeverPrompted();
    expect(AuditLog::query()->count())->toBe(0);
});

test('a provider failure is reported without an audit entry', function () {
    TranslationAgent::fake(fn () => throw new ProviderOverloadedException('Overloaded.'));

    $this->actingAs(User::factory()->editor()->create())
        ->postJson(route('admin.assistant.translation'), assistantTranslationInput())
        ->assertServiceUnavailable()
        ->assertJsonPath('message', __('admin.assistant.errors.unavailable'))
        ->assertJsonPath('reason', 'unavailable');

    expect(AuditLog::query()->count())->toBe(0)
        ->and(Cache::get('assistant:tokens:'.now()->format('Y-m')))->toBeNull();
});

test('the editors offer the AI translation only while the feature is available', function (string $route, bool $enabled, bool $expected) {
    config(['assistant.features.translation' => $enabled]);

    $this->actingAs(User::factory()->editor()->create())
        ->get(route($route))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia->where('can.suggestTranslation', $expected));
})->with([
    'article, available' => ['admin.articles.create', true, true],
    'article, disabled' => ['admin.articles.create', false, false],
    'page, available' => ['admin.pages.create', true, true],
    'page, disabled' => ['admin.pages.create', false, false],
]);
