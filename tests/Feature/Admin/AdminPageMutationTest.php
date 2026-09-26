<?php

use App\Enums\PublicationStatus;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function translationInput(string $title, string $slug, string $status = 'draft', array $overrides = []): array
{
    return [
        'title' => $title,
        'slug' => $slug,
        'meta_description' => 'Short summary.',
        'body' => [
            'type' => 'doc',
            'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hello']]]],
        ],
        'status' => $status,
        ...$overrides,
    ];
}

test('the create screen receives an empty translation for every public locale', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->get(route('admin.pages.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/pages/create', false)
            ->where('page.id', null)
            ->where('page.updatedAt', null)
            ->where('page.translations.en', [
                'title' => '',
                'slug' => '',
                'metaDescription' => null,
                'body' => null,
                'status' => 'draft',
                'publishedAt' => null,
            ])
            ->has('page.translations', 3)
            ->where('locales.default', 'en')
            ->where('can.publish', true)
        );
});

test('an editor creates a published page with an optional second translation', function () {
    $this->freezeSecond();
    $editor = User::factory()->editor()->create();

    $response = $this->actingAs($editor)->post(route('admin.pages.store'), [
        'translations' => [
            'en' => translationInput('About us', 'about-us', 'published'),
            'pl' => translationInput('O nas', 'o-nas'),
            'de' => ['title' => '', 'slug' => '', 'status' => 'draft'],
        ],
    ]);

    $page = Page::query()->sole();
    $response->assertRedirect(route('admin.pages.edit', $page))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('admin.pages.created')]);

    expect($page->created_by)->toBe($editor->id)
        ->and($page->updated_by)->toBe($editor->id);

    $en = $page->translations()->where('locale', 'en')->sole();
    expect($en->status)->toBe(PublicationStatus::Published)
        ->and($en->published_at?->equalTo(now()))->toBeTrue()
        ->and($en->body['content'][0]['type'])->toBe('paragraph');

    $pl = $page->translations()->where('locale', 'pl')->sole();
    expect($pl->status)->toBe(PublicationStatus::Draft)
        ->and($pl->published_at)->toBeNull()
        ->and($page->translations()->where('locale', 'de')->exists())->toBeFalse();
});

test('the edit screen exposes the stored translations and the version for locking', function () {
    $admin = User::factory()->admin()->create();
    $page = Page::factory()->published()->create();
    $translation = $page->translations()->sole();

    $this->actingAs($admin)->get(route('admin.pages.edit', $page))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/pages/edit', false)
            ->where('page.id', $page->id)
            ->where('page.updatedAt', $page->updated_at->toIso8601String())
            ->where('page.translations.en.title', $translation->title)
            ->where('page.translations.en.status', 'published')
            ->where('page.translations.en.body', $translation->body)
            ->where('page.translations.pl.title', '')
            ->where('can.delete', true)
        );
});

