<?php

use App\Enums\ContactMessageStatus;
use App\Enums\HomeSectionType;
use App\Enums\MediaStatus;
use App\Enums\PublicationStatus;
use App\Enums\UserRole;
use App\Models\Article;
use App\Models\ArticleSlugRedirect;
use App\Models\ArticleTranslation;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\Faq;
use App\Models\HomeSection;
use App\Models\MediaAsset;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageSlugRedirect;
use App\Models\PageTranslation;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\ArticleSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\HomeSectionSeeder;
use Database\Seeders\PageSeeder;
use Database\Seeders\SiteSettingsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * @return array<string, int>
 */
function seededCounts(): array
{
    return [
        'users' => User::query()->count(),
        'media' => MediaAsset::query()->count(),
        'pages' => Page::query()->count(),
        'articles' => Article::query()->count(),
        'faqs' => Faq::query()->count(),
        'contact messages' => ContactMessage::query()->count(),
        'menu items' => MenuItem::query()->count(),
        'home sections' => HomeSection::query()->count(),
        'audit entries' => AuditLog::query()->count(),
    ];
}

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    Storage::fake('media');
    Storage::fake((string) config('media.public_disk'));
});

test('the local seed fills every module and a second run adds nothing', function () {
    $this->app['env'] = 'local';

    $this->seed(DatabaseSeeder::class);
    $first = seededCounts();
    $mediaFiles = Storage::disk('media')->allFiles();

    $this->seed(DatabaseSeeder::class);

    expect(array_filter($first, fn (int $count): bool => $count === 0))->toBe([])
        ->and(seededCounts())->toBe($first)
        ->and(Storage::disk('media')->allFiles())->toBe($mediaFiles)
        ->and(SiteSetting::query()->value('contact_email'))->not->toBeNull()
        ->and(SiteSetting::query()->value('site_name'))->toBe(SiteSettingsSeeder::SITE_NAME);
});

test('the local seed gives every admin list two pages and every state to review', function () {
    $this->app['env'] = 'local';

    $this->seed(DatabaseSeeder::class);

    $perPage = 15;
    $mfa = User::query()->where('email', 'mfa@example.com')->firstOrFail();
    $articleTranslations = ArticleTranslation::query()->get();
    $pageTranslations = PageTranslation::query()->get();
    $homeOrder = fn (string $locale): array => HomeSection::query()->where('locale', $locale)->orderBy('position')->pluck('type')->all();

    expect(collect(seededCounts())->only(['users', 'media', 'pages', 'articles', 'faqs', 'contact messages', 'audit entries']))
        ->each->toBeGreaterThan($perPage)
        ->and(MediaAsset::query()->distinct()->pluck('status')->sort()->values()->all())->toEqualCanonicalizing(MediaStatus::cases())
        ->and(ContactMessage::query()->distinct()->pluck('status')->all())->toEqualCanonicalizing(ContactMessageStatus::cases())
        ->and($articleTranslations->where('status', PublicationStatus::Draft))->not->toBeEmpty()
        ->and($articleTranslations->where('status', PublicationStatus::Published)->filter(fn (ArticleTranslation $translation): bool => $translation->published_at->isFuture()))->not->toBeEmpty()
        ->and($pageTranslations->where('status', PublicationStatus::Published)->filter(fn (PageTranslation $translation): bool => $translation->published_at->isFuture()))->not->toBeEmpty()
        ->and($articleTranslations->pluck('locale')->unique()->values()->all())->toEqualCanonicalizing(['en', 'pl', 'de'])
        ->and($pageTranslations->pluck('locale')->unique()->values()->all())->toEqualCanonicalizing(['en', 'pl', 'de'])
        ->and(Faq::query()->distinct()->pluck('locale')->all())->toEqualCanonicalizing(['en', 'pl', 'de'])
        ->and(MenuItem::query()->whereNotNull('parent_id')->distinct()->pluck('locale')->all())->toEqualCanonicalizing(['en', 'pl', 'de'])
        ->and(User::query()->whereNotNull('role')->distinct()->pluck('role')->all())->toEqualCanonicalizing(UserRole::cases())
        ->and(User::query()->whereNull('role')->exists())->toBeTrue()
        ->and($mfa->role)->toBe(UserRole::Admin)
        ->and($mfa->two_factor_confirmed_at)->not->toBeNull()
        ->and(decrypt((string) $mfa->two_factor_secret))->toBe(DemoUserSeeder::TWO_FACTOR_SECRET)
        ->and(PageSlugRedirect::query()->count())->toBe(count(PageSeeder::REDIRECTS))
        ->and(ArticleSlugRedirect::query()->count())->toBe(count(ArticleSeeder::REDIRECTS))
        ->and(HomeSection::query()->where('enabled', false)->exists())->toBeTrue()
        ->and($homeOrder('en'))->not->toBe($homeOrder('pl'));
});

