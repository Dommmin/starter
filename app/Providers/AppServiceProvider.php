<?php

namespace App\Providers;

use App\Services\Localization\LocalizationConfig;
use App\Services\Localization\LocalizationManager;
use App\Services\Localization\LocalizedUrlGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
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

        Event::listen(Logout::class, function (): void {
            if (session()->isStarted()) {
                session()->forget('admin_locale');
            }
        });
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
