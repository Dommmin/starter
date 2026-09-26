<?php

use App\Services\Localization\Exceptions\InvalidLocalizationConfigurationException;
use App\Services\Localization\LocalizationConfig;

test('validates default configuration successfully', function () {
    $config = new LocalizationConfig;
    $config->validate();

    expect($config->getPublicDefault())->toBe('en')
        ->and($config->getAdminDefault())->toBe('en')
        ->and($config->isPublicLocale('en'))->toBeTrue()
        ->and($config->isAdminLocale('en'))->toBeTrue();
});

test('allows EN-only configuration without Polish', function () {
    $config = new LocalizationConfig([
        'registry' => [
            'en' => ['code' => 'en', 'name' => 'English', 'native' => 'English', 'dir' => 'ltr'],
        ],
        'public_locales' => ['en'],
        'public_default' => 'en',
        'public_fallback' => 'en',
        'admin_locales' => ['en'],
        'admin_default' => 'en',
        'admin_fallback' => 'en',
    ]);

    $config->validate();

    expect($config->getPublicLocales())->toBe(['en'])
        ->and($config->getAdminLocales())->toBe(['en'])
        ->and($config->isPublicLocale('pl'))->toBeFalse();
});

test('allows public DE+EN configuration with DE default', function () {
    $config = new LocalizationConfig([
        'registry' => [
            'de' => ['code' => 'de', 'name' => 'German', 'native' => 'Deutsch', 'dir' => 'ltr'],
            'en' => ['code' => 'en', 'name' => 'English', 'native' => 'English', 'dir' => 'ltr'],
        ],
        'public_locales' => ['de', 'en'],
        'public_default' => 'de',
        'public_fallback' => 'de',
        'admin_locales' => ['en'],
        'admin_default' => 'en',
        'admin_fallback' => 'en',
    ]);

    $config->validate();

    expect($config->getPublicDefault())->toBe('de')
        ->and($config->getAdminDefault())->toBe('en')
        ->and($config->isAdminLocale('de'))->toBeFalse()
        ->and($config->isPublicLocale('de'))->toBeTrue();
});

test('rejects duplicate locales in public list', function () {
    $config = new LocalizationConfig([
        'registry' => [
            'en' => ['code' => 'en', 'name' => 'English', 'native' => 'English', 'dir' => 'ltr'],
        ],
        'public_locales' => ['en', 'en'],
        'public_default' => 'en',
        'public_fallback' => 'en',
        'admin_locales' => ['en'],
        'admin_default' => 'en',
        'admin_fallback' => 'en',
    ]);

    expect(fn () => $config->validate())->toThrow(
        InvalidLocalizationConfigurationException::class,
        'duplicate',
    );
});

test('rejects public default not present in public locales', function () {
    $config = new LocalizationConfig([
        'registry' => [
            'en' => ['code' => 'en', 'name' => 'English', 'native' => 'English', 'dir' => 'ltr'],
            'de' => ['code' => 'de', 'name' => 'German', 'native' => 'Deutsch', 'dir' => 'ltr'],
        ],
        'public_locales' => ['en'],
        'public_default' => 'de',
        'public_fallback' => 'en',
        'admin_locales' => ['en'],
        'admin_default' => 'en',
        'admin_fallback' => 'en',
    ]);

    expect(fn () => $config->validate())->toThrow(
        InvalidLocalizationConfigurationException::class,
        'must be one of active public_locales',
    );
});

test('rejects admin locales configuration without en', function () {
    $config = new LocalizationConfig([
        'registry' => [
            'pl' => ['code' => 'pl', 'name' => 'Polish', 'native' => 'Polski', 'dir' => 'ltr'],
        ],
        'public_locales' => ['pl'],
        'public_default' => 'pl',
        'public_fallback' => 'pl',
        'admin_locales' => ['pl'],
        'admin_default' => 'en',
        'admin_fallback' => 'en',
    ]);

    expect(fn () => $config->validate())->toThrow(
        InvalidLocalizationConfigurationException::class,
        "must always include 'en'",
    );
});
