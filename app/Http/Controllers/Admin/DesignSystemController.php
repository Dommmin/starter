<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DesignSystemController extends Controller
{
    /**
     * Display the local design-system showcase: every registered component
     * family in all of its states, with synthetic demo data only.
     *
     * The route is registered in the `local` environment only (see
     * routes/design-system.php); the environment check here is a second
     * guard in case the route file is ever loaded elsewhere.
     */
    public function __invoke(): Response
    {
        abort_unless(app()->environment('local'), 404);

        return Inertia::render('admin/design-system/index');
    }
}
