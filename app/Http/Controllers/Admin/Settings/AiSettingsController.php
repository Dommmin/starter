<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Actions\Settings\UpdateAiSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateAiSettingsRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * AI provider and API key of the System page (administrators only, `can`
 * middleware with SiteSettingPolicy and a recent password confirmation).
 */
class AiSettingsController extends Controller
{
    /**
     * Save the provider and, when given, a new API key.
     */
    public function update(UpdateAiSettingsRequest $request, UpdateAiSettings $updateAiSettings): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updateAiSettings->update($request->provider(), $request->apiKey(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.ai.saved')]);

        return to_route('admin.system.index');
    }

    /**
     * Remove the panel provider and key; the environment values apply again.
     */
    public function destroy(Request $request, UpdateAiSettings $updateAiSettings): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updateAiSettings->clear($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.ai.removed')]);

        return to_route('admin.system.index');
    }
}
