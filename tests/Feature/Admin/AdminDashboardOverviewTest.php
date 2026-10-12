<?php

use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

test('the dashboard summarises content, contact messages, media and recent activity for an admin', function () {
    $default = config('localization.public_default');

    Article::factory()->count(2)->published()->create();
    Article::factory()->draft()->create();
    $scheduled = Article::factory()->create();
    ArticleTranslation::factory()->for($scheduled)->scheduled()->create();
    ArticleTranslation::factory()->for($scheduled)->locale('pl')->published()->create();

    Page::factory()->published()->create();
    Page::factory()->count(2)->draft()->create();
    PageTranslation::factory()->locale('pl')->draft()->create();

    ContactMessage::factory()->create();
    ContactMessage::factory()->failed()->create();
    ContactMessage::factory()->sent()->create(['created_at' => now()->subDays(10)]);

    MediaAsset::factory()->count(2)->create();
    MediaAsset::factory()->clean()->create();

    AuditLog::factory()->count(6)->create();

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/index')
            ->where('overview.contentLocale', $default)
            ->where('overview.articles', ['published' => 2, 'drafts' => 1, 'scheduled' => 1])
            ->where('overview.pages', ['published' => 1, 'drafts' => 2, 'scheduled' => 0])
            ->where('overview.contact.recent', 2)
            ->where('overview.contact.failed', 1)
            ->where('overview.contact.recentDays', 7)
            ->where('overview.contact.previous', 1)
            ->has('overview.contact.daily', 30)
            ->where('overview.contact.daily.29', ['date' => now()->toDateString(), 'delivered' => 0, 'undelivered' => 2])
            ->where('overview.quarantinedMedia', 2)
            ->where('overview.attention.failedMessages', 1)
            ->where('overview.attention.quarantinedMedia', 2)
            ->where('overview.attention.adminsWithoutTwoFactor', 1)
            ->has('overview.publishing', fn (Assert $publishing) => $publishing
                ->where('days', 30)
                ->has('recent')
                ->has('previous')
                ->whereNot('nextScheduledAt', null))
            ->has('overview.recentActivity', 6)
            ->has('overview.recentActivity.0', fn (Assert $entry) => $entry
                ->hasAll(['id', 'createdAt', 'actorName', 'action', 'subjectType', 'subjectId', 'changedFields']))
            ->missing('system'),
        );
});

test('the dashboard overview uses a constant number of queries', function () {
    $admin = User::factory()->admin()->create();
    AuditLog::factory()->count(2)->create();

    $this->actingAs($admin);
    $this->get(route('admin.index'))->assertOk();

    DB::enableQueryLog();
    $this->get(route('admin.index'))->assertOk();
    $fewRecords = count(DB::getQueryLog());

    AuditLog::factory()->count(4)->create();
    Article::factory()->count(3)->published()->create();

    DB::flushQueryLog();
    $this->get(route('admin.index'))->assertOk();

    expect(count(DB::getQueryLog()))->toBe($fewRecords);
});

test('an editor sees the content overview without the audit log', function () {
    AuditLog::factory()->create();
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)
        ->get(route('admin.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/index')
            ->has('overview.articles')
            ->has('overview.pages')
            ->has('overview.contact')
            ->where('overview.recentActivity', null)
            ->where('overview.attention.adminsWithoutTwoFactor', null),
        );
});

test('an empty site reports zero counts', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.articles', ['published' => 0, 'drafts' => 0, 'scheduled' => 0])
            ->where('overview.pages', ['published' => 0, 'drafts' => 0, 'scheduled' => 0])
            ->where('overview.quarantinedMedia', 0)
            ->has('overview.recentActivity', 0),
        );
});

test('drafts nobody touched for two weeks need attention', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create();
    $stale = Article::factory()->create();
    ArticleTranslation::factory()->for($stale)->draft()->create(['updated_at' => now()->subDays(20)]);
    Article::factory()->draft()->create();

    $this->actingAs($admin)
        ->get(route('admin.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.attention.staleDrafts', 1)
            ->where('overview.attention.adminsWithoutTwoFactor', 0),
        );
});

test('a user without panel access cannot open the dashboard', function () {
    ContactMessage::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.index'))
        ->assertForbidden();
});
