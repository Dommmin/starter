<?php

namespace App\Console\Commands;

use App\Actions\Content\DeletePage;
use App\Actions\Users\AssignUserRole;
use App\Enums\UserRole;
use App\Models\ContactMessage;
use App\Models\Page;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\RateLimiter;

#[Signature('app:e2e-prepare {--client-host= : Host name of the E2E browser container whose contact rate limit is reset}')]
#[Description('Prepare synthetic accounts and clean data of previous browser E2E runs (local/testing only)')]
class PrepareE2eCommand extends Command
{
    private const int MIN_PASSWORD_LENGTH = 12;

    /**
     * Execute the console command.
     */
    public function handle(AssignUserRole $assignUserRole, DeletePage $deletePage): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('E2E data may be prepared only in the local or testing environment.');

            return self::FAILURE;
        }

        if (config('fortify.require_two_factor_for_admin')) {
            $this->error('The E2E suite signs in without TOTP: set ADMIN_REQUIRE_TWO_FACTOR=false in the local environment.');

            return self::FAILURE;
        }

        $password = config('e2e.password');

        if (! is_string($password) || strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $this->error(sprintf('Set E2E_PASSWORD (at least %d characters); `make e2e` generates one per run.', self::MIN_PASSWORD_LENGTH));

            return self::FAILURE;
        }

        $admin = $this->prepareUser((string) config('e2e.admin_email'), 'E2E Admin', $password);
        $editor = $this->prepareUser((string) config('e2e.editor_email'), 'E2E Editor', $password);

        $assignUserRole->handle($admin, UserRole::Admin, actor: null);
        $assignUserRole->handle($editor, UserRole::Editor, actor: null);

        $deletedPages = $this->deletePreviousPages($deletePage, $admin);
        $deletedMessages = ContactMessage::query()
            ->where('email', 'like', config('e2e.slug_prefix').'%@example.test')
            ->delete();

        $this->resetContactRateLimit();

        $this->info(sprintf(
            'E2E accounts ready (%s, %s); removed %d page(s) and %d contact message(s) of previous runs.',
            $admin->email,
            $editor->email,
            $deletedPages,
            $deletedMessages,
        ));

        return self::SUCCESS;
    }

    private function prepareUser(string $email, string $name, string $password): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        $user->forceFill([
            'name' => $name,
            'password' => $password,
            'email_verified_at' => now(),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'admin_locale' => null,
        ])->save();

        return $user;
    }

    private function deletePreviousPages(DeletePage $deletePage, User $actor): int
    {
        $pages = Page::query()
            ->whereHas('translations', fn ($query) => $query->where('slug', 'like', config('e2e.slug_prefix').'%'))
            ->get();

        foreach ($pages as $page) {
            $deletePage->handle($page, $actor);
        }

        return $pages->count();
    }

    /**
     * The contact form allows a few messages per IP and hour; repeated local
     * runs would otherwise hit the limit. Keys follow the named `contact`
     * limiter (see AppServiceProvider and ThrottleRequests).
     */
    private function resetContactRateLimit(): void
    {
        $host = $this->option('client-host');

        if (! is_string($host) || $host === '') {
            return;
        }

        $addresses = gethostbynamel($host);

        if ($addresses === false) {
            $this->warn(sprintf('Host "%s" could not be resolved; the contact rate limit was not reset.', $host));

            return;
        }

        foreach ($addresses as $address) {
            RateLimiter::clear(md5('contact'.'contact-ip|'.$address));
        }
    }
}
