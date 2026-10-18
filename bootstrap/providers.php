<?php

use App\Providers\AdminSearchServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\SitemapServiceProvider;

return [
    AppServiceProvider::class,
    AdminSearchServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
    SitemapServiceProvider::class,
];
