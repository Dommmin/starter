<?php

use App\Contracts\Settings\UpdatesSiteName;
use App\Enums\UserRole;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\User;
use App\Notifications\AccountInvitation;
use App\Support\Env\EnvFileEditor;
use Database\Seeders\ArticleSeeder;
use Database\Seeders\DemoContent;
use Database\Seeders\PageSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

const INIT_ENV = "APP_NAME=Laravel\n# keep this comment\nAPP_PUBLIC_LOCALES=en,pl,de\nAPP_ENV=local\n";

/**
 * Options of a complete, valid run.
 *
 * @return array<string, mixed>
 */
function initOptions(array $overrides = []): array
{
    return [
        '--name' => 'Acme',
        '--locales' => 'pl,en',
        '--default-locale' => 'pl',
        '--admin-email' => 'owner@example.test',
        '--admin-name' => 'Owner',
        '--no-interaction' => true,
        ...$overrides,
    ];
}

/**
 * Run the command and return its exit code and output.
 *
 * @return array{0: int, 1: string}
 */
function runInit(array $overrides = []): array
{
    $exitCode = Artisan::call('app:init-project', initOptions($overrides));

    return [$exitCode, Artisan::output()];
}

/**
 * Seed the sample account and sample content like `DatabaseSeeder` does locally.
 */
function seedDemo(): void
{
    User::factory()->admin()->create(['name' => DemoContent::USER_NAME, 'email' => DemoContent::USER_EMAIL]);
    test()->seed([PageSeeder::class, ArticleSeeder::class]);
}

/**
 * @return array{users: int, audit: int, pages: int, articles: int}
 */
function recordCounts(): array
{
    return [
        'users' => User::query()->count(),
        'audit' => AuditLog::query()->count(),
        'pages' => Page::query()->count(),
        'articles' => Article::query()->count(),
    ];
}

beforeEach(function () {
    Notification::fake();
    Mail::fake();

    $this->envPath = tempnam(sys_get_temp_dir(), 'init-env-');
    file_put_contents($this->envPath, INIT_ENV);
    $this->app->instance(EnvFileEditor::class, new EnvFileEditor($this->envPath));

    $this->siteName = new class implements UpdatesSiteName
    {
        public ?string $stored = null;

        /** @var list<string> */
        public array $calls = [];

        public function isAvailable(): bool
        {
            return true;
        }

        public function currentSiteName(): ?string
        {
            return $this->stored;
        }

        public function updateSiteName(string $name): bool
        {
            $this->calls[] = $name;
            $changed = $this->stored !== $name;
            $this->stored = $name;

            return $changed;
        }
    };
    $this->app->instance(UpdatesSiteName::class, $this->siteName);
});

afterEach(function () {
    foreach ([$this->envPath, ...glob($this->envPath.'.backup-*')] as $path) {
        @unlink($path);
    }
});

test('a dry run shows the plan without any effect', function () {
    Queue::fake();
    seedDemo();
    $before = recordCounts();

    [$exitCode, $output] = runInit(['--dry-run' => true, '--remove-demo' => true, '--write-env' => true, '--force' => true]);

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Planned change')->toContain('Dry run')->toContain('delete 2')
        ->and(recordCounts())->toBe($before)
        ->and(file_get_contents($this->envPath))->toBe(INIT_ENV)
        ->and(glob($this->envPath.'.backup-*'))->toBe([])
        ->and($this->siteName->calls)->toBe([]);
    Notification::assertNothingSent();
    Mail::assertNothingSent();
    Queue::assertNothingPushed();
});

test('a run creates the administrator with a queued invitation and never prints a secret', function () {
    Log::spy();

    [$exitCode, $output] = runInit();

    $administrator = User::query()->where('email', 'owner@example.test')->sole();

    expect($exitCode)->toBe(0)
        ->and($administrator->role)->toBe(UserRole::Admin)
        ->and($administrator->name)->toBe('Owner')
        ->and($output)->toContain('Invitation queued for owner@example.test')
        ->and($output)->not->toContain('reset-password')->not->toContain('token')->not->toContain('password')
        ->and($this->siteName->calls)->toBe(['Acme'])
        ->and(AuditLog::query()->where('subject_id', $administrator->id)->value('actor_id'))->toBeNull();
    Notification::assertSentTo($administrator, AccountInvitation::class);
    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('debug');
    Log::shouldNotHaveReceived('warning');
    Log::shouldNotHaveReceived('error');
});

