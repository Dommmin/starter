<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

/**
 * Horizon dashboard access. Long-wait alerts are handled by
 * App\Listeners\ReportLongQueueWait (log + optional synchronous mail), so
 * Horizon's own queued notification routes stay unconfigured.
 */
class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Only the gate decides, in every environment. Horizon's default also
     * lets guests in locally; here local behaves like the rest of the panel
     * (every signed-in user is an administrator there, see User::isAdmin()).
     */
    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(fn (): bool => Gate::allows('viewHorizon'));
    }

    /**
     * Register the Horizon gate: administrators only.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (User $user): bool => $user->isAdmin());
    }
}
