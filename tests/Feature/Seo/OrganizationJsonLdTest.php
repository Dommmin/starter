<?php

use App\Models\SiteSetting;
use Inertia\Testing\AssertableInertia as Assert;

test('the home page describes a local business with its address and opening hours', function () {
    SiteSetting::factory()->create([
        'site_name' => 'Acme Bakery',
        'contact_email' => 'hello@acme.test',
        'address_line' => 'Main Street 1',
        'postal_code' => '00-001',
        'city' => 'Warsaw',
        'country_code' => 'PL',
        'tax_id' => 'PL 123-456-32-18',
        'map_url' => 'https://maps.example.test/acme',
        'social_links' => ['facebook' => 'https://facebook.com/acme'],
        'opening_hours' => [
            ['day' => 'monday', 'opens' => '08:00', 'closes' => '16:00'],
            ['day' => 'sunday', 'opens' => null, 'closes' => null],
        ],
    ]);

    $this->get('/')->assertOk()->assertInertia(fn (Assert $inertia) => $inertia
        ->where('organizationJsonLd.@type', 'LocalBusiness')
        ->where('organizationJsonLd.name', 'Acme Bakery')
        ->where('organizationJsonLd.taxID', 'PL 123-456-32-18')
        ->where('organizationJsonLd.hasMap', 'https://maps.example.test/acme')
        ->where('organizationJsonLd.address', [
            '@type' => 'PostalAddress',
            'streetAddress' => 'Main Street 1',
            'postalCode' => '00-001',
            'addressLocality' => 'Warsaw',
            'addressCountry' => 'PL',
        ])
        ->where('organizationJsonLd.openingHoursSpecification', [
            ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'Monday', 'opens' => '08:00', 'closes' => '16:00'],
        ])
        ->where('organizationJsonLd.sameAs', ['https://facebook.com/acme'])
    );
});

test('without opening hours the home page describes an organization without empty fields', function () {
    SiteSetting::factory()->create(['site_name' => 'Acme', 'contact_phone' => null, 'tax_id' => null]);

    $this->get('/')->assertOk()->assertInertia(fn (Assert $inertia) => $inertia
        ->where('organizationJsonLd.@type', 'Organization')
        ->missing('organizationJsonLd.telephone')
        ->missing('organizationJsonLd.taxID')
        ->missing('organizationJsonLd.openingHoursSpecification')
    );
});
