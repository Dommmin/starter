<?php

use App\Data\Home\FeaturesContentData;
use App\Data\Home\HeroContentData;
use App\Enums\AuditAction;
use App\Enums\HomeLinkTarget;
use App\Enums\HomeSectionType;
use App\Models\AuditLog;
use App\Models\HomeSection;
use App\Models\PageTranslation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

/**
 * A valid hero update payload.
 *
 * @param  array<string, mixed>  $content
 * @return array<string, mixed>
 */
function heroPayload(HomeSection $section, array $content = []): array
{
    return [
        'updated_at' => $section->updated_at?->toIso8601String(),
        'content' => [
            'eyebrow' => 'New eyebrow',
            'title' => 'New hero title',
            'description' => 'New description',
            'primaryAction' => ['label' => 'Write to us', 'target' => 'contact', 'pageId' => null],
            'secondaryAction' => null,
            ...$content,
        ],
    ];
}

/**
 * A features payload with the given number of items.
 *
 * @return array<string, mixed>
 */
function featuresPayload(HomeSection $section, int $items, array $item = []): array
{
    return [
        'updated_at' => $section->updated_at?->toIso8601String(),
        'content' => [
            'title' => 'Features',
            'description' => null,
            'items' => array_fill(0, $items, ['icon' => 'rocket', 'title' => 'Fast', 'description' => 'Very fast', ...$item]),
        ],
    ];
}

/**
 * The sections of one locale in a fixed order: hero, features, cta.
 *
 * @return list<HomeSection>
 */
function sectionsOf(string $locale = 'en'): array
{
    return [
        HomeSection::factory()->ofType(HomeSectionType::Hero)->create(['locale' => $locale, 'position' => 1]),
        HomeSection::factory()->ofType(HomeSectionType::Features)->create(['locale' => $locale, 'position' => 2]),
        HomeSection::factory()->ofType(HomeSectionType::Cta)->create(['locale' => $locale, 'position' => 3]),
    ];
}

test('guests are redirected and change nothing', function () {
    [$hero] = sectionsOf();
    $original = $hero->fresh()?->toArray();

    $this->get(route('admin.home-sections.index'))->assertRedirect(route('login'));
    $this->put(route('admin.home-sections.update', $hero), heroPayload($hero))->assertRedirect(route('login'));
    $this->patch(route('admin.home-sections.visibility', $hero), ['enabled' => true])->assertRedirect(route('login'));
    $this->put(route('admin.home-sections.reorder'), ['locale' => 'en', 'ids' => [$hero->id]])->assertRedirect(route('login'));

    expect($hero->fresh()?->toArray())->toBe($original)
        ->and(AuditLog::query()->count())->toBe(0);
});

test('users without a panel role get 403 and change nothing', function () {
    $user = User::factory()->create();
    [$hero, $features, $cta] = sectionsOf();
    $original = HomeSection::query()->orderBy('id')->get()->toArray();

    $this->actingAs($user)->get(route('admin.home-sections.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.home-sections.edit', $hero))->assertForbidden();
    $this->actingAs($user)->put(route('admin.home-sections.update', $hero), heroPayload($hero))->assertForbidden();
    $this->actingAs($user)->patch(route('admin.home-sections.visibility', $hero), ['enabled' => true])->assertForbidden();
    $this->actingAs($user)->put(route('admin.home-sections.reorder'), [
        'locale' => 'en',
        'ids' => [$cta->id, $features->id, $hero->id],
    ])->assertForbidden();

    expect(HomeSection::query()->orderBy('id')->get()->toArray())->toBe($original)
        ->and(AuditLog::query()->count())->toBe(0);
});

