<?php

use App\Data\Home\CtaContentData;
use App\Data\Home\FaqContentData;
use App\Data\Home\HeroContentData;
use App\Data\Home\HeroStatData;
use App\Data\Home\HomeActionData;
use App\Data\Home\LatestArticlesContentData;
use App\Data\Home\PortfolioContentData;
use App\Data\Home\PortfolioItemData;
use App\Data\Home\PricingContentData;
use App\Data\Home\PricingPlanData;
use App\Data\Home\TeamContentData;
use App\Data\Home\TeamMemberData;
use App\Data\Media\MediaImageData;
use App\Enums\HomeLinkTarget;
use App\Enums\HomeSectionType;
use App\Models\ArticleTranslation;
use App\Models\Faq;
use App\Models\HomeSection;
use App\Models\MediaAsset;
use App\Models\PageTranslation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the home page renders only enabled sections of its locale in position order', function () {
    HomeSection::factory()->ofType(HomeSectionType::Cta)->enabled()->create(['position' => 1, 'content' => new CtaContentData(title: 'Bottom line')]);
    HomeSection::factory()->ofType(HomeSectionType::Hero)->enabled()->create(['position' => 2, 'content' => new HeroContentData(title: 'English hero')]);
    HomeSection::factory()->ofType(HomeSectionType::Features)->create(['position' => 3]);
    HomeSection::factory()->ofType(HomeSectionType::Hero)->enabled()->create(['locale' => 'pl', 'content' => new HeroContentData(title: 'Polski baner')]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('welcome', false)
            ->has('contactForm.token')
            ->has('sections', 2)
            ->where('sections.0.type', 'cta')
            ->where('sections.0.anchor', 'cta')
            ->where('sections.0.content.title', 'Bottom line')
            ->where('sections.1.type', 'hero')
            ->where('sections.1.anchor', 'hero')
            ->where('sections.1.content.title', 'English hero')
        );

    $this->get('/pl')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('welcome', false)
            ->has('sections', 1)
            ->where('sections.0.content.title', 'Polski baner')
        );
});

test('a locale without sections renders an empty page', function () {
    HomeSection::factory()->ofType(HomeSectionType::Hero)->enabled()->create(['locale' => 'pl']);

    $this->get('/de')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('welcome', false)
            ->where('sections', [])
            ->has('seo')
        );
});

test('the hero image is resolved from the DAM and a missing or unusable one is dropped', function () {
    $image = MediaAsset::factory()->withVariants()->create(['alt' => 'Our team at work']);
    $quarantined = MediaAsset::factory()->create();
    HomeSection::factory()->ofType(HomeSectionType::Hero)->enabled()->create(['content' => new HeroContentData(
        title: 'With image',
        imageId: $image->id,
        stats: [new HeroStatData(value: '300+', label: 'Projects')],
    )]);
    HomeSection::factory()->ofType(HomeSectionType::Hero)->enabled()->create(['locale' => 'pl', 'content' => new HeroContentData(
        title: 'Bez obrazu',
        imageId: $quarantined->id,
    )]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('sections.0.content.imageAlt', 'Our team at work')
            ->where('sections.0.content.image.width', MediaImageData::fromAsset($image)?->width)
            ->where('sections.0.content.stats', [['value' => '300+', 'label' => 'Projects']])
        );

    $this->get('/pl')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('sections.0.content.image', null)
            ->where('sections.0.content.imageAlt', '')
            ->where('sections.0.content.stats', [])
        );

    $image->delete();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia->where('sections.0.content.image', null));
});

test('portfolio thumbnails are resolved per project and unusable ones are dropped', function () {
    $image = MediaAsset::factory()->withVariants()->create(['alt' => 'Shop home page']);
    $quarantined = MediaAsset::factory()->create();
    HomeSection::factory()->ofType(HomeSectionType::Portfolio)->enabled()->create(['content' => new PortfolioContentData(
        items: [
            new PortfolioItemData(title: 'Shop', category: 'E-commerce', imageId: $image->id, url: 'https://shop.example.com'),
            new PortfolioItemData(title: 'Logo', imageId: $quarantined->id),
        ],
        title: 'Our work',
    )]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('sections.0.type', 'portfolio')
            ->where('sections.0.anchor', 'portfolio')
            ->where('sections.0.content.title', 'Our work')
            ->where('sections.0.content.items.0.imageAlt', 'Shop home page')
            ->where('sections.0.content.items.0.image.width', MediaImageData::fromAsset($image)?->width)
            ->where('sections.0.content.items.0.url', 'https://shop.example.com')
            ->where('sections.0.content.items.0.category', 'E-commerce')
            ->where('sections.0.content.items.1.image', null)
            ->where('sections.0.content.items.1.imageAlt', '')
        );
});

