<?php

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Audit\ShareRecordHistory;
use App\Enums\AuditAction;
use App\Enums\HomeSectionType;
use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\ContactForm;
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

test('an administrator sees who changed which fields of the record, newest first', function () {
    $admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
    $article = ArticleTranslation::factory()->create()->article;
    $other = Article::factory()->create();
    $audit = app(RecordAuditEvent::class);
    $audit->handle(AuditAction::ArticleCreated, $article, $admin, ['en.title' => RecordAuditEvent::change(null, 'Hello')]);
    $audit->handle(AuditAction::ArticleUpdated, $article, null, ['en.slug' => RecordAuditEvent::change('a', 'b')]);
    $audit->handle(AuditAction::ArticleUpdated, $other, $admin, ['en.title' => RecordAuditEvent::change('x', 'y')]);

    $this->actingAs($admin)->get(route('admin.articles.edit', $article))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('recordHistory.entries', 2)
            ->where('recordHistory.limit', ShareRecordHistory::LIMIT)
            ->where('recordHistory.entries.0.action', AuditAction::ArticleUpdated->value)
            ->where('recordHistory.entries.0.actorName', null)
            ->where('recordHistory.entries.0.changedFields', ['en.slug'])
            ->where('recordHistory.entries.1.actorName', 'Ada Admin')
        );
});

test('the history shows at most the newest entries', function () {
    $admin = User::factory()->admin()->create();
    $tag = Tag::factory()->create();
    $audit = app(RecordAuditEvent::class);

    foreach (range(1, ShareRecordHistory::LIMIT + 2) as $version) {
        $audit->handle(AuditAction::TagUpdated, $tag, $admin, ['slug' => RecordAuditEvent::change("v{$version}", "v{$version}")]);
    }

    $this->actingAs($admin)->get(route('admin.tags.edit', $tag))
        ->assertInertia(fn (Assert $page) => $page->has('recordHistory.entries', ShareRecordHistory::LIMIT));
});

test('users who may not read the audit log get no history', function (string $role) {
    $user = User::factory()->{$role}()->create();
    $article = ArticleTranslation::factory()->create()->article;
    app(RecordAuditEvent::class)->handle(AuditAction::ArticleUpdated, $article, $user, ['en.title' => RecordAuditEvent::change('a', 'b')]);

    $this->actingAs($user)->get(route('admin.articles.edit', $article))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('recordHistory'));
})->with(['editor', 'viewer']);

test('every audited editor shares the history of its record', function (string $route, Closure $record) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route($route, $record()))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('recordHistory.entries'));
})->with([
    'page' => ['admin.pages.edit', fn () => Page::factory()->create()],
    'tag' => ['admin.tags.edit', fn () => Tag::factory()->create()],
    'contact form' => ['admin.contact-forms.edit', fn () => ContactForm::factory()->create()],
    'menu item' => ['admin.navigation.edit', fn () => MenuItem::factory()->create()],
    'media' => ['admin.media.edit', fn () => MediaAsset::factory()->clean()->create()],
    'user' => ['admin.users.edit', fn () => User::factory()->create()],
    'home section' => ['admin.home-sections.edit', fn () => HomeSection::factory()->ofType(HomeSectionType::Hero)->create()],
]);