test('the list shows the sections of the selected locale in order', function () {
    $editor = User::factory()->editor()->create();
    [$hero, $features] = sectionsOf();
    $features->forceFill(['position' => 1])->save();
    $hero->forceFill(['position' => 2])->save();
    sectionsOf('pl');

    $this->actingAs($editor)->get(route('admin.home-sections.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/home-sections/index', false)
            ->where('locale', 'en')
            ->has('items', 3)
            ->where('items.0.id', $features->id)
            ->where('items.0.type', 'features')
            ->where('items.0.anchor', 'features')
            ->where('items.1.id', $hero->id)
            ->where('items.1.title', 'Hero')
            ->where('can', ['update' => true, 'reorder' => true])
        );

    $this->actingAs($editor)->get(route('admin.home-sections.index', ['locale' => 'pl']))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('locale', 'pl')
            ->has('items', 3)
            ->where('items.0.type', 'hero')
        );

    $this->actingAs($editor)->get(route('admin.home-sections.index', ['locale' => 'xx']))
        ->assertSessionHasErrors('locale');
});

test('the edit screen exposes the typed content, link targets and published pages of the locale', function () {
    $editor = User::factory()->editor()->create();
    [$hero] = sectionsOf();
    $published = PageTranslation::factory()->published()->create(['title' => 'About us']);
    PageTranslation::factory()->draft()->create();
    PageTranslation::factory()->locale('pl')->published()->create();

    $this->actingAs($editor)->get(route('admin.home-sections.edit', $hero))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/home-sections/edit', false)
            ->where('section.id', $hero->id)
            ->where('section.type', 'hero')
            ->where('section.locale', 'en')
            ->where('section.updatedAt', $hero->updated_at?->toIso8601String())
            ->where('section.content.title', 'Hero')
            ->where('locale.code', 'en')
            ->where('linkTargets', ['contact', 'articles', 'login', 'register', 'page'])
            ->where('pages', [['id' => $published->page_id, 'title' => 'About us']])
        );
});

test('an editor saves the content of a section and the change is audited', function () {
    $editor = User::factory()->editor()->create();
    [$hero] = sectionsOf();

    $this->travel(5)->minutes();

    $this->actingAs($editor)->put(route('admin.home-sections.update', $hero), heroPayload($hero))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.home-sections.edit', $hero))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('admin.homeSections.updated')]);

    $content = $hero->fresh()?->content;
    expect($content)->toBeInstanceOf(HeroContentData::class)
        ->and($content?->title)->toBe('New hero title')
        ->and($content?->primaryAction?->target)->toBe(HomeLinkTarget::Contact);

    $log = AuditLog::query()->sole();
    expect($log->action)->toBe(AuditAction::HomeSectionUpdated)
        ->and($log->actor_id)->toBe($editor->id)
        ->and($log->subject_id)->toBe($hero->id)
        ->and(array_keys($log->changes ?? []))->toEqualCanonicalizing([
            'content.eyebrow', 'content.title', 'content.description', 'content.primaryAction',
        ]);
});

test('feature items are stored as the closed schema', function () {
    $editor = User::factory()->editor()->create();
    [, $features] = sectionsOf();

    $this->actingAs($editor)->put(route('admin.home-sections.update', $features), featuresPayload($features, 2))
        ->assertSessionHasNoErrors();

    $stored = json_decode((string) HomeSection::query()->toBase()->where('id', $features->id)->value('content'), true);
    expect($stored)->toBe([
        'items' => [
            ['title' => 'Fast', 'description' => 'Very fast', 'icon' => 'rocket'],
            ['title' => 'Fast', 'description' => 'Very fast', 'icon' => 'rocket'],
        ],
        'title' => 'Features',
        'description' => null,
    ]);
});

test('unknown keys are rejected with 422 at every level and nothing is stored', function (array $content) {
    $editor = User::factory()->editor()->create();
    [$hero] = sectionsOf();
    $original = $hero->fresh()?->toArray();

    $this->actingAs($editor)
        ->putJson(route('admin.home-sections.update', $hero), heroPayload($hero, $content))
        ->assertUnprocessable();

    expect($hero->fresh()?->toArray())->toBe($original)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    'top level' => [['script' => 'alert(1)']],
    'action' => [['primaryAction' => ['label' => 'Go', 'target' => 'contact', 'pageId' => null, 'url' => 'https://evil.test']]],
    'unknown target' => [['primaryAction' => ['label' => 'Go', 'target' => 'https://evil.test', 'pageId' => null]]],
]);