test('updating keeps the first publication date and removes translations with an empty title', function () {
    $admin = User::factory()->admin()->create();
    $page = Page::factory()->create();
    $firstPublishedAt = Carbon::parse('2026-01-01 10:00:00');
    PageTranslation::factory()->for($page)->published()->create(['slug' => 'old', 'published_at' => $firstPublishedAt]);
    PageTranslation::factory()->for($page)->locale('pl')->create(['slug' => 'stara']);

    $this->travel(5)->minutes();

    $this->actingAs($admin)->put(route('admin.pages.update', $page), [
        'updated_at' => $page->updated_at->toIso8601String(),
        'translations' => [
            'en' => translationInput('New title', 'new-title', 'published'),
            'pl' => ['title' => '', 'slug' => 'stara', 'status' => 'draft'],
        ],
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.pages.edit', $page))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('admin.pages.updated')]);

    $en = $page->translations()->where('locale', 'en')->sole();
    expect($en->title)->toBe('New title')
        ->and($en->slug)->toBe('new-title')
        ->and($en->published_at?->equalTo($firstPublishedAt))->toBeTrue()
        ->and($page->translations()->where('locale', 'pl')->exists())->toBeFalse()
        ->and($page->fresh()->updated_by)->toBe($admin->id)
        ->and($page->fresh()->updated_at->greaterThan($page->updated_at))->toBeTrue();
});

test('a stale version is rejected with a conflict error and nothing changes', function () {
    $admin = User::factory()->admin()->create();
    $page = Page::factory()->draft()->create();
    $staleVersion = $page->updated_at->subMinute()->toIso8601String();
    $original = $page->translations()->sole()->title;

    $this->actingAs($admin)
        ->from(route('admin.pages.edit', $page))
        ->put(route('admin.pages.update', $page), [
            'updated_at' => $staleVersion,
            'translations' => ['en' => translationInput('Overwritten', 'overwritten')],
        ])
        ->assertRedirect(route('admin.pages.edit', $page))
        ->assertSessionHasErrors(['conflict' => __('admin.pages.conflict')]);

    expect($page->translations()->sole()->title)->toBe($original);
});

test('the default public locale translation is required', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->post(route('admin.pages.store'), [
        'translations' => ['pl' => translationInput('O nas', 'o-nas')],
    ])->assertSessionHasErrors(['translations.en', 'translations.en.title']);

    expect(Page::query()->count())->toBe(0);
});

test('slugs must be lowercase kebab case and not a reserved path', function (string $slug) {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->post(route('admin.pages.store'), [
        'translations' => ['en' => translationInput('Title', $slug)],
    ])->assertSessionHasErrors('translations.en.slug');

    expect(Page::query()->count())->toBe(0);
})->with(['About-Us', 'about us', 'about--us', '-about', 'über', 'login', 'admin', 'settings', 'about', 'pl']);

test('slugs are unique per locale but may repeat across locales', function () {
    $editor = User::factory()->editor()->create();
    PageTranslation::factory()->create(['slug' => 'contact']);

    $this->actingAs($editor)->post(route('admin.pages.store'), [
        'translations' => ['en' => translationInput('Contact', 'contact')],
    ])->assertSessionHasErrors('translations.en.slug');

    $this->actingAs($editor)->post(route('admin.pages.store'), [
        'translations' => [
            'en' => translationInput('Contact us', 'contact-us'),
            'pl' => translationInput('Kontakt', 'contact'),
        ],
    ])->assertSessionHasNoErrors();

    expect(PageTranslation::query()->where('slug', 'contact')->count())->toBe(2);
});

test('updating a page may keep its own slug', function () {
    $admin = User::factory()->admin()->create();
    $page = Page::factory()->create();
    PageTranslation::factory()->for($page)->create(['slug' => 'kept']);

    $this->actingAs($admin)->put(route('admin.pages.update', $page), [
        'updated_at' => $page->updated_at->toIso8601String(),
        'translations' => ['en' => translationInput('Kept', 'kept')],
    ])->assertSessionHasNoErrors();
});

test('rich text with unknown nodes or unsafe links is rejected', function (array $body) {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->post(route('admin.pages.store'), [
        'translations' => ['en' => translationInput('Title', 'title', overrides: ['body' => $body])],
    ])->assertSessionHasErrors('translations.en.body');

    expect(Page::query()->count())->toBe(0);
})->with([
    'not a document' => [['type' => 'paragraph']],
    'image node' => [['type' => 'doc', 'content' => [['type' => 'image', 'attrs' => ['src' => 'https://example.com/x.png']]]]],
    'javascript link' => [['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
        ['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]],
    ]]]]],
    'h1 heading' => [['type' => 'doc', 'content' => [['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'x']]]]]],
]);

test('oversized rich text is rejected', function () {
    $editor = User::factory()->editor()->create();
    $body = ['type' => 'doc', 'content' => array_fill(0, 2100, [
        'type' => 'paragraph', 'content' => [['type' => 'text', 'text' => str_repeat('a', 100)]],
    ])];

    $this->actingAs($editor)->post(route('admin.pages.store'), [
        'translations' => ['en' => translationInput('Title', 'title', overrides: ['body' => $body])],
    ])->assertSessionHasErrors(['translations.en.body' => __('admin.pages.validation.bodyTooLarge')]);
});

test('an editor cannot delete a page', function () {
    $editor = User::factory()->editor()->create();
    $page = Page::factory()->published()->create();

    $this->actingAs($editor)->delete(route('admin.pages.destroy', $page))->assertForbidden();

    expect(Page::query()->whereKey($page->id)->exists())->toBeTrue()
        ->and(PageTranslation::query()->count())->toBe(1);
});

test('an admin deletes a page with all translations', function () {
    $admin = User::factory()->admin()->create();
    $page = Page::factory()->published()->create();
    PageTranslation::factory()->for($page)->locale('pl')->create();

    $this->actingAs($admin)->delete(route('admin.pages.destroy', $page))
        ->assertRedirect(route('admin.pages.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('admin.pages.deleted')]);

    expect(Page::query()->count())->toBe(0)
        ->and(PageTranslation::query()->count())->toBe(0);
});

test('users without a panel role cannot mutate pages', function () {
    $user = User::factory()->create();
    $page = Page::factory()->draft()->create();
    $translation = $page->translations()->sole();

    $this->actingAs($user)->post(route('admin.pages.store'), [
        'translations' => ['en' => translationInput('Title', 'title')],
    ])->assertForbidden();

    $this->actingAs($user)->put(route('admin.pages.update', $page), [
        'updated_at' => $page->updated_at->toIso8601String(),
        'translations' => ['en' => translationInput('Hacked', 'hacked', 'published')],
    ])->assertForbidden();

    $this->actingAs($user)->delete(route('admin.pages.destroy', $page))->assertForbidden();

    expect(Page::query()->count())->toBe(1)
        ->and($translation->fresh()->title)->toBe($translation->title)
        ->and($translation->fresh()->status)->toBe(PublicationStatus::Draft);
});

test('publishing requires the publish ability', function () {
    $editor = User::factory()->editor()->create();
    Gate::before(fn (User $user, string $ability) => $ability === 'publish' ? false : null);

    $this->actingAs($editor)->post(route('admin.pages.store'), [
        'translations' => ['en' => translationInput('Title', 'title', 'published')],
    ])->assertForbidden();

    $this->actingAs($editor)->post(route('admin.pages.store'), [
        'translations' => ['en' => translationInput('Title', 'title')],
    ])->assertSessionHasNoErrors();

    expect(PageTranslation::query()->sole()->status)->toBe(PublicationStatus::Draft);
});
