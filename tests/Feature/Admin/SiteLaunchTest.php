<?php

use App\Actions\Settings\BuildSiteLaunchChecklist;
use App\Enums\AuditAction;
use App\Enums\PublicationStatus;
use App\Mail\TestMail;
use App\Models\AuditLog;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Content\LegalPages;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

test('an administrator turns the coming soon page and noindex on with an audit entry', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.system.publication.update'), ['coming_soon' => true, 'noindex' => true])
        ->assertRedirect(route('admin.system.index'))
        ->assertSessionHasNoErrors();

    $settings = SiteSetting::query()->sole();
    expect($settings->coming_soon)->toBeTrue()
        ->and($settings->noindex)->toBeTrue();

    $audit = AuditLog::query()->sole();
    expect($audit->action)->toBe(AuditAction::SiteSettingsUpdated)
        ->and($audit->changes['coming_soon'])->toEqual(['old' => false, 'new' => true]);
});

test('editors cannot change the publication switches or send a test e-mail', function () {
    Mail::fake();
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)
        ->put(route('admin.system.publication.update'), ['coming_soon' => true, 'noindex' => true])
        ->assertForbidden();
    $this->actingAs($editor)->post(route('admin.system.test-mail.store'))->assertForbidden();

    expect(SiteSetting::query()->exists())->toBeFalse();
    Mail::assertNothingSent();
});

test('the publication switches must be booleans', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.system.publication.update'), ['coming_soon' => 'maybe'])
        ->assertSessionHasErrors(['coming_soon', 'noindex']);

    expect(SiteSetting::query()->exists())->toBeFalse();
});

test('the test e-mail goes to the signed-in administrator and ticks the checklist', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create(['email' => 'owner@acme.test']);

    $this->actingAs($admin)
        ->post(route('admin.system.test-mail.store'))
        ->assertRedirect(route('admin.system.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('admin.launch.testMailSent', ['email' => 'owner@acme.test'])]);

    Mail::assertSent(TestMail::class, fn (TestMail $mail) => $mail->hasTo('owner@acme.test'));
    expect(Cache::has(BuildSiteLaunchChecklist::TEST_MAIL_SENT_KEY))->toBeTrue();
});

test('a failing mailer reports an error toast and leaves the checklist item open', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.system.test-mail.store'))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => __('admin.launch.testMailFailed')]);

    expect(Cache::has(BuildSiteLaunchChecklist::TEST_MAIL_SENT_KEY))->toBeFalse();
});

test('the System page shows the switches and which launch steps are done', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create();
    SiteSetting::factory()->create(['coming_soon' => true, 'google_site_verification' => 'token']);

    foreach (LegalPages::SLUGS as $slugs) {
        PageTranslation::factory()->for(Page::factory())->create([
            'locale' => 'en',
            'slug' => $slugs['en'],
            'status' => PublicationStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }

    $this->actingAs($admin)->get(route('admin.system.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('launch.comingSoon', true)
            ->where('launch.noindex', false)
            ->where('launch.testMailRecipient', $admin->email)
            ->where('launch.checklist', [
                ['key' => 'admin_two_factor', 'done' => true],
                ['key' => 'test_mail', 'done' => false],
                ['key' => 'search_console', 'done' => true],
                ['key' => 'legal_pages', 'done' => true],
            ])
        );
});

test('an administrator without two-factor authentication leaves the first step open', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.system.index'))
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('launch.checklist.0', ['key' => 'admin_two_factor', 'done' => false])
            ->where('launch.checklist.3', ['key' => 'legal_pages', 'done' => false])
        );
});