test('unknown item keys and icons are rejected', function (array $item) {
    $editor = User::factory()->editor()->create();
    [, $features] = sectionsOf();

    $this->actingAs($editor)
        ->putJson(route('admin.home-sections.update', $features), featuresPayload($features, 1, $item))
        ->assertUnprocessable();

    expect($features->fresh()?->content)->toBeInstanceOf(FeaturesContentData::class)
        ->and($features->fresh()?->content->items)->toBe([]);
})->with([
    'extra key' => [['html' => '<b>x</b>']],
    'unknown icon' => [['icon' => 'skull']],
]);

test('the section type cannot be changed through the payload', function () {
    $editor = User::factory()->editor()->create();
    [$hero] = sectionsOf();

    $this->actingAs($editor)
        ->putJson(route('admin.home-sections.update', $hero), [...heroPayload($hero), 'type' => 'cta'])
        ->assertRedirect();

    expect($hero->fresh()?->type)->toBe(HomeSectionType::Hero);
});

test('limits and plain text are enforced', function (string $type, array $content, string $error) {
    $editor = User::factory()->editor()->create();
    $section = HomeSection::factory()->ofType(HomeSectionType::from($type))->create();

    $this->actingAs($editor)
        ->putJson(route('admin.home-sections.update', $section), [
            'updated_at' => $section->updated_at?->toIso8601String(),
            'content' => $content,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);

    expect(AuditLog::query()->count())->toBe(0);
})->with([
    'too many features' => ['features', ['items' => array_fill(0, 13, ['icon' => null, 'title' => 'T', 'description' => 'D'])], 'content.items'],
    'too many testimonials' => ['testimonials', ['items' => array_fill(0, 7, ['author' => 'A', 'role' => null, 'quote' => 'Q'])], 'content.items'],
    'long hero title' => ['hero', ['title' => str_repeat('a', 121)], 'content.title'],
    'missing hero title' => ['hero', ['title' => ''], 'content.title'],
    'markup in title' => ['hero', ['title' => '<img src=x onerror=alert(1)>'], 'content.title'],
    'long feature title' => ['features', ['items' => [['icon' => null, 'title' => str_repeat('a', 81), 'description' => 'D']]], 'content.items.0.title'],
    'articles limit' => ['latest_articles', ['limit' => 7], 'content.limit'],
    'articles limit zero' => ['latest_articles', ['limit' => 0], 'content.limit'],
    'faq limit' => ['faq', ['limit' => 51], 'content.limit'],
    'long label' => ['cta', ['title' => 'T', 'primaryAction' => ['label' => str_repeat('a', 41), 'target' => 'contact']], 'content.primaryAction.label'],
]);

test('a page action needs a page published in the section locale', function () {
    $editor = User::factory()->editor()->create();
    [$hero] = sectionsOf();
    $draft = PageTranslation::factory()->draft()->create();
    $polish = PageTranslation::factory()->locale('pl')->published()->create();
    $published = PageTranslation::factory()->published()->create();

    foreach ([null, $draft->page_id, $polish->page_id] as $pageId) {
        $this->actingAs($editor)
            ->putJson(route('admin.home-sections.update', $hero), heroPayload($hero, [
                'primaryAction' => ['label' => 'About', 'target' => 'page', 'pageId' => $pageId],
            ]))
            ->assertJsonValidationErrors('content.primaryAction.pageId');
    }

    $this->actingAs($editor)
        ->putJson(route('admin.home-sections.update', $hero), heroPayload($hero, [
            'primaryAction' => ['label' => 'About', 'target' => 'contact', 'pageId' => $published->page_id],
        ]))
        ->assertJsonValidationErrors('content.primaryAction.pageId');

    $this->actingAs($editor)
        ->put(route('admin.home-sections.update', $hero), heroPayload($hero, [
            'primaryAction' => ['label' => 'About', 'target' => 'page', 'pageId' => $published->page_id],
        ]))
        ->assertSessionHasNoErrors();

    expect($hero->fresh()?->content->primaryAction?->pageId)->toBe($published->page_id);
});

test('a stale version is rejected with a conflict error and nothing changes', function () {
    $editor = User::factory()->editor()->create();
    [$hero] = sectionsOf();
    $original = $hero->fresh()?->toArray();

    $this->actingAs($editor)
        ->from(route('admin.home-sections.edit', $hero))
        ->put(route('admin.home-sections.update', $hero), [
            ...heroPayload($hero),
            'updated_at' => $hero->updated_at?->subMinute()->toIso8601String(),
        ])
        ->assertRedirect(route('admin.home-sections.edit', $hero))
        ->assertSessionHasErrors(['conflict' => __('admin.homeSections.conflict')]);

    expect($hero->fresh()?->toArray())->toBe($original)
        ->and(AuditLog::query()->count())->toBe(0);
});

test('an editor shows and hides a section', function () {
    $editor = User::factory()->editor()->create();
    [$hero] = sectionsOf();

    $this->actingAs($editor)->patch(route('admin.home-sections.visibility', $hero), ['enabled' => true])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.home-sections.index', ['locale' => 'en']));

    expect($hero->fresh()?->enabled)->toBeTrue();

    $log = AuditLog::query()->sole();
    expect($log->action)->toBe(AuditAction::HomeSectionToggled)
        ->and($log->changes)->toBe(['enabled' => ['old' => false, 'new' => true]]);

    $this->actingAs($editor)->patch(route('admin.home-sections.visibility', $hero), ['enabled' => true]);
    expect(AuditLog::query()->count())->toBe(1);

    $this->actingAs($editor)->patch(route('admin.home-sections.visibility', $hero), ['enabled' => 'maybe'])
        ->assertSessionHasErrors('enabled');
    expect($hero->fresh()?->enabled)->toBeTrue();
});

test('an editor reorders the sections of a locale in one step', function () {
    $editor = User::factory()->editor()->create();
    [$hero, $features, $cta] = sectionsOf();
    $polishHero = sectionsOf('pl')[0];

    $this->actingAs($editor)->put(route('admin.home-sections.reorder'), [
        'locale' => 'en',
        'ids' => [$cta->id, $hero->id, $features->id],
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.home-sections.index', ['locale' => 'en']));

    expect($cta->fresh()?->position)->toBe(1)
        ->and($hero->fresh()?->position)->toBe(2)
        ->and($features->fresh()?->position)->toBe(3)
        ->and($polishHero->fresh()?->position)->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::HomeSectionsReordered)->count())->toBe(3);
});

test('a reorder with a foreign, missing or duplicate id changes nothing', function (Closure $ids) {
    $editor = User::factory()->editor()->create();
    $sections = sectionsOf();
    $polish = sectionsOf('pl');
    $original = HomeSection::query()->orderBy('id')->pluck('position', 'id')->all();

    $this->actingAs($editor)
        ->putJson(route('admin.home-sections.reorder'), ['locale' => 'en', 'ids' => $ids($sections, $polish)])
        ->assertUnprocessable();

    expect(HomeSection::query()->orderBy('id')->pluck('position', 'id')->all())->toBe($original)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    'foreign id' => [fn (array $en, array $pl) => [$en[2]->id, $en[1]->id, $pl[0]->id]],
    'missing id' => [fn (array $en, array $pl) => [$en[2]->id, $en[1]->id]],
    'extra id' => [fn (array $en, array $pl) => [$en[2]->id, $en[1]->id, $en[0]->id, $pl[0]->id]],
    'duplicate id' => [fn (array $en, array $pl) => [$en[2]->id, $en[2]->id, $en[0]->id]],
    'unknown id' => [fn (array $en, array $pl) => [$en[2]->id, $en[1]->id, 999999]],
]);
