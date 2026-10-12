<?php

namespace App\Console\Commands;

use App\Actions\Users\CreateUser;
use App\Actions\Users\DeleteUser;
use App\Contracts\DemoContent\DemoContentProvider;
use App\Contracts\Settings\UpdatesSiteName;
use App\Enums\AccentColor;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\Env\EnvFileEditor;
use Database\Seeders\DemoContent;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

#[Signature('app:init-project
    {--name= : Site name stored in the application settings}
    {--locales= : Comma-separated public languages, e.g. en,pl}
    {--default-locale= : Default public language (one of --locales)}
    {--enable-registration : Keep self-service sign-up on (it is turned off by default)}
    {--accent= : Accent colour: default, blue, violet, rose or green}
    {--admin-email= : E-mail address of the first administrator}
    {--admin-name= : Name of the first administrator}
    {--remove-demo : Remove unedited sample content and the sample account}
    {--write-env : Write the language keys to .env (local only, after a backup)}
    {--dry-run : Show the plan without changing anything}
    {--force : Write .env without asking for confirmation}')]
#[Description('Initialise a new project: brand, languages, accent, first administrator and sample content (local/testing only)')]
class InitProjectCommand extends Command
{
    private const string SKIP = 'skip';

    /**
     * @var list<string>
     */
    private const array LOCALE_KEYS = ['APP_PUBLIC_LOCALES', 'APP_PUBLIC_DEFAULT', 'APP_PUBLIC_FALLBACK', 'APP_REGISTRATION_ENABLED', 'APP_ACCENT'];

    /**
     * Execute the console command.
     */
    public function handle(
        UpdatesSiteName $siteName,
        EnvFileEditor $envFile,
        CreateUser $createUser,
        DeleteUser $deleteUser,
    ): int {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('A project may be initialised only in the local or testing environment.');

            return self::FAILURE;
        }

        $existingAdministrator = $this->existingAdministrator();
        $input = $this->collectInput(adminRequired: $existingAdministrator === null);

        if ($input === null) {
            return self::INVALID;
        }

        $envValues = [
            'APP_PUBLIC_LOCALES' => implode(',', $input['locales']),
            'APP_PUBLIC_DEFAULT' => $input['default_locale'],
            'APP_PUBLIC_FALLBACK' => $input['default_locale'],
            'APP_REGISTRATION_ENABLED' => $this->option('enable-registration') ? 'true' : 'false',
            'APP_ACCENT' => $input['accent']->value,
        ];

        $emailOwner = $existingAdministrator === null
            ? User::query()->where('email', $input['admin_email'])->first()
            : null;

        if ($emailOwner !== null) {
            $this->error(sprintf(
                'The e-mail address %s belongs to an existing account without the administrator role. Nothing was changed; grant the role with `php artisan app:user-role %s admin`.',
                $input['admin_email'],
                $input['admin_email'],
            ));

            return self::FAILURE;
        }

        $demo = $this->option('remove-demo') ? $this->demoPlan() : null;
        $envChanges = $envFile->exists() ? $envFile->changes($envValues) : $this->missingEnvChanges($envValues);

        $this->table(['Step', 'State', 'Planned change', 'Action'], [
            $this->brandRow($siteName, $input['name']),
            $this->localeRow($envChanges),
            ['accent', $input['accent']->value, 'APP_ACCENT (with the other .env lines)', self::SKIP],
            $existingAdministrator !== null
                ? ['admin', 'an administrator exists', 'none', self::SKIP]
                : ['admin', 'no administrator', 'create '.$input['admin_email'].' (admin) + invitation', 'create 1'],
            ...$this->demoRows($demo),
        ]);

        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing was changed.');

