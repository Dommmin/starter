<?php

use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Load the showcase route file the way web.php does (inside the `web`
 * group); the test run boots outside `local`, so it was skipped at boot.
 */
function loadPublicDesignSystemShowcaseRoutes(): void
{
    Route::middleware('web')->group(base_path('routes/design-system.php'));

    app('router')->getRoutes()->refreshNameLookups();
}

function registerLocalPublicDesignSystemShowcase(): void
{
    app()->detectEnvironment(fn (): string => 'local');

    loadPublicDesignSystemShowcaseRoutes();
}

test('a guest opens the public showcase in the local environment, not indexable', function () {
    registerLocalPublicDesignSystemShowcase();

    $this->get('/_design-system')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn (Assert $page) => $page
            ->component('design-system/index')
            ->where('i18n.area', 'public')
            ->where('i18n.locale', 'en')
            ->where('i18n.messages.admin.designSystem.web.title', 'Design system — public website')
            ->has('i18n.messages.admin.designSystem.states.submenu')
            ->missing('i18n.messages.admin.dashboard')
            ->has('navigation'),
        );
});

test('the public showcase is served in the other public locales', function () {
    registerLocalPublicDesignSystemShowcase();

    $this->get('/pl/_design-system')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('design-system/index')
            ->where('i18n.locale', 'pl')
            ->where('i18n.messages.admin.designSystem.web.title', 'Design system — strona publiczna'),
        );
});

test('other public pages do not receive the showcase catalog', function () {
    registerLocalPublicDesignSystemShowcase();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('i18n.messages.admin'));
});

test('the public showcase is not listed in the sitemap', function () {
    registerLocalPublicDesignSystemShowcase();

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertDontSee('_design-system', false);
});

test('the public showcase does not exist outside the local environment', function () {
    expect(app()->environment('local'))->toBeFalse();

    $this->get('/_design-system')->assertNotFound();
    $this->get('/pl/_design-system')->assertNotFound();
});

test('the public showcase controller refuses to render outside local even when the route is loaded', function () {
    loadPublicDesignSystemShowcaseRoutes();

    $this->get('/_design-system')->assertNotFound();
});
