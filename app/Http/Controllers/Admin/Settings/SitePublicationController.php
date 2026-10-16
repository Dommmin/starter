<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Actions\Settings\BuildSiteLaunchChecklist;
use App\Actions\Settings\UpdateSiteSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateSitePublicationRequest;
use App\Mail\TestMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Throwable;

/**
 * Launch tools of the System page (administrators only, `can` middleware
 * with SiteSettingPolicy): publication switches and the test e-mail.
 */
class SitePublicationController extends Controller
{
    /**
     * Save the coming soon and noindex switches.
     */
    public function update(UpdateSitePublicationRequest $request, UpdateSiteSettings $updateSiteSettings): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updateSiteSettings->updatePublication(
            comingSoon: $request->boolean('coming_soon'),
            noindex: $request->boolean('noindex'),
            actor: $user,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.launch.publicationSaved')]);

        return to_route('admin.system.index');
    }

    /**
     * Send a test e-mail to the signed-in administrator, synchronously, so
     * a broken mailer shows up at once. Failures are reported, never shown
     * in detail.
     */
    public function sendTestMail(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            Mail::to($user->email)->send(new TestMail);
        } catch (Throwable $exception) {
            report($exception);
            Inertia::flash('toast', ['type' => 'error', 'message' => __('admin.launch.testMailFailed')]);

            return to_route('admin.system.index');
        }

        Cache::forever(BuildSiteLaunchChecklist::TEST_MAIL_SENT_KEY, now()->toIso8601String());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.launch.testMailSent', ['email' => $user->email])]);

        return to_route('admin.system.index');
    }
}
