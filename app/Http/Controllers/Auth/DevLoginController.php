<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DevLoginController extends Controller
{
    /**
     * Sign in as the first administrator without a password, so local
     * testing of the panel takes one click.
     *
     * The route is registered in the `local` environment only (see
     * routes/dev-login.php); the environment check here is a second guard
     * in case the route file is ever loaded elsewhere.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(app()->environment('local'), 404);

        $administrator = User::query()
            ->where('role', UserRole::Admin)
            ->orderBy('id')
            ->first();

        abort_if($administrator === null, 404);

        Auth::login($administrator);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.index'));
    }
}