test('the local seed spreads samples over past dates and leaves them unedited', function () {
    $this->app['env'] = 'local';

    $this->seed(DatabaseSeeder::class);

    $days = fn (string $model): int => $model::query()->pluck('created_at')->map->toDateString()->unique()->count();

    expect(Carbon::getTestNow())->toBeNull()
        ->and($days(User::class))->toBeGreaterThan(10)
        ->and($days(MediaAsset::class))->toBeGreaterThan(10)
        ->and($days(Article::class))->toBeGreaterThan(10)
        ->and($days(AuditLog::class))->toBeGreaterThan(10)
        ->and(AuditLog::query()->where('created_at', '>', now())->exists())->toBeFalse()
        ->and(ArticleTranslation::query()->whereColumn('updated_at', '>', 'created_at')->exists())->toBeFalse()
        ->and(PageTranslation::query()->whereColumn('updated_at', '>', 'created_at')->exists())->toBeFalse()
        ->and(User::query()->whereColumn('updated_at', '>', 'created_at')->exists())->toBeFalse();
});

test('the local seed has no sample that only repeats its title or describes the starter', function () {
    $this->app['env'] = 'local';

    $this->seed(DatabaseSeeder::class);

    $bodyRepeatsTitle = fn (ArticleTranslation|PageTranslation $translation): bool => count($translation->body['content']) === 1
        && data_get($translation->body, 'content.0.content.0.text') === $translation->title;
    $hero = HomeSection::query()->where('locale', 'en')->where('type', HomeSectionType::Hero)->firstOrFail();

    expect(ArticleTranslation::query()->get()->filter($bodyRepeatsTitle))->toBeEmpty()
        ->and(PageTranslation::query()->get()->filter($bodyRepeatsTitle))->toBeEmpty()
        ->and(Faq::query()->pluck('question')->reject(fn (string $question): bool => str_ends_with($question, '?')))->toBeEmpty()
        ->and(Article::query()->whereNotNull('cover_media_id')->count())->toBeGreaterThan(Article::query()->whereNull('cover_media_id')->count())
        ->and($hero->content->title)->toBe(HomeSectionSeeder::BRAND['en']['title']);
});

test('the local seed keeps a site name that is already saved', function () {
    $this->app['env'] = 'local';
    SiteSetting::factory()->create(['site_name' => 'Owner site', 'contact_email' => null]);

    $this->seed(DatabaseSeeder::class);

    expect(SiteSetting::query()->value('site_name'))->toBe('Owner site')
        ->and(SiteSetting::query()->value('contact_email'))->not->toBeNull();
});

test('outside the local environment only menus and home sections are seeded', function (string $environment) {
    $this->app['env'] = $environment;

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();

    expect(User::query()->exists())->toBeFalse()
        ->and(MediaAsset::query()->exists())->toBeFalse()
        ->and(Page::query()->exists())->toBeFalse()
        ->and(Article::query()->exists())->toBeFalse()
        ->and(Faq::query()->exists())->toBeFalse()
        ->and(ContactMessage::query()->exists())->toBeFalse()
        ->and(SiteSetting::query()->exists())->toBeFalse()
        ->and(Storage::disk('media')->allFiles())->toBe([])
        ->and(MenuItem::query()->whereNotNull('url')->exists())->toBeFalse()
        ->and(HomeSection::query()->where('type', HomeSectionType::Faq)->where('enabled', true)->exists())->toBeFalse();
})->with(['testing', 'production']);
