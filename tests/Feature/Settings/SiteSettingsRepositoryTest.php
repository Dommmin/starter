<?php

use App\Actions\Media\DeleteMediaAsset;
use App\Actions\Settings\UpdateSiteSettings;
use App\Jobs\SendContactMessage;
use App\Mail\ContactMessageMail;
use App\Models\ContactMessage;
use App\Models\MediaAsset;
use App\Models\SiteSetting;
use App\Models\SiteSettingTranslation;
use App\Models\User;
use App\Repositories\Settings\SiteSettingsRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('media');
    Storage::fake('public');
    config([
        'seo.site_name' => 'Configured Site',
        'seo.default_image' => '/images/og.png',
        'contact.recipient' => 'env-inbox@example.test',
    ]);
});

function freshSiteSettings(): SiteSettingsRepository
{
    return app(SiteSettingsRepository::class);
}

test('without saved settings the configuration and catalog fallbacks apply', function () {
    $settings = freshSiteSettings();

    expect($settings->exists())->toBeFalse()
        ->and($settings->siteName())->toBe('Configured Site')
        ->and($settings->contactRecipient())->toBe('env-inbox@example.test')
        ->and($settings->defaultImageUrl())->toBe(url('/images/og.png'))
        ->and($settings->defaultTitle('pl'))->toBe(__('public.site.defaultTitle', [], 'pl'))
        ->and($settings->defaultDescription('de'))->toBe(__('public.site.defaultDescription', [], 'de'));

    $current = $settings->current('en');
    expect($current->name)->toBe('Configured Site')
        ->and($current->isCustomized)->toBeFalse()
        ->and($current->logo)->toBeNull()
        ->and($current->tagline)->toBeNull()
        ->and($current->social)->toBe([]);
});

test('the settings are read from the cache without queries and refreshed after a save', function () {
    SiteSetting::factory()->create(['site_name' => 'Cached Name']);

    expect(freshSiteSettings()->siteName())->toBe('Cached Name');

    DB::enableQueryLog();
    $second = freshSiteSettings();
    expect($second->siteName())->toBe('Cached Name')
        ->and($second->current('en')->name)->toBe('Cached Name')
        ->and(DB::getQueryLog())->toBe([]);
    DB::disableQueryLog();

    app(UpdateSiteSettings::class)->updateSiteName('Renamed');

    expect(freshSiteSettings()->siteName())->toBe('Renamed');
});

test('translated texts fall back to the public fallback locale and saved settings drop the catalog defaults', function () {
    $settings = SiteSetting::factory()->create(['site_name' => 'Acme']);
    SiteSettingTranslation::factory()->for($settings)->locale('en')->create([
        'tagline' => 'English tagline',
        'seo_title' => 'English title',
        'seo_description' => null,
    ]);
    SiteSettingTranslation::factory()->for($settings)->locale('pl')->create([
        'tagline' => 'Polskie hasło',
        'seo_title' => null,
        'seo_description' => null,
    ]);

    $repository = freshSiteSettings();

    expect($repository->current('pl')->tagline)->toBe('Polskie hasło')
        ->and($repository->current('de')->tagline)->toBe('English tagline')
        ->and($repository->defaultTitle('pl'))->toBe('English title')
        ->and($repository->defaultDescription('pl'))->toBeNull();
});

test('only clean images with variants are exposed and a deleted logo disappears', function () {
    $logo = MediaAsset::factory()->withVariants()->create();
    $quarantined = MediaAsset::factory()->create();
    SiteSetting::factory()->create(['logo_media_id' => $logo->id, 'og_image_media_id' => $quarantined->id]);

    $repository = freshSiteSettings();
    expect($repository->current('en')->logo?->width)->toBe(640)
        ->and($repository->defaultImageUrl())->toBe(url('/images/og.png'));

    app(DeleteMediaAsset::class)->handle($logo, User::factory()->admin()->create());

    expect(freshSiteSettings()->current('en')->logo)->toBeNull()
        ->and(SiteSetting::query()->sole()->logo_media_id)->toBeNull();
});

test('public pages share the site settings without the contact form recipient', function () {
    $settings = SiteSetting::factory()->create([
        'site_name' => 'Acme',
        'contact_email' => 'hello@acme.test',
        'address_line' => 'Main Street 1',
        'postal_code' => '00-001',
        'city' => 'Warsaw',
        'country_code' => 'PL',
        'contact_recipient_email' => 'secret-inbox@acme.test',
        'social_links' => ['github' => 'https://github.com/acme', 'facebook' => 'https://facebook.com/acme'],
    ]);
    SiteSettingTranslation::factory()->for($settings)->create([
        'tagline' => 'We build things',
        'footer_text' => 'Acme. All rights reserved.',
        'seo_title' => 'Acme — software house',
        'seo_description' => 'Web applications.',
    ]);

    $response = $this->get('/')->assertOk();

    $response->assertInertia(fn (Assert $inertia) => $inertia
        ->component('welcome')
        ->where('site', [
            'name' => 'Acme',
            'isCustomized' => true,
            'logo' => null,
            'tagline' => 'We build things',
            'footerText' => 'Acme. All rights reserved.',
            'contact' => [
                'email' => 'hello@acme.test',
                'phone' => '+48 123 456 789',
                'address' => "Main Street 1\n00-001 Warsaw\nPL",
            ],
            'social' => [
                ['network' => 'facebook', 'label' => 'Facebook', 'url' => 'https://facebook.com/acme'],
                ['network' => 'github', 'label' => 'GitHub', 'url' => 'https://github.com/acme'],
            ],
        ])
        ->where('seo.siteName', 'Acme')
        ->where('seo.defaultTitle', 'Acme — software house')
        ->where('seo.defaultDescription', 'Web applications.')
        ->where('seo.organization.name', 'Acme')
    );

    expect($response->getContent())->not->toContain('secret-inbox@acme.test');
});

test('saved images replace the configured sharing image and organization logo', function () {
    $logo = MediaAsset::factory()->withVariants()->create();
    $ogImage = MediaAsset::factory()->withVariants()->create();
    SiteSetting::factory()->create(['logo_media_id' => $logo->id, 'og_image_media_id' => $ogImage->id]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('seo.defaultImage', Storage::disk('public')->url("media/{$ogImage->uuid}/abc123-640.jpg"))
            ->where('seo.organization.logo', Storage::disk('public')->url("media/{$logo->uuid}/abc123-640.jpg"))
            ->where('site.logo.width', 640)
        );
});

test('the contact message goes to the recipient from the settings at send time', function () {
    Mail::fake();
    $message = ContactMessage::factory()->create();
    SiteSetting::factory()->create(['contact_recipient_email' => 'panel-inbox@acme.test']);

    app()->call([new SendContactMessage($message->id), 'handle']);

    Mail::assertSent(ContactMessageMail::class, fn (ContactMessageMail $mail) => $mail->hasTo('panel-inbox@acme.test'));
    Mail::assertNotSent(ContactMessageMail::class, fn (ContactMessageMail $mail) => $mail->hasTo('env-inbox@example.test'));
});

test('without a recipient in the settings the contact message goes to the configured address', function () {
    Mail::fake();
    $message = ContactMessage::factory()->create();
    SiteSetting::factory()->create(['contact_recipient_email' => null]);

    app()->call([new SendContactMessage($message->id), 'handle']);

    Mail::assertSent(ContactMessageMail::class, fn (ContactMessageMail $mail) => $mail->hasTo('env-inbox@example.test'));
});
