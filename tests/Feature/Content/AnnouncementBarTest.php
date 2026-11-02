<?php

use App\Models\SiteSetting;
use App\Models\SiteSettingTranslation;
use App\Repositories\Settings\SiteSettingsRepository;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $attributes
 */
function announcementSettings(array $attributes = []): SiteSetting
{
    $settings = SiteSetting::factory()->create([
        'announcement_enabled' => true,
        'announcement_tone' => 'warning',
        'announcement_link_url' => '/contact',
        ...$attributes,
    ]);

    SiteSettingTranslation::factory()->for($settings)->locale('en')->create([
        'announcement_text' => 'Closed 24–26.12',
        'announcement_link_label' => 'Opening hours',
    ]);
    SiteSettingTranslation::factory()->for($settings)->locale('pl')->create([
        'announcement_text' => 'Nieczynne 24–26.12',
        'announcement_link_label' => 'Godziny otwarcia',
    ]);

    return $settings;
}

test('public pages share the announcement in the visitor language', function (string $url, string $message, string $label) {
    announcementSettings();

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia->where('site.announcement', [
            'message' => $message,
            'tone' => 'warning',
            'linkUrl' => '/contact',
            'linkLabel' => $label,
        ]));
})->with([
    'english' => ['/', 'Closed 24–26.12', 'Opening hours'],
    'polish' => ['/pl', 'Nieczynne 24–26.12', 'Godziny otwarcia'],
    'german falls back to the fallback locale' => ['/de', 'Closed 24–26.12', 'Opening hours'],
]);

test('the announcement is shown only within its time window', function (?string $startsAt, ?string $endsAt, bool $isShown) {
    $this->travelTo('2026-12-24 12:00:00');
    announcementSettings([
        'announcement_starts_at' => $startsAt,
        'announcement_ends_at' => $endsAt,
    ]);

    $this->get('/')->assertInertia(fn (Assert $inertia) => $isShown
        ? $inertia->where('site.announcement.message', 'Closed 24–26.12')
        : $inertia->where('site.announcement', null));
})->with([
    'no limits' => [null, null, true],
    'within the window' => ['2026-12-24 00:00:00', '2026-12-26 23:59:59', true],
    'on the last second' => ['2026-12-20 00:00:00', '2026-12-24 12:00:00', true],
    'before the start' => ['2026-12-24 12:00:01', null, false],
    'after the end' => [null, '2026-12-24 11:59:59', false],
]);

test('no announcement is shared while it is off or has no text', function () {
    $settings = announcementSettings(['announcement_enabled' => false]);

    $this->get('/')->assertInertia(fn (Assert $inertia) => $inertia->where('site.announcement', null));

    $settings->forceFill(['announcement_enabled' => true])->save();
    SiteSettingTranslation::query()->update(['announcement_text' => null]);
    app(SiteSettingsRepository::class)->forget();

    $this->get('/pl')->assertInertia(fn (Assert $inertia) => $inertia->where('site.announcement', null));
});

test('a link without a label in the message language is left out', function () {
    $settings = announcementSettings();
    $settings->translations()->where('locale', 'pl')->update(['announcement_link_label' => null]);

    $this->get('/pl')->assertInertia(fn (Assert $inertia) => $inertia
        ->where('site.announcement.message', 'Nieczynne 24–26.12')
        ->where('site.announcement.linkUrl', null)
        ->where('site.announcement.linkLabel', null)
    );
});
