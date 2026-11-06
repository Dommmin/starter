<?php

use App\Actions\Assistant\Exceptions\AssistantUnavailable;
use App\Actions\Media\RecordMediaScanResult;
use App\Ai\Agents\MediaAltTextAgent;
use App\Enums\AuditAction;
use App\Enums\MediaStatus;
use App\Jobs\GenerateImageVariants;
use App\Jobs\GenerateMediaAltText;
use App\Models\AuditLog;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\Media\ScanResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Files\StoredImage;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\StructuredTextResponse;
use Tests\Fakes\MediaFixtures;

beforeEach(function () {
    config([
        'fortify.require_two_factor_for_admin' => false,
        'assistant.enabled' => true,
        'assistant.features.media_alt_text' => true,
        'assistant.monthly_token_budget' => 10_000,
        'ai.default' => 'anthropic',
        'ai.providers.anthropic.key' => 'synthetic-test-key',
    ]);
    Storage::fake('media');
    Storage::fake('public');
});

function altTextResponse(string $alt, int $inputTokens = 900, int $outputTokens = 20): StructuredTextResponse
{
    return new StructuredTextResponse(
        ['alt' => $alt],
        json_encode(['alt' => $alt], JSON_THROW_ON_ERROR),
        new TextUsage($inputTokens, $outputTokens),
        new Meta('anthropic', 'claude-haiku-5-5'),
    );
}

/**
 * A clean JPEG with its original on the faked private disk.
 *
 * @param  array<string, mixed>  $attributes
 */
function cleanImageAsset(array $attributes = []): MediaAsset
{
    $asset = MediaAsset::factory()->clean()->create($attributes);
    Storage::disk('media')->put($asset->path, MediaFixtures::jpegBytes());

    return $asset;
}

function runAltTextJob(MediaAsset $asset): void
{
    app()->call([new GenerateMediaAltText($asset->id), 'handle']);
}

test('an editor gets a normalized alt text suggestion and the usage is audited without the prompt', function () {
    MediaAltTextAgent::fake([altTextResponse("  A loaf of  sourdough\n bread on a board.  ")])->preventStrayPrompts();
    $editor = User::factory()->editor()->create();
    $asset = cleanImageAsset();

    $this->actingAs($editor)
        ->postJson(route('admin.assistant.media-alt-text', $asset))
        ->assertOk()
        ->assertExactJson(['alt' => 'A loaf of sourdough bread on a board.']);

    MediaAltTextAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->attachments->sole() instanceof StoredImage
        && $prompt->attachments->sole()->path === $asset->path
        && $prompt->attachments->sole()->disk === 'media');

    $entry = AuditLog::query()->sole();
    expect($entry->action)->toBe(AuditAction::AssistantSuggested)
        ->and($entry->actor_id)->toBe($editor->id)
        ->and($entry->subject_id)->toBe($asset->id)
        // toEqual: MySQL JSON does not keep the key order.
        ->and($entry->changes)->toEqual([
            'feature' => ['old' => null, 'new' => 'media_alt_text'],
            'model' => ['old' => null, 'new' => 'claude-haiku-5-5'],
            'tokens' => ['old' => null, 'new' => 920],
        ])
        ->and($asset->refresh()->alt)->toBeNull();
});

test('a downscaled public variant is sent instead of the original and long text is cut', function () {
    MediaAltTextAgent::fake([altTextResponse(str_repeat('a', 600))]);
    $asset = MediaAsset::factory()->withVariants()->create();
    $variant = collect($asset->variants)->first(fn (array $variant): bool => $variant['format'] === 'webp' && $variant['width'] === 640);
    Storage::disk('public')->put($variant['path'], 'variant-bytes');

    $response = $this->actingAs(User::factory()->editor()->create())
        ->postJson(route('admin.assistant.media-alt-text', $asset))
        ->assertOk();

    expect(mb_strlen($response->json('alt')))->toBe(MediaAltTextAgent::ALT_MAX);
    MediaAltTextAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->attachments->sole()->path === $variant['path']
        && $prompt->attachments->sole()->disk === 'public');
});

