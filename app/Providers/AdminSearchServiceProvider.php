<?php

namespace App\Providers;

use App\Actions\Admin\SearchAdminRecords;
use App\Contracts\Search\AdminSearchSource;
use App\Repositories\Content\ArticleRepository;
use App\Repositories\Content\FaqRepository;
use App\Repositories\Content\PageRepository;
use App\Repositories\Content\TagRepository;
use App\Repositories\Media\MediaAssetRepository;
use App\Repositories\Users\UserRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Sources of the admin command palette search, in the order of their
 * groups. A new module adds its repository (implementing the contract) here.
 */
class AdminSearchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->tag([
            PageRepository::class,
            ArticleRepository::class,
            TagRepository::class,
            FaqRepository::class,
            MediaAssetRepository::class,
            UserRepository::class,
        ], AdminSearchSource::class);

        $this->app->bind(SearchAdminRecords::class, fn (Application $app): SearchAdminRecords => new SearchAdminRecords(
            $app->tagged(AdminSearchSource::class),
        ));
    }
}
