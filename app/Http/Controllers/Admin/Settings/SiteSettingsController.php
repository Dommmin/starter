<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Actions\Settings\UpdateSiteSettings;
use App\Data\Admin\Settings\SiteSettingsEditorData;
use App\Data\Admin\Settings\SiteSettingsFormData;
use App\Data\Admin\Settings\SocialNetworkOptionData;
use App\Data\Content\ContentLocalesData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateSiteSettingsRequest;
use App\Models\User;
use App\Repositories\Settings\SiteSettingsRepository;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Site settings form (administrators only). Authorization: `can`
 * middleware with SiteSettingPolicy on both routes.
 */
class SiteSettingsController extends Controller
{
    public function __construct(
        private readonly SiteSettingsRepository $settings,
        private readonly LocalizationConfig $localization,
    ) {}

    /**
     * Show the settings form; blank (with the fallback site name) until the
     * settings are saved for the first time.
     */
    public function edit(): Response
    {
        $locales = $this->localization->getPublicLocales();
        $settings = $this->settings->forEditor();

        return Inertia::render('admin/site-settings/edit', new SiteSettingsEditorData(
            settings: $settings === null
                ? SiteSettingsFormData::blank($this->settings->siteName(), $locales)
                : SiteSettingsFormData::fromSettings($settings, $locales),
            locales: ContentLocalesData::fromConfig($this->localization),
            socialNetworks: SocialNetworkOptionData::options(),
        ));
    }

    /**
     * Save the settings; a stale `updated_at` yields a `conflict` error.
     */
    public function update(UpdateSiteSettingsRequest $request, UpdateSiteSettings $updateSiteSettings): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updateSiteSettings->handle($request->toInput(), $user, $request->expectedUpdatedAt());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.siteSettings.updated')]);

        return to_route('admin.site-settings.edit');
    }
}
