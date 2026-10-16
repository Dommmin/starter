<?php

use App\Enums\HomeSectionType;
use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\Faq;
use App\Models\HomeSection;
use App\Models\MediaAsset;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Tag;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

test('a viewer opens the dashboard and every content list', function (string $routeName) {
    $viewer = User::factory()->viewer()->create();

    $this->actingAs($viewer)->get(route($routeName))->assertOk();
})->with([
    'admin.index',
    'admin.pages.index',
    'admin.articles.index',
    'admin.tags.index',
    'admin.faqs.index',
    'admin.media.index',
    'admin.navigation.index',
    'admin.home-sections.index',
]);

test('a viewer opens editors read-only and without create shortcuts', function () {
    $viewer = User::factory()->viewer()->create();
    $article = ArticleTranslation::factory()->create()->article;
    $page = Page::factory()->create();
    $faq = Faq::factory()->create();
    $tag = Tag::factory()->create();
    $menuItem = MenuItem::factory()->create();
    $section = HomeSection::factory()->ofType(HomeSectionType::Hero)->create();

    $this->actingAs($viewer)->get(route('admin.articles.edit', $article))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('can.update', false)
            ->where('can.create', false)
            ->where('can.publish', false)
            ->where('can.delete', false)
        );
    $this->actingAs($viewer)->get(route('admin.pages.edit', $page))
        ->assertInertia(fn (Assert $inertia) => $inertia->where('can.update', false));
    $this->actingAs($viewer)->get(route('admin.faqs.edit', $faq))
        ->assertInertia(fn (Assert $inertia) => $inertia->where('can.update', false));
    $this->actingAs($viewer)->get(route('admin.tags.edit', $tag))
        ->assertInertia(fn (Assert $inertia) => $inertia->where('canUpdate', false)->where('canDelete', false));
    $this->actingAs($viewer)->get(route('admin.navigation.edit', $menuItem))
        ->assertInertia(fn (Assert $inertia) => $inertia->where('can.update', false));
    $this->actingAs($viewer)->get(route('admin.home-sections.edit', $section))
        ->assertInertia(fn (Assert $inertia) => $inertia->where('can.update', false));

    $this->actingAs($viewer)->get(route('admin.index'))
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('overview.canCreateArticles', false)
            ->where('overview.canCreatePages', false)
            ->where('overview.contact', null)
            ->where('auth.can.viewContactMessages', false)
        );
});

test('a viewer cannot create, change, publish, reorder, upload or delete anything', function () {
    $viewer = User::factory()->viewer()->create();
    $article = ArticleTranslation::factory()->create()->article;
    $page = Page::factory()->create();
    $faq = Faq::factory()->create();
    $tag = Tag::factory()->create();
    $menuItem = MenuItem::factory()->create();
    $section = HomeSection::factory()->ofType(HomeSectionType::Hero)->create(['enabled' => true]);
    $asset = MediaAsset::factory()->clean()->create();

    $requests = [
        ['get', route('admin.articles.create')],
        ['post', route('admin.articles.store')],
        ['put', route('admin.articles.update', $article)],
        ['delete', route('admin.articles.destroy', $article)],
        ['get', route('admin.pages.create')],
        ['post', route('admin.pages.store')],
        ['put', route('admin.pages.update', $page)],
        ['delete', route('admin.pages.destroy', $page)],
        ['post', route('admin.tags.store')],
        ['put', route('admin.tags.update', $tag)],
        ['delete', route('admin.tags.destroy', $tag)],
        ['post', route('admin.faqs.store')],
        ['put', route('admin.faqs.update', $faq)],
        ['delete', route('admin.faqs.destroy', $faq)],
        ['post', route('admin.media.store')],
        ['put', route('admin.media.update', $asset)],
        ['delete', route('admin.media.destroy', $asset)],
        ['post', route('admin.navigation.store')],
        ['put', route('admin.navigation.reorder')],
        ['put', route('admin.navigation.update', $menuItem)],
        ['delete', route('admin.navigation.destroy', $menuItem)],
        ['put', route('admin.home-sections.reorder')],
        ['put', route('admin.home-sections.update', $section)],
        ['patch', route('admin.home-sections.visibility', $section), ['enabled' => false]],
    ];

    foreach ($requests as $request) {
        [$method, $url] = $request;
        $this->actingAs($viewer)->{$method}($url, $request[2] ?? [])->assertForbidden();
    }

    expect(Article::query()->count())->toBe(1)
        ->and(Page::query()->count())->toBe(1)
        ->and(Faq::query()->count())->toBe(1)
        ->and(Tag::query()->count())->toBe(1)
        ->and(MenuItem::query()->count())->toBe(1)
        ->and(MediaAsset::query()->count())->toBe(1)
        ->and($section->refresh()->enabled)->toBeTrue()
        ->and(AuditLog::query()->count())->toBe(0);
});

test('a viewer reads no contact messages, users, audit log or settings', function (string $routeName) {
    $viewer = User::factory()->viewer()->create();

    $this->actingAs($viewer)->get(route($routeName))->assertForbidden();
})->with([
    'admin.contact.index',
    'admin.contact-forms.index',
    'admin.users.index',
    'admin.audit.index',
    'admin.site-settings.edit',
    'admin.system.index',
]);

test('a viewer cannot open a single contact message', function () {
    $viewer = User::factory()->viewer()->create();
    $message = ContactMessage::factory()->create();

    $this->actingAs($viewer)->get(route('admin.contact.show', $message))->assertForbidden();
});

test('an editor keeps the right to change content', function () {
    $editor = User::factory()->editor()->create();
    $faq = Faq::factory()->create();

    $this->actingAs($editor)->get(route('admin.faqs.edit', $faq))
        ->assertInertia(fn (Assert $inertia) => $inertia->where('can.update', true));
    $this->actingAs($editor)->get(route('admin.index'))
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('overview.canCreateArticles', true)
            ->where('auth.can.viewContactMessages', true)
        );
});