test('a second run skips every step', function () {
    seedDemo();
    $this->app['env'] = 'local';
    runInit(['--remove-demo' => true, '--write-env' => true, '--force' => true]);
    $before = recordCounts();
    $backups = glob($this->envPath.'.backup-*');
    $env = file_get_contents($this->envPath);

    [$exitCode, $output] = runInit(['--remove-demo' => true, '--write-env' => true, '--force' => true]);

    expect($exitCode)->toBe(0)
        ->and(recordCounts())->toBe($before)
        ->and(glob($this->envPath.'.backup-*'))->toBe($backups)
        ->and(file_get_contents($this->envPath))->toBe($env)
        ->and($output)->not->toContain('update')->not->toContain('create 1')->not->toContain('delete');
    Notification::assertSentTimes(AccountInvitation::class, 1);
});

test('an existing administrator is kept and no account is created', function () {
    User::factory()->admin()->create(['email' => 'boss@example.test']);
    $before = recordCounts();

    [$exitCode, $output] = runInit();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('already exists')
        ->and(recordCounts())->toBe($before)
        ->and(User::query()->where('email', 'owner@example.test')->exists())->toBeFalse();
    Notification::assertNothingSent();
});

test('an e-mail of an existing non-administrator is refused without changes', function () {
    $editor = User::factory()->editor()->create(['email' => 'owner@example.test']);
    $before = recordCounts();

    [$exitCode, $output] = runInit(['--write-env' => true, '--force' => true]);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('app:user-role')
        ->and($editor->refresh()->role)->toBe(UserRole::Editor)
        ->and(recordCounts())->toBe($before)
        ->and(file_get_contents($this->envPath))->toBe(INIT_ENV)
        ->and($this->siteName->calls)->toBe([]);
    Notification::assertNothingSent();
});

test('without --write-env the language lines are only printed', function () {
    $this->app['env'] = 'local';

    [, $output] = runInit();

    expect($output)->toContain("APP_PUBLIC_LOCALES=pl,en\nAPP_PUBLIC_DEFAULT=pl\nAPP_PUBLIC_FALLBACK=pl")
        ->and(file_get_contents($this->envPath))->toBe(INIT_ENV)
        ->and(glob($this->envPath.'.backup-*'))->toBe([]);
});

test('declining the confirmation leaves .env untouched', function () {
    $this->app['env'] = 'local';

    $this->artisan('app:init-project', [...initOptions(['--write-env' => true]), '--no-interaction' => false])
        ->expectsConfirmation('Write APP_PUBLIC_LOCALES, APP_PUBLIC_DEFAULT, APP_PUBLIC_FALLBACK to .env?', 'no')
        ->assertSuccessful();

    expect(file_get_contents($this->envPath))->toBe(INIT_ENV)
        ->and(glob($this->envPath.'.backup-*'))->toBe([]);
});

test('a confirmed write backs up .env and changes only the language keys', function () {
    $this->app['env'] = 'local';

    $this->artisan('app:init-project', [...initOptions(['--write-env' => true]), '--no-interaction' => false])
        ->expectsConfirmation('Write APP_PUBLIC_LOCALES, APP_PUBLIC_DEFAULT, APP_PUBLIC_FALLBACK to .env?', 'yes')
        ->expectsOutputToContain('make restart')
        ->assertSuccessful();

    $backups = glob($this->envPath.'.backup-*');

    expect($backups)->toHaveCount(1)
        ->and(file_get_contents($backups[0]))->toBe(INIT_ENV)
        ->and(fileperms($backups[0]) & 0777)->toBe(0600)
        ->and(file_get_contents($this->envPath))->toBe(
            "APP_NAME=Laravel\n# keep this comment\nAPP_PUBLIC_LOCALES=pl,en\nAPP_ENV=local\nAPP_PUBLIC_DEFAULT=pl\nAPP_PUBLIC_FALLBACK=pl\n",
        );
});

test('.env is not written outside the local environment', function () {
    [, $output] = runInit(['--write-env' => true, '--force' => true]);

    expect($output)->toContain('only in the local environment')
        ->and(file_get_contents($this->envPath))->toBe(INIT_ENV);
});

test('the command refuses to run in production', function () {
    $this->app['env'] = 'production';

    [$exitCode, $output] = runInit(['--write-env' => true, '--force' => true]);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('only in the local or testing environment')
        ->and(User::query()->count())->toBe(0)
        ->and(file_get_contents($this->envPath))->toBe(INIT_ENV)
        ->and($this->siteName->calls)->toBe([]);
    Notification::assertNothingSent();
});

