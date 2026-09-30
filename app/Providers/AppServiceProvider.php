<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\MediaAsset;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Observers\NavigationCacheObserver;
use App\Observers\PageMenuAnchorObserver;
use App\Repositories\Settings\SiteSettingsRepository;
use App\Services\Localization\LocalizationConfig;
use App\Services\Localization\LocalizationManager;
use App\Services\Localization\LocalizedUrlGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LocalizationConfig::class, function () {
            $config = new LocalizationConfig;
            $config->validate();

            return $config;
        });

        $this->app->singleton(LocalizationManager::class, function ($app) {
            return new LocalizationManager($app->make(LocalizationConfig::class));
        });

        $this->app->singleton(LocalizedUrlGenerator::class, function ($app) {
            return new LocalizedUrlGenerator(
                $app->make(LocalizationConfig::class),
                $app->make(LocalizationManager::class),
            );
        });

        // Dev-only type generation (spatie/laravel-typescript-transformer is require-dev).
        if (class_exists(TypeScriptTransformerApplicationServiceProvider::class)) {
            $this->app->register(TypeScriptTransformerServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();

        Event::listen(Logout::class, function (): void {
            if (session()->isStarted()) {
                session()->forget('admin_locale');
            }
        });

        $this->forgetSiteSettingsWhenMediaChanges();
        $this->configureNavigationCache();
    }

    /**
     * The cached site settings embed resolved logo and og:image URLs, so a
     * deleted, rescanned or re-rendered asset must rebuild them.
     */
    protected function forgetSiteSettingsWhenMediaChanges(): void
    {
        $forget = function (): void {
            DB::afterCommit(fn () => app(SiteSettingsRepository::class)->forget());
        };

        MediaAsset::updated($forget);
        MediaAsset::deleted($forget);
    }

    /**
     * Cached public menus depend on menu items and on the titles, slugs
     * and publication of their page/article targets. Anchors on a page are
     * deleted with that page.
     */
    protected function configureNavigationCache(): void
    {
        foreach ([MenuItem::class, Page::class, PageTranslation::class, Article::class, ArticleTranslation::class] as $model) {
            $model::observe(NavigationCacheObserver::class);
        }

        Page::observe(PageMenuAnchorObserver::class);
    }

    /**
     * Public contact form limits, counted per hour in the cache only (the
     * limiter hashes its keys; no IP address or email is persisted), and the
     * per-IP limit of the stateless readiness probe.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('contact', function (Request $request): array {
            $email = $request->input('email');
            $email = is_string($email) ? Str::lower(trim($email)) : '';

            return [
                Limit::perHour((int) config('contact.rate_limits.per_ip_per_hour'))
                    ->by('contact-ip|'.$request->ip()),
                Limit::perHour((int) config('contact.rate_limits.per_email_per_hour'))
                    ->by('contact-email|'.hash('sha256', $email)),
            ];
        });

        RateLimiter::for('health', fn (Request $request): Limit => Limit::perMinute(
            max(1, (int) config('ops.health.rate_limit_per_minute')),
        )->by('health|'.$request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::shouldBeStrict(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): Password => Password::min(8)
            ->max(128)
            ->mixedCase()
            ->letters()
            ->numbers()
            ->symbols()
            ->when(
                app()->isProduction(),
                fn (Password $rule): Password => $rule->uncompromised(),
            ),
        );
    }
}
