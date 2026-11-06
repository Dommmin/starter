<?php

namespace App\Http\Controllers\Admin\Assistant;

use App\Actions\Assistant\SuggestMediaAltText;
use App\Data\Admin\Assistant\MediaAltTextSuggestionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Assistant\SuggestMediaAltTextRequest;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * JSON suggestion of the alt text of a clean image; nothing is saved.
 * Assets that are not clean images yield 404 without a provider call.
 */
class MediaAltTextSuggestionController extends Controller
{
    public function __invoke(SuggestMediaAltTextRequest $request, MediaAsset $mediaAsset, SuggestMediaAltText $suggest): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $alt = $suggest->handle($mediaAsset, $user);

        abort_if($alt === null, 404);

        return response()->json(new MediaAltTextSuggestionData(alt: $alt));
    }
}