test('team photos are resolved per member and a deleted one is dropped', function () {
    $photo = MediaAsset::factory()->withVariants()->create(['alt' => 'Ada smiling']);
    $deleted = MediaAsset::factory()->withVariants()->create();
    HomeSection::factory()->ofType(HomeSectionType::Team)->enabled()->create(['content' => new TeamContentData(
        items: [
            new TeamMemberData(name: 'Ada', role: 'Developer', photoId: $photo->id, skills: ['Laravel']),
            new TeamMemberData(name: 'Bo', role: 'Designer', photoId: $deleted->id),
        ],
    )]);
    $deleted->delete();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('sections.0.type', 'team')
            ->where('sections.0.anchor', 'team')
            ->where('sections.0.content.items.0.photoAlt', 'Ada smiling')
            ->where('sections.0.content.items.0.photo.width', MediaImageData::fromAsset($photo)?->width)
            ->where('sections.0.content.items.0.skills', ['Laravel'])
            ->where('sections.0.content.items.1.photo', null)
        );
});

test('pricing plan actions are resolved and an unpublished page action is dropped', function () {
    $published = PageTranslation::factory()->published()->create(['slug' => 'offer']);
    $draft = PageTranslation::factory()->draft()->create();
    HomeSection::factory()->ofType(HomeSectionType::Pricing)->enabled()->create(['content' => new PricingContentData(
        items: [
            new PricingPlanData(name: 'Start', priceMinor: 290000, priceFrom: true, features: ['Five pages'], highlighted: true, action: new HomeActionData(label: 'Details', target: HomeLinkTarget::Page, pageId: $published->page_id)),
            new PricingPlanData(name: 'Shop', action: new HomeActionData(label: 'Draft', target: HomeLinkTarget::Page, pageId: $draft->page_id)),
            new PricingPlanData(name: 'Logo', action: new HomeActionData(label: 'Write', target: HomeLinkTarget::Contact)),
        ],
        note: 'Prices are net.',
    )]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('sections.0.type', 'pricing')
            ->where('sections.0.anchor', 'pricing')
            ->where('sections.0.content.items.0.priceMinor', 290000)
            ->where('sections.0.content.items.0.currency', 'PLN')
            ->where('sections.0.content.items.0.action.url', url('/offer'))
            ->where('sections.0.content.items.1.action', null)
            ->where('sections.0.content.items.2.action.url', '#contact')
            ->where('sections.0.content.note', 'Prices are net.')
        );
});

test('actions are resolved to URLs of the locale and unavailable ones are dropped', function () {
    $page = PageTranslation::factory()->locale('pl')->published()->create(['slug' => 'o-nas']);
    $draft = PageTranslation::factory()->locale('pl')->draft()->create();

    HomeSection::factory()->ofType(HomeSectionType::Hero)->enabled()->create([
        'locale' => 'pl',
        'content' => new HeroContentData(
            title: 'Hero',
            primaryAction: new HomeActionData(label: 'O nas', target: HomeLinkTarget::Page, pageId: $page->page_id),
            secondaryAction: new HomeActionData(label: 'Szkic', target: HomeLinkTarget::Page, pageId: $draft->page_id),
        ),
    ]);
    HomeSection::factory()->ofType(HomeSectionType::Cta)->enabled()->create([
        'locale' => 'pl',
        'content' => new CtaContentData(
            title: 'CTA',
            primaryAction: new HomeActionData(label: 'Napisz', target: HomeLinkTarget::Contact),
            secondaryAction: new HomeActionData(label: 'Artykuły', target: HomeLinkTarget::Articles),
        ),
    ]);

    $this->get('/pl')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('sections.0.content.primaryAction', ['label' => 'O nas', 'url' => url('/pl/o-nas')])
            ->where('sections.0.content.secondaryAction', null)
            ->where('sections.1.content.primaryAction', ['label' => 'Napisz', 'url' => '#contact'])
            ->where('sections.1.content.secondaryAction', ['label' => 'Artykuły', 'url' => url('/pl/articles')])
        );
});

