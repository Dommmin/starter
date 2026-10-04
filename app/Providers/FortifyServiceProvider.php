<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\RedirectIfTwoFactorAuthenticatable;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\AuthenticatedResponse;
use App\Services\Localization\LocalizedUrlGenerator;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RedirectsIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(
            RedirectsIfTwoFactorAuthenticatable::class,
            RedirectIfTwoFactorAuthenticatable::class,
        );

        foreach ([LoginResponse::class, RegisterResponse::class, TwoFactorLoginResponse::class] as $contract) {
            $this->app->singleton($contract, AuthenticatedResponse::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configureEmails();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }

    /**
     * Configure localized authentication emails.
     */
    private function configureEmails(): void
    {
        VerifyEmail::toMailUsing(function ($notifiable, string $url) {
            $locale = ($notifiable instanceof HasLocalePreference ? $notifiable->preferredLocale() : null) ?: app()->getLocale();

            return (new MailMessage)
                ->subject(__('auth.emails.verify_email.subject', [], $locale))
                ->line(__('auth.emails.verify_email.line_1', [], $locale))
                ->action(__('auth.emails.verify_email.action', [], $locale), $url)
                ->line(__('auth.emails.verify_email.line_2', [], $locale));
        });

        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $locale = ($notifiable instanceof HasLocalePreference ? $notifiable->preferredLocale() : null) ?: app()->getLocale();
            /** @var LocalizedUrlGenerator $urlGenerator */
            $urlGenerator = app(LocalizedUrlGenerator::class);
            $url = $urlGenerator->url('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], $locale);

            return (new MailMessage)
                ->subject(__('auth.emails.reset_password.subject', [], $locale))
                ->line(__('auth.emails.reset_password.line_1', [], $locale))
                ->action(__('auth.emails.reset_password.action', [], $locale), $url)
                ->line(__('auth.emails.reset_password.line_2', [
                    'count' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
                ], $locale))
                ->line(__('auth.emails.reset_password.line_3', [], $locale));
        });
    }
}
