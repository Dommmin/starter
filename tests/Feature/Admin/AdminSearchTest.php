<?php

use App\Models\ArticleTranslation;
use App\Models\Faq;
use App\Models\MediaAsset;
use App\Models\PageTranslation;
use App\Models\Tag;
use App\Models\User;

beforeEach(function () {
    config(['fortify.require_two_factor_for_admin' => false]);
});

/**
 * @return list<string>
 */
function searchTypes(mixed $response): array
{
    return array_values(array_unique(array_column($response->json('results'), 'type')));
}

test('an editor finds content of every module by a case-insensitive fragment', function () {
    $editor = User::factory()->editor()->create();
    $page = PageTranslation::factory()->create(['title' => 'Bakery offer', 'slug' => 'offer']);
    $article = ArticleTranslation::factory()->create(['title' => 'Our BAKERY story', 'slug' => 'story']);
    Tag::factory()->create(['slug' => 'bakery-news', 'name' => ['en' => 'Bakery news']]);
    Faq::factory()->create(['question' => 'Does the bakery deliver?']);
    MediaAsset::factory()->clean()->create(['original_name' => 'bakery-front.jpg']);
    User::factory()->create(['name' => 'Bakery Owner']);
    PageTranslation::factory()->create(['title' => 'Unrelated', 'slug' => 'unrelated']);

    $response = $this->actingAs($editor)->getJson(route('admin.search', ['q' => 'bakery']))->assertOk();

    expect(searchTypes($response))->toBe(['pages', 'articles', 'tags', 'faqs', 'media'])
        ->and($response->json('results.0'))->toMatchArray([
            'type' => 'pages',
            'id' => $page->page_id,
            'label' => 'Bakery offer',
            'url' => route('admin.pages.edit', $page->page_id),
        ])
        ->and($response->json('results.1.id'))->toBe($article->article_id)
        ->and(collect($response->json('results'))->pluck('label'))->not->toContain('Unrelated');
});

test('accounts are found only by users who may manage them', function () {
    User::factory()->create(['name' => 'Zofia Searchable', 'email' => 'zofia@example.test']);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->getJson(route('admin.search', ['q' => 'zofia@']))
        ->assertOk()
        ->assertJsonPath('results.0.type', 'users')
        ->assertJsonPath('results.0.label', 'Zofia Searchable');

    $editor = User::factory()->editor()->create();
    expect(searchTypes($this->actingAs($editor)->getJson(route('admin.search', ['q' => 'zofia']))))->toBe([]);
});

test('a page with several matching translations is listed once', function () {
    $editor = User::factory()->editor()->create();
    $translation = PageTranslation::factory()->create(['locale' => 'en', 'title' => 'Pricing', 'slug' => 'pricing']);
    PageTranslation::factory()->for($translation->page)->create(['locale' => 'pl', 'title' => 'Pricing PL', 'slug' => 'cennik']);

    $response = $this->actingAs($editor)->getJson(route('admin.search', ['q' => 'pricing']))->assertOk();

    expect($response->json('results'))->toHaveCount(1);
});

test('wildcards in the term are matched literally', function () {
    $editor = User::factory()->editor()->create();
    Faq::factory()->create(['question' => 'Plain question']);
    Faq::factory()->create(['question' => 'Discount 50% today']);

    $response = $this->actingAs($editor)->getJson(route('admin.search', ['q' => '0%']))->assertOk();

    expect(array_column($response->json('results'), 'label'))->toBe(['Discount 50% today']);
    expect($this->actingAs($editor)->getJson(route('admin.search', ['q' => '__']))->json('results'))->toBe([]);
});

test('the term must have at least two characters', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->getJson(route('admin.search', ['q' => 'a']))->assertUnprocessable();
    $this->actingAs($editor)->getJson(route('admin.search'))->assertUnprocessable();
});

test('users without a panel role and guests cannot search', function () {
    $this->getJson(route('admin.search', ['q' => 'bakery']))->assertUnauthorized();
    $this->actingAs(User::factory()->create())->getJson(route('admin.search', ['q' => 'bakery']))->assertForbidden();
});
