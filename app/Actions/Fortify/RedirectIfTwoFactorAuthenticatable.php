<?php

namespace App\Actions\Fortify;

use App\Services\Localization\LocalizedUrlGenerator;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable as BaseRedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfTwoFactorAuthenticatable extends BaseRedirectIfTwoFactorAuthenticatable
{
    /**
     * Get the two factor authentication enabled response.
     *
     * @param  Request  $request
     * @param  mixed  $user
     */
    protected function twoFactorChallengeResponse($request, $user): Response
    {
        $request->session()->put([
            'login.id' => $user->getKey(),
            'login.remember' => $request->boolean('remember'),
        ]);

        TwoFactorAuthenticationChallenged::dispatch($user);

        if ($request->wantsJson()) {
            return response()->json(['two_factor' => true]);
        }

        $locale = app()->getLocale();
        /** @var LocalizedUrlGenerator $urlGenerator */
        $urlGenerator = app(LocalizedUrlGenerator::class);

        return redirect()->to($urlGenerator->url('two-factor.login', [], $locale));
    }
}
