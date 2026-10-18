<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Database\Seeders\DemoContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoLoginController extends Controller
{
    /**
     * Sign in as the sample administrator of the public demo. The route is
     * registered only when `demo.enabled` is on (routes/demo.php); the check
     * here is a second guard. Every change is refused there anyway
     * (BlockChangesInDemo).
     */
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless((bool) config('demo.enabled'), 404);

        $administrator = User::query()->where('email', DemoContent::USER_EMAIL)->first();
        abort_if($administrator === null, 404);

        Auth::login($administrator);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.index'));
    }
}
