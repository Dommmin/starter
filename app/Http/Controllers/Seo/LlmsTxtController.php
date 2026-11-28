<?php

namespace App\Http\Controllers\Seo;

use App\Actions\Seo\BuildLlmsTxt;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class LlmsTxtController extends Controller
{
    public function __invoke(BuildLlmsTxt $buildLlmsTxt): Response
    {
        return response($buildLlmsTxt->handle(), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Cache-Control' => 'public, max-age='.(int) config('seo.cache_max_age'),
        ]);
    }
}