test('the endpoint does not exist while the feature is unavailable', function (array $config) {
    config($config);
    MediaAltTextAgent::fake();

    $this->actingAs(User::factory()->editor()->create())
        ->postJson(route('admin.assistant.media-alt-text', cleanImageAsset()))
        ->assertNotFound();

    MediaAltTextAgent::assertNeverPrompted();
    expect(AuditLog::query()->count())->toBe(0);
})->with([
    'assistant disabled' => [['assistant.enabled' => false]],
    'feature disabled' => [['assistant.features.media_alt_text' => false]],
    'no API key' => [['ai.providers.anthropic.key' => null]],
]);

test('users who may not update the asset are denied', function () {
    MediaAltTextAgent::fake();

    $this->actingAs(User::factory()->viewer()->create())
        ->postJson(route('admin.assistant.media-alt-text', cleanImageAsset()))
        ->assertForbidden();

    MediaAltTextAgent::assertNeverPrompted();
    expect(AuditLog::query()->count())->toBe(0);
});

test('files that are not clean, supported images never reach the provider', function (Closure $makeAsset) {
    MediaAltTextAgent::fake();
    /** @var MediaAsset $asset */
    $asset = $makeAsset();
    Storage::disk('media')->put($asset->path, 'bytes');

    $this->actingAs(User::factory()->editor()->create())
        ->postJson(route('admin.assistant.media-alt-text', $asset))
        ->assertNotFound();

    MediaAltTextAgent::assertNeverPrompted();
    expect(AuditLog::query()->count())->toBe(0);
})->with([
    'quarantined image' => [fn () => MediaAsset::factory()->create()],
    'rejected image' => [fn () => MediaAsset::factory()->rejected()->create()],
    'clean document' => [fn () => MediaAsset::factory()->clean()->pdf()->create()],
    'avif without variants' => [fn () => MediaAsset::factory()->clean()->create(['mime' => 'image/avif', 'path' => 'x/original.avif'])],
    'original over the provider limit' => [fn () => MediaAsset::factory()->clean()->create(['size' => 6 * 1024 * 1024])],
]);

test('a used up budget and a provider failure are reported without an audit entry', function () {
    $asset = cleanImageAsset();
    $editor = User::factory()->editor()->create();

    Cache::put('assistant:tokens:'.now()->format('Y-m'), 10_000);
    MediaAltTextAgent::fake();
    $this->actingAs($editor)
        ->postJson(route('admin.assistant.media-alt-text', $asset))
        ->assertTooManyRequests()
        ->assertJsonPath('reason', 'budget');
    MediaAltTextAgent::assertNeverPrompted();

    Cache::forget('assistant:tokens:'.now()->format('Y-m'));
    MediaAltTextAgent::fake(fn () => throw new ProviderOverloadedException('Overloaded.'));
    $this->actingAs($editor)
        ->postJson(route('admin.assistant.media-alt-text', $asset))
        ->assertServiceUnavailable()
        ->assertJsonPath('reason', 'unavailable');

    expect(AuditLog::query()->count())->toBe(0);
});

test('the media editor offers the suggestion only for clean images while the feature is available', function (string $role, Closure $makeAsset, bool $enabled, bool $expected) {
    config(['assistant.enabled' => $enabled]);
    /** @var MediaAsset $asset */
    $asset = $makeAsset();

    $this->actingAs(User::factory()->{$role}()->create())
        ->get(route('admin.media.edit', $asset))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia->where('can.suggestAlt', $expected));
})->with([
    'editor, clean image' => ['editor', fn () => MediaAsset::factory()->clean()->create(), true, true],
    'editor, feature disabled' => ['editor', fn () => MediaAsset::factory()->clean()->create(), false, false],
    'editor, quarantined image' => ['editor', fn () => MediaAsset::factory()->create(), true, false],
    'editor, clean document' => ['editor', fn () => MediaAsset::factory()->clean()->pdf()->create(), true, false],
    'viewer, clean image' => ['viewer', fn () => MediaAsset::factory()->clean()->create(), true, false],
]);