            return self::SUCCESS;
        }

        $this->applyBrand($siteName, $input['name']);
        $this->applyLocales($envFile, $envValues, $envChanges);
        $this->line('Accent: '.$input['accent']->value.' (APP_ACCENT, written with the other .env lines).');

        $administrator = $existingAdministrator;

        if ($administrator === null) {
            $administrator = $createUser->handle(null, (string) $input['admin_name'], (string) $input['admin_email'], UserRole::Admin);
            $this->info(sprintf('Administrator created. Invitation queued for %s.', $administrator->email));
        } else {
            $this->line('Administrator: an administrator account already exists — skipped.');
        }

        if ($demo !== null) {
            $this->removeDemo($demo, $administrator, $deleteUser);
        }

        return self::SUCCESS;
    }

    /**
     * Read, ask for and validate all input; null when the input is invalid.
     * Administrator details are required (and asked for) only when the
     * administrator step will create an account; given values are validated
     * either way.
     *
     * @return array{name: string, locales: list<string>, default_locale: string, accent: AccentColor, admin_email: string|null, admin_name: string|null}|null
     */
    private function collectInput(bool $adminRequired): ?array
    {
        $registry = array_keys((array) config('localization.registry', []));
        $interactive = $this->input->isInteractive();
        $missing = [];

        $name = $this->stringOption('name');
        if ($name === null && $interactive) {
            $name = trim((string) $this->ask('Site name'));
        }

        $locales = $this->stringOption('locales') !== null
            ? $this->splitLocales((string) $this->stringOption('locales'))
            : null;
        if ($locales === null && $interactive) {
            /** @var list<string> $chosen */
            $chosen = (array) $this->choice('Public languages (comma-separated numbers or codes)', $registry, 0, null, true);
            $locales = array_values(array_unique($chosen));
        }

        $defaultLocale = $this->stringOption('default-locale');
        if ($defaultLocale === null && $interactive && $locales !== null && $locales !== []) {
            $chosenDefault = $this->choice('Default public language', $locales, 0);
            $defaultLocale = is_string($chosenDefault) ? $chosenDefault : null;
        }

        $accent = $this->stringOption('accent') ?? AccentColor::Default->value;

        $adminEmail = $this->stringOption('admin-email');
        if ($adminEmail === null && $adminRequired && $interactive) {
            $adminEmail = trim((string) $this->ask('Administrator e-mail'));
        }

        $adminName = $this->stringOption('admin-name');
        if ($adminName === null && $adminRequired && $interactive) {
            $adminName = trim((string) $this->ask('Administrator name'));
        }

        $required = ['name' => $name, 'locales' => $locales, 'default-locale' => $defaultLocale];

        if ($adminRequired) {
            $required += ['admin-email' => $adminEmail, 'admin-name' => $adminName];
        }

        foreach ($required as $option => $value) {
            if ($value === null || $value === '' || $value === []) {
                $missing[] = '--'.$option;
            }
        }

        if ($missing !== []) {
            $this->error('Missing required values: '.implode(', ', $missing).'. Nothing was changed.');

            return null;
        }

        $validator = Validator::make([
            'name' => $name,
            'locales' => $locales,
            'default_locale' => $defaultLocale,
            'accent' => $accent,
            'admin_email' => $adminEmail,
            'admin_name' => $adminName,
        ], [
            'name' => ['required', 'string', 'max:255', 'not_regex:/[\r\n]/'],
            'locales' => ['required', 'array', 'min:1'],
            'locales.*' => ['string', Rule::in($registry)],
            'default_locale' => ['required', 'string', Rule::in((array) $locales)],
            'accent' => ['required', Rule::enum(AccentColor::class)],
            'admin_email' => [$adminRequired ? 'required' : 'nullable', 'string', 'email:rfc', 'max:255', Rule::notIn([DemoContent::USER_EMAIL])],
            'admin_name' => [$adminRequired ? 'required' : 'nullable', 'string', 'max:255'],
        ], [
            'locales.*.in' => 'The language :input is not in the localization registry ('.implode(', ', $registry).').',
            'default_locale.in' => 'The default language must be one of the chosen languages.',
            'admin_email.not_in' => 'The sample account address cannot become the administrator.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }
            $this->error('Nothing was changed.');

            return null;
        }

        /** @var list<string> $locales */
        return [
            'name' => (string) $name,
            'locales' => $locales,
            'default_locale' => (string) $defaultLocale,
            'accent' => AccentColor::from($accent),
            'admin_email' => $adminEmail !== '' ? $adminEmail : null,
            'admin_name' => $adminName !== '' ? $adminName : null,
        ];
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return list<string>
     */
    private function splitLocales(string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', $value)), fn (string $locale): bool => $locale !== '')));
    }

    /**
     * An administrator other than the seeded sample accounts.
     */
    private function existingAdministrator(): ?User
    {
        return User::query()
            ->where('role', UserRole::Admin->value)
            ->whereNotIn('email', [DemoContent::USER_EMAIL, ...array_keys(DemoUserSeeder::USERS)])
            ->orderBy('id')
            ->first();
    }

    /**
     * @return list<string>
     */
    private function brandRow(UpdatesSiteName $siteName, string $name): array
    {
        if (! $siteName->isAvailable()) {
            return ['brand', 'settings module not available', 'none', self::SKIP];
        }

        $current = $siteName->currentSiteName();

        return $current === $name
            ? ['brand', 'site name: '.$current, 'none', self::SKIP]
            : ['brand', 'site name: '.($current ?? '(none)'), 'site name → '.$name, 'update'];
    }

    /**
     * @param  array<string, array{old: string|null, new: string}>  $changes
     * @return list<string>
     */
    private function localeRow(array $changes): array
    {
        if ($changes === []) {
            return ['languages', '.env up to date', 'none', self::SKIP];
        }

        $planned = implode(', ', array_map(
            fn (string $key, array $change): string => $key.'='.$change['new'],
            array_keys($changes),
            $changes,
        ));

        return ['languages', count($changes).' key(s) differ', $planned, $this->option('write-env') ? 'update .env' : 'print lines'];
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, array{old: string|null, new: string}>
     */
    private function missingEnvChanges(array $values): array
    {
        return array_map(fn (string $value): array => ['old' => null, 'new' => $value], $values);
    }

    private function applyBrand(UpdatesSiteName $siteName, string $name): void
    {
        if (! $siteName->isAvailable()) {
            $this->line('Brand: skipped — settings module not available.');

            return;
        }

        $this->line($siteName->updateSiteName($name) ? 'Brand: site name updated.' : 'Brand: site name unchanged — skipped.');
    }

    /**
     * @param  array<string, string>  $values
     * @param  array<string, array{old: string|null, new: string}>  $changes
     */
    private function applyLocales(EnvFileEditor $envFile, array $values, array $changes): void
    {
        if ($changes === []) {
            $this->line('Languages: .env already up to date — skipped.');

            return;
        }

        if (! $this->option('write-env')) {
            $this->printEnvLines($values, 'Languages: add these lines to .env (or rerun with --write-env):');

            return;
        }

        if (! app()->environment('local')) {
            $this->printEnvLines($values, 'Languages: .env is written only in the local environment; add these lines manually:');

            return;
        }

        if (! $envFile->exists()) {
            $this->printEnvLines($values, 'Languages: no .env file found; add these lines to it:');

            return;
        }

        if (! $this->option('force') && ! $this->confirm('Write '.implode(', ', self::LOCALE_KEYS).' to .env?')) {
            $this->printEnvLines($values, 'Languages: .env not written; add these lines manually:');

            return;
        }

        $backupPath = $envFile->backup();
        $envFile->write($values);
        $this->callSilently('config:clear');

        $this->info(sprintf('Languages: .env updated (backup: %s).', basename($backupPath)));
        $this->line('Run `make restart` so the running services read the new configuration.');
    }

    /**
     * @param  array<string, string>  $values
     */
    private function printEnvLines(array $values, string $heading): void
    {
        $this->line($heading);

        foreach ($values as $key => $value) {
            $this->line($key.'='.$value);
        }
    }

    /**
     * Seeded records still present, grouped by provider, plus the sample account.
     *
     * @return array{providers: list<array{provider: DemoContentProvider, records: list<Model>}>, user: User|null}
     */
    private function demoPlan(): array
    {
        $providers = [];

        foreach (DemoContent::PROVIDERS as $providerClass) {
            $provider = app($providerClass);
            $providers[] = ['provider' => $provider, 'records' => $provider->records()];
        }

        return [
            'providers' => $providers,
            'user' => User::query()->where('email', DemoContent::USER_EMAIL)->first(),
        ];
    }

    /**
     * @param  array{providers: list<array{provider: DemoContentProvider, records: list<Model>}>, user: User|null}|null  $demo
     * @return list<list<string>>
     */
    private function demoRows(?array $demo): array
    {
        if ($demo === null) {
            return [['demo', 'not requested', 'none', self::SKIP]];
        }

        $rows = [];

        foreach ($demo['providers'] as ['provider' => $provider, 'records' => $records]) {
            $edited = count(array_filter($records, fn (Model $record): bool => $provider->isModifiedSinceSeed($record)));
            $deletable = count($records) - $edited;

            $verb = $provider->resetsInsteadOfDeleting() ? 'reset' : 'delete';

            $rows[] = [
                'demo '.$provider->label(),
                sprintf('%d seeded, %d edited', count($records), $edited),
                $deletable > 0 ? ($provider->resetsInsteadOfDeleting() ? 'reset unedited sample ' : 'remove unedited sample ').$provider->label() : 'none',
                $deletable > 0 ? $verb.' '.$deletable : self::SKIP,
            ];
        }

        $user = $demo['user'];

        $rows[] = match (true) {
            $user === null => ['demo account', 'absent', 'none', self::SKIP],
            $this->userWasModified($user) => ['demo account', 'edited after seeding', 'keep', self::SKIP],
            default => ['demo account', 'present', 'remove '.DemoContent::USER_EMAIL, 'delete 1'],
        };

        return $rows;
    }

    /**
     * @param  array{providers: list<array{provider: DemoContentProvider, records: list<Model>}>, user: User|null}  $demo
     */
    private function removeDemo(array $demo, User $actor, DeleteUser $deleteUser): void
    {
        foreach ($demo['providers'] as ['provider' => $provider, 'records' => $records]) {
            $deleted = 0;

            foreach ($records as $record) {
                if ($provider->isModifiedSinceSeed($record)) {
                    $this->warn(sprintf('Demo %s: "%s" was edited after seeding — kept.', $provider->label(), $provider->describe($record)));

                    continue;
                }

                $provider->delete($record, $actor);
                $deleted++;
            }

            $this->line(sprintf('Demo %s: %d %s.', $provider->label(), $deleted, $provider->resetsInsteadOfDeleting() ? 'reset to placeholders and hidden' : 'removed'));
        }

        $user = $demo['user'];

        if ($user === null) {
            return;
        }

        if ($this->userWasModified($user)) {
            $this->warn('Demo account: edited after seeding — kept.');

            return;
        }

        $deleteUser->handle($actor, $user);
        $this->line('Demo account: removed.');
    }

    private function userWasModified(User $user): bool
    {
        return $user->updated_at !== null
            && $user->created_at !== null
            && $user->updated_at->gt($user->created_at);
    }
}
