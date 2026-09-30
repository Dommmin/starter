<?php

use App\Enums\HomeSectionType;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\Faq;
use App\Models\HomeSection;
use App\Models\MediaAsset;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
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
        ->and(HomeSection::query()->where('enabled', false)->exists())->toBeFalse();
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