test('the job fills in a missing alt text as the system actor', function () {
    MediaAltTextAgent::fake([altTextResponse('A loaf of bread.')])->preventStrayPrompts();
    $asset = cleanImageAsset();

    runAltTextJob($asset);

    expect($asset->refresh()->alt)->toBe('A loaf of bread.');
    $suggested = AuditLog::query()->where('action', AuditAction::AssistantSuggested)->sole();
    $updated = AuditLog::query()->where('action', AuditAction::MediaUpdated)->sole();
    expect($suggested->actor_id)->toBeNull()
        ->and($updated->actor_id)->toBeNull()
        // toEqual: MySQL JSON does not keep the key order.
        ->and($updated->changes)->toEqual(['alt' => ['old' => null, 'new' => 'A loaf of bread.']]);
});

test('running the job again has no further effect', function () {
    MediaAltTextAgent::fake([altTextResponse('A loaf of bread.'), altTextResponse('Something else.')]);
    $asset = cleanImageAsset();

    runAltTextJob($asset);
    runAltTextJob($asset);

    MediaAltTextAgent::assertPromptedTimes(1);
    expect($asset->refresh()->alt)->toBe('A loaf of bread.')
        ->and(AuditLog::query()->where('action', AuditAction::MediaUpdated)->count())->toBe(1);
});

test('the job never sends a file that is not a clean image and never replaces a human alt text', function (Closure $makeAsset) {
    MediaAltTextAgent::fake();
    /** @var MediaAsset $asset */
    $asset = $makeAsset();
    $alt = $asset->alt;
    Storage::disk('media')->put($asset->path, 'bytes');

    runAltTextJob($asset);

    MediaAltTextAgent::assertNeverPrompted();
    expect($asset->refresh()->alt)->toBe($alt)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    'quarantined image' => [fn () => MediaAsset::factory()->create()],
    'rejected image' => [fn () => MediaAsset::factory()->rejected()->create()],
    'clean document' => [fn () => MediaAsset::factory()->clean()->pdf()->create()],
    'human alt text' => [fn () => MediaAsset::factory()->clean()->create(['alt' => 'Written by the editor.'])],
]);

test('an alt text saved while the provider was answering wins', function () {
    $asset = cleanImageAsset();
    MediaAltTextAgent::fake(function () use ($asset): StructuredTextResponse {
        MediaAsset::query()->whereKey($asset->id)->update(['alt' => 'Written by the editor.']);

        return altTextResponse('A loaf of bread.');
    });

    runAltTextJob($asset);

    expect($asset->refresh()->alt)->toBe('Written by the editor.')
        ->and(AuditLog::query()->where('action', AuditAction::MediaUpdated)->count())->toBe(0);
});

test('the job does nothing while the feature is unavailable or the budget is used up', function (Closure $setUp) {
    MediaAltTextAgent::fake();
    $asset = cleanImageAsset();
    $setUp();

    runAltTextJob($asset);

    MediaAltTextAgent::assertNeverPrompted();
    expect($asset->refresh()->alt)->toBeNull();
})->with([
    'feature disabled' => [fn () => config(['assistant.features.media_alt_text' => false])],
    'budget used up' => [fn () => Cache::put('assistant:tokens:'.now()->format('Y-m'), 10_000)],
]);

test('a provider failure is thrown so the job is retried', function () {
    MediaAltTextAgent::fake(fn () => throw new ProviderOverloadedException('Overloaded.'));
    $asset = cleanImageAsset();

    expect(fn () => runAltTextJob($asset))->toThrow(AssistantUnavailable::class);
    expect($asset->refresh()->alt)->toBeNull()
        ->and(AuditLog::query()->count())->toBe(0);
});

test('a clean image queues the alt text job after its variants only while the feature is available', function (bool $enabled) {
    Queue::fake();
    config(['assistant.enabled' => $enabled]);
    $asset = MediaAsset::factory()->create();

    app(RecordMediaScanResult::class)->handle($asset, ScanResult::skipped());

    expect($asset->refresh()->status)->toBe(MediaStatus::Clean);

    if ($enabled) {
        Queue::assertPushedWithChain(GenerateImageVariants::class, [GenerateMediaAltText::class]);
    } else {
        Queue::assertPushedWithoutChain(GenerateImageVariants::class);
    }
})->with(['available' => [true], 'disabled' => [false]]);
