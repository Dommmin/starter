<?php

use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\Faq;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\User;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

test('an administrator downloads the content without accounts, messages or trashed pages', function () {
    $admin = User::factory()->admin()->create(['email' => 'secret-admin@example.com']);
    ContactMessage::factory()->create(['email' => 'secret-sender@example.com']);
    PageTranslation::factory()->for(Page::factory()->create(['created_by' => $admin->id]))->locale('en')->create(['title' => 'Kept page']);
    $trashed = Page::factory()->create();
    PageTranslation::factory()->for($trashed)->locale('en')->create(['title' => 'Trashed page']);
    $trashed->delete();
    Faq::factory()->create(['question' => 'A question?']);

    $response = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('admin.system.export'))
        ->assertOk()
        ->assertDownload('content-'.now()->format('Y-m-d').'.json');

    $json = $response->streamedContent();
    expect($json)->toContain('Kept page', 'A question?')
        ->not->toContain('secret-admin@example.com')
        ->not->toContain('secret-sender@example.com')
        ->not->toContain('Trashed page')
        ->not->toContain('created_by')
        ->and(json_decode($json, true)['pages'])->toHaveCount(1)
        ->and(AuditLog::query()->where('action', 'resource.exported')->count())->toBe(1);
});

test('the export needs a recently confirmed password', function () {
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.system.export'))
        ->assertRedirect(route('password.confirm'));

    expect(AuditLog::query()->count())->toBe(0);
});

test('editors and viewers cannot export', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create())->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('admin.system.export'))
        ->assertForbidden();

    expect(AuditLog::query()->count())->toBe(0);
})->with(['editor', 'viewer']);
