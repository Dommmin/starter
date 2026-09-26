<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAdminLocaleRequest;
use Illuminate\Http\RedirectResponse;

class AdminLocaleController extends Controller
{
    /**
     * Update the admin locale preference in profile (if authenticated) and session.
     */
    public function update(UpdateAdminLocaleRequest $request): RedirectResponse
    {
        $locale = $request->validated('locale');

        if ($user = $request->user()) {
            $user->update(['admin_locale' => $locale]);
        }

        $request->session()->put('admin_locale', $locale);

        return back();
    }
}
