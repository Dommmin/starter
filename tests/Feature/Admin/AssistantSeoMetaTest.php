<?php

use App\Ai\Agents\SeoMetaAgent;
use App\Enums\AuditAction;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Page;
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
        'assistant.features.seo_meta' => true,
        'assistant.monthly_token_budget' => 1_000,
        'ai.default' => 'anthropic',
        'ai.providers.anthropic.key' => 'synthetic-test-key',
    ]);
});

/**
 * @param  array<string, mixed>  $structured
 */
function seoMetaResponse(array $structured, int $inputTokens = 120, int $outputTokens = 30): StructuredTextResponse
{
    return new StructuredTextResponse(
        $structured,
        json_encode($structured, JSON_THROW_ON_ERROR),
        new TextUsage($inputTokens, $outputTokens),
        new Meta('anthropic', 'claude-haiku-5-5'),
    );
}

/**
 * @return array<string, string>
 */
function seoMetaInput(array $overrides = []): array
{
    return [
        'subject' => 'article',
        'locale' => 'en',
        'title' => 'Fresh bread every morning',
        'text' => 'Our bakery bakes sourdough bread before sunrise.',
        ...$overrides,
    ];
}

test('an editor gets a normalized suggestion and the usage is audited without the prompt', function () {
    SeoMetaAgent::fake([seoMetaResponse([
        'meta_description' => '  Sourdough bread baked before sunrise.  ',
        'slug' => 'Fresh Bread Every Morning!',
    ])])->preventStrayPrompts();
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)
        ->postJson(route('admin.assistant.seo-meta'), seoMetaInput())
        ->assertOk()
        ->assertExactJson([
            'metaDescription' => 'Sourdough bread baked before sunrise.',
            'slug' => 'fresh-bread-every-morning',
        ]);

    SeoMetaAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Fresh bread every morning')
        && $prompt->contains('sourdough bread'));

    $entry = AuditLog::query()->sole();
    expect($entry->action)->toBe(AuditAction::AssistantSuggested)
        ->and($entry->actor_id)->toBe($editor->id)
        ->and($entry->subject_type)->toBe((new Article)->getMorphClass())
        ->and($entry->subject_id)->toBeNull()
        ->and($entry->changes)->toBe([
            'feature' => ['old' => null, 'new' => 'seo_meta'],
            'model' => ['old' => null, 'new' => 'claude-haiku-5-5'],
            'tokens' => ['old' => null, 'new' => 150],
        ])
        ->and(json_encode($entry->changes))->not->toContain('sourdough')
        ->and(Cache::get('assistant:tokens:'.now()->format('Y-m')))->toBe(150);
});

test('a suggestion longer than the column limits is cut to them', function () {
    SeoMetaAgent::fake([seoMetaResponse([
        'meta_description' => str_repeat('a', 200),
        'slug' => str_repeat('word-', 30),
    ])]);

    $response = $this->actingAs(User::factory()->editor()->create())
        ->postJson(route('admin.assistant.seo-meta'), seoMetaInput(['subject' => 'page']))
        ->assertOk();

    expect(mb_strlen($response->json('metaDescription')))->toBe(SeoMetaAgent::META_DESCRIPTION_MAX)
        ->and(mb_strlen($response->json('slug')))->toBeLessThanOrEqual(SeoMetaAgent::SLUG_MAX)
        ->and(AuditLog::query()->sole()->subject_type)->toBe((new Page)->getMorphClass());
});

test('the endpoint does not exist while the feature is unavailable', function (array $config) {
    config($config);
    SeoMetaAgent::fake();

    $this->actingAs(User::factory()->editor()->create())
        ->postJson(route('admin.assistant.seo-meta'), seoMetaInput())
        ->assertNotFound();

    SeoMetaAgent::assertNeverPrompted();
    expect(AuditLog::query()->count())->toBe(0);
})->with([
    'assistant disabled' => [['assistant.enabled' => false]],
    'feature disabled' => [['assistant.features.seo_meta' => false]],
    'no API key' => [['ai.providers.anthropic.key' => null]],
]);

