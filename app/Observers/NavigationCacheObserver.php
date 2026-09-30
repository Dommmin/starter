<?php

namespace App\Observers;

use App\Services\Navigation\PublicNavigation;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Drops the cached public menus whenever a menu item or a possible menu
 * target (page, article and their translations) is saved or deleted. Runs
 * after the surrounding transaction commits, so a rolled back change never
 * clears the cache and a concurrent request cannot re-cache stale data from
 * before the commit.
 */
class NavigationCacheObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly PublicNavigation $navigation) {}

    public function saved(): void
    {
        $this->navigation->forget();
    }

    public function deleted(): void
    {
        $this->navigation->forget();
    }
}
