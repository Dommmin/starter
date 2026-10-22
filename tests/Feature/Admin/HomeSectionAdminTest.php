<?php

use App\Data\Home\FeaturesContentData;
use App\Data\Home\HeroContentData;
use App\Data\Home\PortfolioContentData;
use App\Data\Home\PricingContentData;
use App\Data\Home\TeamContentData;
use App\Data\Home\TestimonialsContentData;
use App\Enums\AuditAction;
use App\Enums\HomeLinkTarget;
use App\Enums\HomeSectionType;
use App\Models\AuditLog;
use App\Models\HomeSection;
use App\Models\MediaAsset;
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
            'imageId' => null,
            'stats' => [],
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
            ->where('linkTargets', ['contact', 'portfolio', 'articles', 'login', 'register', 'page'])
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
    expect($stored)->toEqual([
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

test('the hero stores its stats and a usable DAM image', function () {
    $editor = User::factory()->editor()->create();
    [$hero] = sectionsOf();
    $image = MediaAsset::factory()->withVariants()->create();

    $this->actingAs($editor)->put(route('admin.home-sections.update', $hero), heroPayload($hero, [
        'imageId' => $image->id,
        'stats' => [['value' => '300+', 'label' => 'Projects delivered']],
    ]))->assertSessionHasNoErrors();

    $content = $hero->fresh()?->content;
    expect($content)->toBeInstanceOf(HeroContentData::class)
        ->and($content?->imageId)->toBe($image->id)
        ->and($content?->stats[0]->value)->toBe('300+');
});

test('testimonials store their proof figures', function () {
    $editor = User::factory()->editor()->create();
    $testimonials = HomeSection::factory()->ofType(HomeSectionType::Testimonials)->create();

    $this->actingAs($editor)->put(route('admin.home-sections.update', $testimonials), [
        'updated_at' => $testimonials->updated_at?->toIso8601String(),
        'content' => [
            'title' => 'Clients',
            'items' => [['author' => 'Anna', 'role' => null, 'quote' => 'Great work.']],
            'stats' => [['value' => '4.9/5', 'label' => 'Google rating']],
        ],
    ])->assertSessionHasNoErrors();

    $content = $testimonials->fresh()?->content;
    expect($content)->toBeInstanceOf(TestimonialsContentData::class)
        ->and($content?->stats[0]->value)->toBe('4.9/5')
        ->and($content?->stats[0]->label)->toBe('Google rating');
});

test('the hero rejects an image that is not a usable DAM image', function () {
    $editor = User::factory()->editor()->create();
    [$hero] = sectionsOf();
    $original = $hero->fresh()?->toArray();
    $quarantined = MediaAsset::factory()->create();

    foreach ([$quarantined->id, 999999] as $imageId) {
        $this->actingAs($editor)
            ->putJson(route('admin.home-sections.update', $hero), heroPayload($hero, ['imageId' => $imageId]))
            ->assertJsonValidationErrors('content.imageId');
    }

    expect($hero->fresh()?->toArray())->toBe($original)
        ->and(AuditLog::query()->count())->toBe(0);
});

test('portfolio projects are stored with their thumbnails and links', function () {
    $editor = User::factory()->editor()->create();
    $portfolio = HomeSection::factory()->ofType(HomeSectionType::Portfolio)->create();
    $image = MediaAsset::factory()->withVariants()->create();

    $this->actingAs($editor)->put(route('admin.home-sections.update', $portfolio), [
        'updated_at' => $portfolio->updated_at?->toIso8601String(),
        'content' => [
            'title' => 'Our work',
            'description' => null,
            'items' => [
                ['title' => 'Shop', 'category' => 'E-commerce', 'description' => null, 'imageId' => $image->id, 'url' => 'https://shop.example.com'],
                ['title' => 'Logo', 'category' => null, 'description' => 'Brand mark', 'imageId' => null, 'url' => null],
            ],
        ],
    ])->assertSessionHasNoErrors();

    $content = $portfolio->fresh()?->content;
    expect($content)->toBeInstanceOf(PortfolioContentData::class)
        ->and($content?->items)->toHaveCount(2)
        ->and($content?->items[0]->imageId)->toBe($image->id)
        ->and($content?->items[0]->url)->toBe('https://shop.example.com');
});

test('portfolio links and thumbnails are restricted', function (array $item, string $error) {
    $editor = User::factory()->editor()->create();
    $portfolio = HomeSection::factory()->ofType(HomeSectionType::Portfolio)->create();

    $this->actingAs($editor)->putJson(route('admin.home-sections.update', $portfolio), [
        'updated_at' => $portfolio->updated_at?->toIso8601String(),
        'content' => [
            'title' => null,
            'description' => null,
            'items' => [['title' => 'Shop', 'category' => null, 'description' => null, 'imageId' => null, 'url' => null, ...$item]],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors($error);

    expect($portfolio->fresh()?->content->items)->toBe([])
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    'script url' => [['url' => 'javascript:alert(1)'], 'content.items.0.url'],
    'not a url' => [['url' => 'shop'], 'content.items.0.url'],
    'quarantined image' => [fn () => ['imageId' => MediaAsset::factory()->create()->id], 'content.items.0.imageId'],
    'extra key' => [['html' => '<b>x</b>'], 'content.items.0'],
]);

test('team members are stored with their photo and skills', function () {
    $editor = User::factory()->editor()->create();
    $team = HomeSection::factory()->ofType(HomeSectionType::Team)->create();
    $photo = MediaAsset::factory()->withVariants()->create();

    $this->actingAs($editor)->put(route('admin.home-sections.update', $team), [
        'updated_at' => $team->updated_at?->toIso8601String(),
        'content' => [
            'title' => 'Team',
            'description' => null,
            'items' => [
                ['name' => 'Ada Example', 'role' => 'Developer', 'bio' => null, 'photoId' => $photo->id, 'skills' => ['Laravel', 'React']],
            ],
        ],
    ])->assertSessionHasNoErrors();

    $content = $team->fresh()?->content;
    expect($content)->toBeInstanceOf(TeamContentData::class)
        ->and($content?->items[0]->photoId)->toBe($photo->id)
        ->and($content?->items[0]->skills)->toBe(['Laravel', 'React']);
});

test('team members are validated', function (array $member, string $error) {
    $editor = User::factory()->editor()->create();
    $team = HomeSection::factory()->ofType(HomeSectionType::Team)->create();

    $this->actingAs($editor)->putJson(route('admin.home-sections.update', $team), [
        'updated_at' => $team->updated_at?->toIso8601String(),
        'content' => [
            'title' => null,
            'description' => null,
            'items' => [['name' => 'Ada', 'role' => 'Developer', 'bio' => null, 'photoId' => null, 'skills' => [], ...$member]],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors($error);

    expect($team->fresh()?->content->items)->toBe([])
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    'missing role' => [['role' => ''], 'content.items.0.role'],
    'too many skills' => [['skills' => array_fill(0, 11, 'PHP')], 'content.items.0.skills'],
    'markup in skill' => [['skills' => ['<b>PHP</b>']], 'content.items.0.skills.0'],
    'quarantined photo' => [fn () => ['photoId' => MediaAsset::factory()->create()->id], 'content.items.0.photoId'],
]);

/**
 * A valid pricing plan payload.
 *
 * @param  array<string, mixed>  $plan
 * @return array<string, mixed>
 */
function pricingPlan(array $plan = []): array
{
    return [
        'name' => 'Start',
        'priceMinor' => 290000,
        'currency' => 'PLN',
        'priceFrom' => true,
        'period' => null,
        'description' => null,
        'features' => ['Five pages'],
        'highlighted' => false,
        'action' => null,
        ...$plan,
    ];
}

test('pricing plans are stored with an integer price and a page action', function () {
    $editor = User::factory()->editor()->create();
    $pricing = HomeSection::factory()->ofType(HomeSectionType::Pricing)->create();
    $page = PageTranslation::factory()->published()->create();

    $this->actingAs($editor)->put(route('admin.home-sections.update', $pricing), [
        'updated_at' => $pricing->updated_at?->toIso8601String(),
        'content' => [
            'title' => 'Pricing',
            'description' => null,
            'note' => 'Prices are net.',
            'items' => [
                pricingPlan(['highlighted' => true, 'action' => ['label' => 'Details', 'target' => 'page', 'pageId' => $page->page_id]]),
                pricingPlan(['name' => 'Shop', 'priceMinor' => null, 'priceFrom' => false]),
            ],
        ],
    ])->assertSessionHasNoErrors();

    $content = $pricing->fresh()?->content;
    expect($content)->toBeInstanceOf(PricingContentData::class)
        ->and($content?->items[0]->priceMinor)->toBe(290000)
        ->and($content?->items[0]->highlighted)->toBeTrue()
        ->and($content?->items[0]->action?->pageId)->toBe($page->page_id)
        ->and($content?->items[1]->priceMinor)->toBeNull()
        ->and($content?->note)->toBe('Prices are net.');
});

test('pricing plans are validated', function (array $plan, string $error) {
    $editor = User::factory()->editor()->create();
    $pricing = HomeSection::factory()->ofType(HomeSectionType::Pricing)->create();

    $this->actingAs($editor)->putJson(route('admin.home-sections.update', $pricing), [
        'updated_at' => $pricing->updated_at?->toIso8601String(),
        'content' => ['title' => null, 'description' => null, 'note' => null, 'items' => [pricingPlan($plan)]],
    ])->assertUnprocessable()->assertJsonValidationErrors($error);

    expect($pricing->fresh()?->content->items)->toBe([])
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    'decimal price' => [['priceMinor' => 12.5], 'content.items.0.priceMinor'],
    'negative price' => [['priceMinor' => -1], 'content.items.0.priceMinor'],
    'unsupported currency' => [['currency' => 'BTC'], 'content.items.0.currency'],
    'too many features' => [['features' => array_fill(0, 11, 'Page')], 'content.items.0.features'],
    'markup in feature' => [['features' => ['<script>x</script>']], 'content.items.0.features.0'],
    'page action without page' => [['action' => ['label' => 'Go', 'target' => 'page', 'pageId' => null]], 'content.items.0.action.pageId'],
    'action without label' => [['action' => ['label' => '', 'target' => 'contact', 'pageId' => null]], 'content.items.0.action.label'],
    'unknown action target' => [['action' => ['label' => 'Go', 'target' => 'https://evil.test', 'pageId' => null]], 'content.items.0.action.target'],
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
    'too many projects' => ['portfolio', ['items' => array_fill(0, 13, ['title' => 'T', 'category' => null, 'description' => null, 'imageId' => null, 'url' => null])], 'content.items'],
    'too many team members' => ['team', ['items' => array_fill(0, 9, ['name' => 'N', 'role' => 'R', 'bio' => null, 'photoId' => null, 'skills' => []])], 'content.items'],
    'too many plans' => ['pricing', ['items' => array_fill(0, 5, pricingPlan())], 'content.items'],
    'too many testimonials' => ['testimonials', ['items' => array_fill(0, 7, ['author' => 'A', 'role' => null, 'quote' => 'Q'])], 'content.items'],
    'too many testimonial stats' => ['testimonials', ['items' => [], 'stats' => array_fill(0, 5, ['value' => '1', 'label' => 'L'])], 'content.stats'],
    'markup in testimonial stat' => ['testimonials', ['items' => [], 'stats' => [['value' => '<b>5</b>', 'label' => 'L']]], 'content.stats.0.value'],
    'too many hero stats' => ['hero', ['title' => 'T', 'stats' => array_fill(0, 5, ['value' => '1', 'label' => 'L'])], 'content.stats'],
    'long hero stat value' => ['hero', ['title' => 'T', 'stats' => [['value' => str_repeat('9', 13), 'label' => 'L']]], 'content.stats.0.value'],
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
        ->and($log->changes)->toEqual(['enabled' => ['old' => false, 'new' => true]]);

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