test('a portfolio action links to the portfolio section of the same page', function () {
    HomeSection::factory()->ofType(HomeSectionType::Cta)->enabled()->create([
        'content' => new CtaContentData(
            title: 'CTA',
            primaryAction: new HomeActionData(label: 'Our work', target: HomeLinkTarget::Portfolio),
        ),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('sections.0.content.primaryAction', ['label' => 'Our work', 'url' => '#portfolio'])
        );
});

test('guest-only actions follow the viewer: log in for guests, the panel for its users, nothing for others', function () {
    HomeSection::factory()->ofType(HomeSectionType::Cta)->enabled()->create([
        'content' => new CtaContentData(
            title: 'CTA',
            primaryAction: new HomeActionData(label: 'Join', target: HomeLinkTarget::Register),
            secondaryAction: new HomeActionData(label: 'Sign in', target: HomeLinkTarget::Login),
        ),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('sections.0.content.primaryAction', ['label' => 'Join', 'url' => url('/register')])
            ->where('sections.0.content.secondaryAction', ['label' => 'Sign in', 'url' => url('/login')])
        );

    $this->actingAs(User::factory()->editor()->create())
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('sections.0.content.primaryAction', null)
            ->where('sections.0.content.secondaryAction', ['label' => 'Open admin panel', 'url' => route('admin.index')])
        );

    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('sections.0.content.primaryAction', null)
            ->where('sections.0.content.secondaryAction', null)
        );
});

test('the latest articles section lists only published articles of the locale', function () {
    ArticleTranslation::factory()->published(now()->subDays(3))->create(['title' => 'Oldest']);
    ArticleTranslation::factory()->published(now()->subDays(2))->create(['title' => 'Middle']);
    ArticleTranslation::factory()->published(now()->subDay())->create(['title' => 'Newest', 'slug' => 'newest']);
    ArticleTranslation::factory()->draft()->create(['title' => 'Draft']);
    ArticleTranslation::factory()->scheduled()->create(['title' => 'Scheduled']);
    ArticleTranslation::factory()->locale('pl')->published()->create(['title' => 'Polski']);

    HomeSection::factory()->ofType(HomeSectionType::LatestArticles)->enabled()->create([
        'content' => new LatestArticlesContentData(limit: 2, title: 'News'),
    ]);

    $this->get('/?page=2')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('sections.0.type', 'latest_articles')
            ->where('sections.0.anchor', 'latest-articles')
            ->where('sections.0.content.title', 'News')
            ->where('sections.0.content.listUrl', url('/articles'))
            ->has('sections.0.content.items', 2)
            ->where('sections.0.content.items.0.title', 'Newest')
            ->where('sections.0.content.items.0.url', url('/articles/newest'))
            ->where('sections.0.content.items.1.title', 'Middle')
        );
});

test('the faq section shows published questions of the locale and of every locale', function () {
    Faq::factory()->create(['question' => 'Second', 'locale' => null, 'published' => true, 'position' => 2]);
    Faq::factory()->create(['question' => 'First', 'locale' => 'pl', 'published' => true, 'position' => 1]);
    Faq::factory()->create(['question' => 'English', 'locale' => 'en', 'published' => true, 'position' => 0]);
    Faq::factory()->create(['question' => 'Hidden', 'locale' => 'pl', 'published' => false, 'position' => 0]);
    Faq::factory()->create(['question' => 'Third', 'locale' => null, 'published' => true, 'position' => 3]);

    HomeSection::factory()->ofType(HomeSectionType::Faq)->enabled()->create([
        'locale' => 'pl',
        'content' => new FaqContentData(title: 'Pytania', limit: 2),
    ]);

    $this->get('/pl')
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->where('sections.0.type', 'faq')
            ->where('sections.0.content.title', 'Pytania')
            ->has('sections.0.content.items', 2)
            ->where('sections.0.content.items.0.question', 'First')
            ->where('sections.0.content.items.1.question', 'Second')
        );
});
