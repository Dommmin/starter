<?php

use App\Models\Page;
use App\Models\PageTranslation;
use Inertia\Testing\AssertableInertia as Assert;

test('a published page renders sanitized html with alternates of published translations', function () {
    $page = Page::factory()->create();
    PageTranslation::factory()->for($page)->published()->create([
        'title' => 'Privacy policy',
        'slug' => 'privacy-policy',
        'meta_description' => 'How we process data.',
        'body' => ['type' => 'doc', 'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Scope']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '<script>alert(1)</script>']]],
        ]],
    ]);
    PageTranslation::factory()->for($page)->locale('pl')->published()->create(['slug' => 'polityka-prywatnosci']);
    PageTranslation::factory()->for($page)->locale('de')->draft()->create(['slug' => 'datenschutz']);

    $this->get('/privacy-policy')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('pages/show', false)
            ->where('title', 'Privacy policy')
            ->where('metaDescription', 'How we process data.')
            ->where('locale', 'en')
            ->where('bodyHtml', '<h2>Scope</h2><p>&lt;script&gt;alert(1)&lt;/script&gt;</p>')
            ->where('alternates', [
                'en' => url('/privacy-policy'),
                'pl' => url('/pl/polityka-prywatnosci'),
                'x-default' => url('/privacy-policy'),
            ])
        );
});

test('a translation is served under its own locale prefix and slug', function () {
    $page = Page::factory()->published()->create();
    PageTranslation::factory()->for($page)->locale('pl')->published()->create(['title' => 'O nas', 'slug' => 'o-nas']);

    $this->get('/pl/o-nas')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('pages/show', false)
            ->where('title', 'O nas')
            ->where('locale', 'pl')
        );

    $this->get('/o-nas')->assertNotFound();
    $this->get('/de/o-nas')->assertNotFound();
});

test('drafts and unknown slugs are not found', function () {
    PageTranslation::factory()->draft()->create(['slug' => 'secret-draft', 'title' => 'Secret draft']);

    $this->get('/secret-draft')->assertNotFound()->assertDontSee('Secret draft');
    $this->get('/missing-page')->assertNotFound();
});

test('the content catch-all does not shadow system routes', function () {
    PageTranslation::factory()->published()->create(['slug' => 'about']);

    $this->get('/login')->assertOk()->assertInertia(fn (Assert $inertia) => $inertia->component('auth/login'));
    $this->get('/admin')->assertRedirect(route('login'));
    $this->get('/about')->assertOk()->assertSee('about');
    $this->get('/pl/admin')->assertNotFound();
});