test('invalid input is rejected without effects', function (array $overrides, string $message) {
    $this->app['env'] = 'local';

    [$exitCode, $output] = runInit([...$overrides, '--write-env' => true, '--force' => true]);

    expect($exitCode)->toBe(2)
        ->and($output)->toContain($message)
        ->and(User::query()->count())->toBe(0)
        ->and(file_get_contents($this->envPath))->toBe(INIT_ENV)
        ->and($this->siteName->calls)->toBe([]);
    Notification::assertNothingSent();
})->with([
    'language outside the registry' => [['--locales' => 'pl,xx'], 'xx is not in the localization registry'],
    'default outside the chosen languages' => [['--default-locale' => 'de'], 'default language must be one of'],
    'unknown accent' => [['--accent' => 'purple'], 'accent'],
    'invalid e-mail' => [['--admin-email' => 'not-an-email'], 'admin email'],
    'missing value without interaction' => [['--name' => null], 'Missing required values: --name'],
    'missing administrator without an administrator' => [['--admin-email' => null], 'Missing required values: --admin-email'],
]);

test('an existing administrator makes the administrator options optional', function () {
    User::factory()->admin()->create(['email' => 'boss@example.test']);
    $before = recordCounts();

    [$exitCode, $output] = runInit(['--admin-email' => null, '--admin-name' => null]);

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('already exists')
        ->and(recordCounts())->toBe($before);
    Notification::assertNothingSent();
});

test('a given administrator e-mail is validated even when an administrator exists', function () {
    User::factory()->admin()->create(['email' => 'boss@example.test']);

    [$exitCode] = runInit(['--admin-email' => 'not-an-email']);

    expect($exitCode)->toBe(2)
        ->and($this->siteName->calls)->toBe([]);
});

test('a backup never overwrites an earlier backup from the same second', function () {
    $this->freezeTime();
    $editor = new EnvFileEditor($this->envPath);

    $first = $editor->backup();
    $editor->write(['APP_PUBLIC_DEFAULT' => 'pl']);
    $second = $editor->backup();

    expect($second)->not->toBe($first)
        ->and(file_get_contents($first))->toBe(INIT_ENV)
        ->and(file_get_contents($second))->toBe(INIT_ENV.'APP_PUBLIC_DEFAULT=pl'."\n")
        ->and(fileperms($second) & 0777)->toBe(0600);
});

test('--remove-demo removes only unedited registry records and then the sample account', function () {
    seedDemo();
    $ownPage = Page::factory()->published()->create();
    $editedTranslation = PageTranslation::query()->where('slug', DemoContent::PAGES['upcoming']['en'])->sole();
    $this->travel(1)->minutes();
    $editedTranslation->update(['title' => 'Edited offer']);

    [$exitCode, $output] = runInit(['--remove-demo' => true]);

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('edited after seeding — kept')
        ->and(Page::query()->pluck('id')->sort()->values()->all())->toBe(collect([$ownPage->id, $editedTranslation->page_id])->sort()->values()->all())
        ->and(Article::query()->count())->toBe(0)
        ->and(User::query()->where('email', DemoContent::USER_EMAIL)->exists())->toBeFalse()
        ->and(User::query()->where('email', 'owner@example.test')->value('role'))->toBe(UserRole::Admin);
});

test('without the settings module the brand step is skipped', function () {
    $this->app->forgetInstance(UpdatesSiteName::class);

    [$exitCode, $output] = runInit();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('settings module not available');
});

test('missing values are asked for interactively', function () {
    $this->artisan('app:init-project', ['--locales' => 'en', '--default-locale' => 'en'])
        ->expectsQuestion('Site name', 'Acme')
        ->expectsQuestion('Administrator e-mail', 'owner@example.test')
        ->expectsQuestion('Administrator name', 'Owner')
        ->expectsOutputToContain('Invitation queued for owner@example.test')
        ->assertSuccessful();

    expect($this->siteName->calls)->toBe(['Acme']);
});

test('a user page that shares one sample slug is kept', function () {
    $page = Page::factory()->create();
    PageTranslation::factory()->published()->for($page)->create(['locale' => 'en', 'slug' => DemoContent::PAGES['privacy']['en']]);

    runInit(['--remove-demo' => true]);

    expect(Page::query()->whereKey($page->id)->exists())->toBeTrue();
});