test('users who may not create the content type are denied', function (string $role, string $subject) {
    SeoMetaAgent::fake();

    $this->actingAs(User::factory()->{$role}()->create())
        ->postJson(route('admin.assistant.seo-meta'), seoMetaInput(['subject' => $subject]))
        ->assertForbidden();

    SeoMetaAgent::assertNeverPrompted();
    expect(AuditLog::query()->count())->toBe(0);
})->with([
    'viewer, article' => ['viewer', 'article'],
    'viewer, page' => ['viewer', 'page'],
    'editor, unknown subject' => ['editor', 'user'],
]);

test('guests are sent to the login page', function () {
    SeoMetaAgent::fake();

    $this->post(route('admin.assistant.seo-meta'), seoMetaInput())->assertRedirect(route('login'));

    SeoMetaAgent::assertNeverPrompted();
});

test('the input is validated before the provider is called', function (array $overrides, string $field) {
    SeoMetaAgent::fake();

    $this->actingAs(User::factory()->editor()->create())
        ->postJson(route('admin.assistant.seo-meta'), seoMetaInput($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    SeoMetaAgent::assertNeverPrompted();
})->with([
    'unknown locale' => [['locale' => 'xx'], 'locale'],
    'missing title' => [['title' => ''], 'title'],
    'text too long' => [['text' => str_repeat('a', 20_001)], 'text'],
]);

test('a used up monthly budget stops suggestions before the provider is called', function () {
    Cache::put('assistant:tokens:'.now()->format('Y-m'), 1_000);
    SeoMetaAgent::fake();

    $this->actingAs(User::factory()->editor()->create())
        ->postJson(route('admin.assistant.seo-meta'), seoMetaInput())
        ->assertTooManyRequests()
        ->assertJsonPath('message', __('admin.assistant.errors.budget'))
        ->assertJsonPath('reason', 'budget');

    SeoMetaAgent::assertNeverPrompted();
    expect(AuditLog::query()->count())->toBe(0);
});

test('a provider failure is reported without an audit entry', function () {
    SeoMetaAgent::fake(fn () => throw new ProviderOverloadedException('Overloaded.'));

    $this->actingAs(User::factory()->editor()->create())
        ->postJson(route('admin.assistant.seo-meta'), seoMetaInput())
        ->assertServiceUnavailable()
        ->assertJsonPath('message', __('admin.assistant.errors.unavailable'))
        ->assertJsonPath('reason', 'unavailable');

    expect(AuditLog::query()->count())->toBe(0)
        ->and(Cache::get('assistant:tokens:'.now()->format('Y-m')))->toBeNull();
});

test('suggestions are rate limited per user', function () {
    config(['assistant.rate_limit_per_minute' => 1]);
    SeoMetaAgent::fake([
        seoMetaResponse(['meta_description' => 'One.', 'slug' => 'one']),
        seoMetaResponse(['meta_description' => 'Two.', 'slug' => 'two']),
    ]);
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->postJson(route('admin.assistant.seo-meta'), seoMetaInput())->assertOk();
    $this->actingAs($editor)->postJson(route('admin.assistant.seo-meta'), seoMetaInput())->assertTooManyRequests();

    $this->actingAs(User::factory()->editor()->create())
        ->postJson(route('admin.assistant.seo-meta'), seoMetaInput())
        ->assertOk();

    SeoMetaAgent::assertPromptedTimes(2);
});

test('the editors offer AI suggestions only while the feature is available', function (string $route, bool $enabled, bool $expected) {
    config(['assistant.enabled' => $enabled]);

    $this->actingAs(User::factory()->editor()->create())
        ->get(route($route))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia->where('can.suggestSeo', $expected));
})->with([
    'article, available' => ['admin.articles.create', true, true],
    'article, disabled' => ['admin.articles.create', false, false],
    'page, available' => ['admin.pages.create', true, true],
    'page, disabled' => ['admin.pages.create', false, false],
]);
