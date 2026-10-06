<?php

namespace App\Http\Responses;

use App\Services\Localization\LocalizedUrlGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where a user lands after signing in, registering or passing the two-factor
 * challenge: back on the page that required authentication (`intended`), and
 * otherwise on the home page of the current public language.
 */
class AuthenticatedResponse implements LoginResponse, RegisterResponse, TwoFactorLoginResponse
{
    public function __construct(private readonly LocalizedUrlGenerator $urls) {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        return redirect()->intended($this->urls->url('home', [], app()->getLocale()));
    }
}
