<?php

use App\Enums\AuditAction;
use App\Enums\HomeSectionAnchor;
use App\Enums\HomeSectionType;
use App\Enums\MenuItemType;
use App\Enums\MenuLocation;
use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\AuditLog;
use App\Models\HomeSection;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

/**
 * A valid store payload (external link in the English header).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function navigationItemPayload(array $overrides = []): array
{
    return [
        'location' => 'header',
        'locale' => 'en',
        'parent_id' => null,
        'type' => 'external',
        'page_id' => null,
        'article_id' => null,
        'anchor' => null,
        'url' => 'https://example.com/docs',
        'label' => 'Docs',
        'open_in_new_tab' => false,
        ...$overrides,
    ];
}

/**
 * The update payload of an existing item (location and locale are fixed).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function navigationUpdatePayload(MenuItem $item, array $overrides = []): array
{
    return [
        'updated_at' => $item->updated_at?->toIso8601String(),
        'parent_id' => $item->parent_id,
        'type' => $item->type->value,
        'page_id' => $item->page_id,
        'article_id' => $item->article_id,
        'anchor' => $item->anchor,
        'url' => $item->url,
        'label' => $item->label,
        'open_in_new_tab' => $item->open_in_new_tab,
        ...$overrides,
    ];
}

test('guests are redirected to the login page', function () {
    $this->get(route('admin.navigation.index'))->assertRedirect(route('login'));
    $this->post(route('admin.navigation.store'), navigationItemPayload())->assertRedirect(route('login'));

    expect(MenuItem::query()->count())->toBe(0);
});

test('users without panel access cannot read or change menus', function () {
    $user = User::factory()->create();
    $item = MenuItem::factory()->create();
    $before = $item->fresh()?->toArray();

    $this->actingAs($user)->get(route('admin.navigation.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.navigation.create'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.navigation.edit', $item))->assertForbidden();
    $this->actingAs($user)->post(route('admin.navigation.store'), navigationItemPayload())->assertForbidden();
    $this->actingAs($user)->put(route('admin.navigation.update', $item), navigationUpdatePayload($item, ['label' => 'Hacked']))->assertForbidden();
    $this->actingAs($user)->put(route('admin.navigation.reorder'), [
        'location' => 'header', 'locale' => 'en', 'parentId' => null, 'ids' => [$item->id],
    ])->assertForbidden();
    $this->actingAs($user)->delete(route('admin.navigation.destroy', $item))->assertForbidden();

    expect(MenuItem::query()->count())->toBe(1)
        ->and($item->fresh()?->toArray())->toBe($before)
        ->and(AuditLog::query()->count())->toBe(0);
});

test('the index exposes the tree of the selected menu with target states', function () {
    $editor = User::factory()->editor()->create();
    $draftPage = Page::factory()->create();
    PageTranslation::factory()->for($draftPage)->draft()->create(['title' => 'Draft page']);
    $deletedTarget = MenuItem::factory()->page(Page::factory()->create())->create(['position' => 2]);
    $deletedTarget->forceFill(['page_id' => null])->save();

    $group = MenuItem::factory()->group()->create(['label' => 'Company', 'position' => 1]);
    MenuItem::factory()->childOf($group)->page($draftPage)->create(['position' => 1]);
    MenuItem::factory()->in(MenuLocation::Footer, 'en')->create(['label' => 'Footer link']);
    MenuItem::factory()->in(MenuLocation::Header, 'pl')->create(['label' => 'Polski link']);

    $this->actingAs($editor)->get(route('admin.navigation.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/navigation/index', false)
            ->where('location', 'header')
            ->where('locale', 'en')
            ->has('items', 2)
            ->where('items.0.label', 'Company')
            ->where('items.0.type', 'group')
            ->has('items.0.children', 1)
            ->where('items.0.children.0.label', 'Draft page')
            ->where('items.0.children.0.draftTarget', true)
            ->where('items.1.targetMissing', true)
            ->where('items.1.label', null)
            ->where('can', ['create' => true, 'reorder' => true, 'delete' => true])
        );

    $this->actingAs($editor)->get(route('admin.navigation.index', ['location' => 'footer']))
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('location', 'footer')
            ->has('items', 1)
            ->where('items.0.label', 'Footer link')
        );
});

test('the create form offers targets translated in the menu locale and marks drafts', function () {
    $editor = User::factory()->editor()->create();
    $published = Page::factory()->create();
    PageTranslation::factory()->for($published)->published()->locale('pl')->create(['title' => 'O nas']);
    $englishOnly = Page::factory()->create();
    PageTranslation::factory()->for($englishOnly)->published()->create(['title' => 'English only']);
    $scheduled = Article::factory()->create();
    ArticleTranslation::factory()->for($scheduled)->scheduled()->locale('pl')->create(['title' => 'Wkrótce']);
    $parent = MenuItem::factory()->in(MenuLocation::Footer, 'pl')->group()->create(['label' => 'Firma']);

    $this->actingAs($editor)->get(route('admin.navigation.create', ['location' => 'footer', 'locale' => 'pl']))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/navigation/create', false)
            ->where('item.id', null)
            ->where('item.location', 'footer')
            ->where('item.locale', 'pl')
            ->where('pages', [['id' => $published->id, 'title' => 'O nas', 'draft' => false]])
            ->where('articles', [['id' => $scheduled->id, 'title' => 'Wkrótce', 'draft' => true]])
            ->where('parents', [['id' => $parent->id, 'label' => 'Firma', 'type' => 'group']])
        );
});

test('an editor creates an item at the end of its level with an audit entry', function () {
    $editor = User::factory()->editor()->create();
    MenuItem::factory()->create(['position' => 4]);
    $page = Page::factory()->published()->create();

    $response = $this->actingAs($editor)->post(route('admin.navigation.store'), navigationItemPayload([
        'type' => 'page',
        'page_id' => $page->id,
        'url' => null,
        'label' => '',
    ]));

    $response->assertRedirect(route('admin.navigation.index', ['location' => 'header', 'locale' => 'en']))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('admin.navigation.created')]);

    $item = MenuItem::query()->where('type', 'page')->sole();
    expect($item->position)->toBe(5)
        ->and($item->label)->toBeNull()
        ->and($item->page_id)->toBe($page->id)
        ->and($item->open_in_new_tab)->toBeFalse()
        ->and($item->created_by)->toBe($editor->id);

    $log = AuditLog::query()->sole();
    expect($log->action)->toBe(AuditAction::NavigationItemCreated)
        ->and($log->actor_id)->toBe($editor->id)
        ->and($log->subject_id)->toBe($item->id)
        ->and($log->changes['page_id'] ?? null)->toBe(['old' => null, 'new' => $page->id]);
});

test('an admin creates a child and an external link opening in a new tab', function () {
    $admin = User::factory()->admin()->create();
    $parent = MenuItem::factory()->group()->create(['label' => 'Resources']);

    $this->actingAs($admin)->post(route('admin.navigation.store'), navigationItemPayload([
        'parent_id' => $parent->id,
        'open_in_new_tab' => true,
    ]))->assertSessionHasNoErrors();

    $child = MenuItem::query()->where('parent_id', $parent->id)->sole();
    expect($child->open_in_new_tab)->toBeTrue()
        ->and($child->position)->toBe(1);
});

test('invalid structures are rejected without saving', function (Closure $payload, string $field) {
    $editor = User::factory()->editor()->create();
    $root = MenuItem::factory()->create(['label' => 'Root']);
    $child = MenuItem::factory()->childOf($root)->create();
    $polishRoot = MenuItem::factory()->in(MenuLocation::Header, 'pl')->create();
    $footerRoot = MenuItem::factory()->in(MenuLocation::Footer, 'en')->create();
    $count = MenuItem::query()->count();

    $this->actingAs($editor)
        ->from(route('admin.navigation.create'))
        ->post(route('admin.navigation.store'), navigationItemPayload($payload(compact('root', 'child', 'polishRoot', 'footerRoot'))))
        ->assertSessionHasErrors($field);

    expect(MenuItem::query()->count())->toBe($count)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    'third level' => [fn (array $items) => ['parent_id' => $items['child']->id], 'parent_id'],
    'parent in another locale' => [fn (array $items) => ['parent_id' => $items['polishRoot']->id], 'parent_id'],
    'parent in another location' => [fn (array $items) => ['parent_id' => $items['footerRoot']->id], 'parent_id'],
    'unknown parent' => [fn (array $items) => ['parent_id' => 999999], 'parent_id'],
    'group as a child' => [fn (array $items) => ['parent_id' => $items['root']->id, 'type' => 'group', 'url' => null, 'label' => 'Group'], 'parent_id'],
    'new tab for a non-external link' => [fn (array $items) => ['type' => 'article_index', 'url' => null, 'open_in_new_tab' => true], 'open_in_new_tab'],
    'javascript url' => [fn (array $items) => ['url' => 'javascript:alert(1)'], 'url'],
    'data url' => [fn (array $items) => ['url' => 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='], 'url'],
    'protocol-relative url' => [fn (array $items) => ['url' => '//evil.com'], 'url'],
    'other scheme' => [fn (array $items) => ['url' => 'ftp://example.com/file'], 'url'],
    'invalid anchor' => [fn (array $items) => ['type' => 'anchor', 'url' => null, 'anchor' => 'Bad Anchor!'], 'anchor'],
    'home anchor outside the home sections' => [fn (array $items) => ['type' => 'anchor', 'url' => null, 'anchor' => 'team'], 'anchor'],
    'anchor without label' => [fn (array $items) => ['type' => 'anchor', 'url' => null, 'anchor' => 'features', 'label' => ''], 'label'],
    'page without page' => [fn (array $items) => ['type' => 'page', 'url' => null], 'page_id'],
    'url on a group' => [fn (array $items) => ['type' => 'group'], 'url'],
    'unsupported locale' => [fn (array $items) => ['locale' => 'fr'], 'locale'],
]);

test('an editor updates an item with an audit of the changed fields', function () {
    $editor = User::factory()->editor()->create();
    $item = MenuItem::factory()->create(['label' => 'Old', 'url' => 'https://example.com/old']);

    $this->actingAs($editor)
        ->put(route('admin.navigation.update', $item), navigationUpdatePayload($item, [
            'label' => 'New',
            'url' => 'https://example.com/new',
        ]))
        ->assertRedirect(route('admin.navigation.edit', $item))
        ->assertSessionHasNoErrors();

    $item->refresh();
    expect($item->label)->toBe('New')
        ->and($item->updated_by)->toBe($editor->id);

    $log = AuditLog::query()->sole();
    expect($log->action)->toBe(AuditAction::NavigationItemUpdated)
        ->and(array_keys($log->changes ?? []))->toBe(['url', 'label'])
        ->and($log->changes['label'] ?? null)->toBe(['old' => 'Old', 'new' => 'New']);
});

test('an item with children cannot become a child', function () {
    $editor = User::factory()->editor()->create();
    $parent = MenuItem::factory()->create(['position' => 1]);
    MenuItem::factory()->childOf($parent)->create();
    $other = MenuItem::factory()->create(['position' => 2]);

    $this->actingAs($editor)
        ->put(route('admin.navigation.update', $parent), navigationUpdatePayload($parent, ['parent_id' => $other->id]))
        ->assertSessionHasErrors(['parent_id' => __('validation.navigation.max_depth')]);

    expect($parent->fresh()?->parent_id)->toBeNull()
        ->and(AuditLog::query()->count())->toBe(0);
});

test('moving an item under another parent appends it to that level', function () {
    $editor = User::factory()->editor()->create();
    $parent = MenuItem::factory()->group()->create(['label' => 'Group', 'position' => 1]);
    MenuItem::factory()->childOf($parent)->create(['position' => 3]);
    $item = MenuItem::factory()->create(['position' => 2]);

    $this->actingAs($editor)
        ->put(route('admin.navigation.update', $item), navigationUpdatePayload($item, ['parent_id' => $parent->id]))
        ->assertSessionHasNoErrors();

    expect($item->fresh()?->parent_id)->toBe($parent->id)
        ->and($item->fresh()?->position)->toBe(4);
});

test('a stale update is a conflict and saves nothing', function () {
    $editor = User::factory()->editor()->create();
    $item = MenuItem::factory()->create(['label' => 'Current']);
    $stale = navigationUpdatePayload($item, [
        'label' => 'Stale',
        'updated_at' => $item->updated_at?->subMinute()->toIso8601String(),
    ]);

    $this->actingAs($editor)
        ->put(route('admin.navigation.update', $item), $stale)
        ->assertSessionHasErrors('conflict');

    expect($item->fresh()?->label)->toBe('Current')
        ->and(AuditLog::query()->count())->toBe(0);
});

test('an editor deletes an item with its children and an audit entry', function () {
    $editor = User::factory()->editor()->create();
    $parent = MenuItem::factory()->create();
    $child = MenuItem::factory()->childOf($parent)->create();

    $this->actingAs($editor)
        ->delete(route('admin.navigation.destroy', $parent))
        ->assertRedirect(route('admin.navigation.index', ['location' => 'header', 'locale' => 'en']));

    expect(MenuItem::query()->count())->toBe(0);

    $log = AuditLog::query()->sole();
    expect($log->action)->toBe(AuditAction::NavigationItemDeleted)
        ->and($log->subject_id)->toBe($parent->id)
        ->and($log->changes['children'] ?? null)->toBe(['old' => [$child->id], 'new' => null]);
});

test('admins and editors reorder one level with an audit entry', function (string $role) {
    $user = User::factory()->{$role}()->create();
    $first = MenuItem::factory()->create(['position' => 1]);
    $second = MenuItem::factory()->create(['position' => 2]);
    $third = MenuItem::factory()->create(['position' => 3]);

    $this->actingAs($user)->put(route('admin.navigation.reorder'), [
        'location' => 'header',
        'locale' => 'en',
        'parentId' => null,
        'ids' => [$third->id, $first->id, $second->id],
    ])->assertRedirect(route('admin.navigation.index', ['location' => 'header', 'locale' => 'en']))
        ->assertSessionHasNoErrors();

    expect($third->fresh()?->position)->toBe(1)
        ->and($first->fresh()?->position)->toBe(2)
        ->and($second->fresh()?->position)->toBe(3);

    $log = AuditLog::query()->sole();
    expect($log->action)->toBe(AuditAction::NavigationReordered)
        ->and($log->changes['order'] ?? null)->toBe([
            'old' => [$first->id, $second->id, $third->id],
            'new' => [$third->id, $first->id, $second->id],
        ]);
})->with(['admin', 'editor']);

test('reordering children targets only that level', function () {
    $editor = User::factory()->editor()->create();
    $parent = MenuItem::factory()->group()->create(['label' => 'Group']);
    $a = MenuItem::factory()->childOf($parent)->create(['position' => 1]);
    $b = MenuItem::factory()->childOf($parent)->create(['position' => 2]);

    $this->actingAs($editor)->put(route('admin.navigation.reorder'), [
        'location' => 'header', 'locale' => 'en', 'parentId' => $parent->id, 'ids' => [$b->id, $a->id],
    ])->assertSessionHasNoErrors();

    expect($b->fresh()?->position)->toBe(1)
        ->and(AuditLog::query()->sole()->subject_id)->toBe($parent->id);
});

test('reordering with a stale or foreign set of ids is a conflict without changes', function (Closure $ids) {
    $editor = User::factory()->editor()->create();
    $first = MenuItem::factory()->create(['position' => 1]);
    $second = MenuItem::factory()->create(['position' => 2]);
    $polish = MenuItem::factory()->in(MenuLocation::Header, 'pl')->create(['position' => 1]);

    $this->actingAs($editor)
        ->from(route('admin.navigation.index'))
        ->put(route('admin.navigation.reorder'), [
            'location' => 'header',
            'locale' => 'en',
            'parentId' => null,
            'ids' => $ids($first, $second, $polish),
        ])->assertSessionHasErrors('conflict');

    expect($first->fresh()?->position)->toBe(1)
        ->and($second->fresh()?->position)->toBe(2)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    'missing sibling' => [fn (MenuItem $first, MenuItem $second, MenuItem $polish) => [$second->id]],
    'foreign item' => [fn (MenuItem $first, MenuItem $second, MenuItem $polish) => [$second->id, $first->id, $polish->id]],
    'swapped for foreign' => [fn (MenuItem $first, MenuItem $second, MenuItem $polish) => [$second->id, $polish->id]],
]);

test('the edit form marks a deleted target', function () {
    $editor = User::factory()->editor()->create();
    $article = Article::factory()->create();
    ArticleTranslation::factory()->for($article)->published()->create();
    $item = MenuItem::factory()->article($article)->create();
    $article->delete();

    $this->actingAs($editor)->get(route('admin.navigation.edit', $item))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('admin/navigation/edit', false)
            ->where('item.id', $item->id)
            ->where('item.type', MenuItemType::Article->value)
            ->where('item.articleId', null)
            ->where('item.targetMissing', true)
        );
});

test('an anchor without a page targets a home section, an anchor on a page any valid id', function () {
    $editor = User::factory()->editor()->create();
    $page = Page::factory()->published()->create();

    $this->actingAs($editor)->post(route('admin.navigation.store'), navigationItemPayload([
        'type' => 'anchor', 'url' => null, 'anchor' => HomeSectionAnchor::LatestArticles->value, 'label' => 'News',
    ]))->assertSessionHasNoErrors();
    $this->actingAs($editor)->post(route('admin.navigation.store'), navigationItemPayload([
        'type' => 'anchor', 'url' => null, 'page_id' => $page->id, 'anchor' => 'team', 'label' => 'Team',
    ]))->assertSessionHasNoErrors();

    expect(MenuItem::query()->where('type', 'anchor')->orderBy('position')->get(['page_id', 'anchor'])->toArray())->toBe([
        ['page_id' => null, 'anchor' => 'latest-articles'],
        ['page_id' => $page->id, 'anchor' => 'team'],
    ]);
});

test('the form lists every home section anchor and marks the ones hidden in the menu locale', function () {
    $editor = User::factory()->editor()->create();
    HomeSection::factory()->create(['locale' => 'pl', 'type' => HomeSectionType::Hero, 'enabled' => true]);
    HomeSection::factory()->create(['locale' => 'en', 'type' => HomeSectionType::Faq, 'enabled' => true]);

    $this->actingAs($editor)->get(route('admin.navigation.create', ['location' => 'header', 'locale' => 'pl']))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->has('homeAnchors', count(HomeSectionType::cases()))
            ->where('homeAnchors.0', ['anchor' => 'hero', 'sectionType' => 'hero', 'enabled' => true])
            ->where('homeAnchors.2', ['anchor' => 'faq', 'sectionType' => 'faq', 'enabled' => false])
        );
});
