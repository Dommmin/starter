<?php

namespace App\Http\Controllers\Admin\Assistant;

use App\Actions\Assistant\RunAssistantPrompt;
use App\Ai\Agents\SeoMetaAgent;
use App\Data\Admin\Assistant\SeoMetaSuggestionData;
use App\Enums\AssistantFeature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Assistant\SuggestSeoMetaRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * JSON suggestion of the meta description and slug of a translation;
 * nothing is saved.
 */
class SeoMetaSuggestionController extends Controller
{
    public function __invoke(SuggestSeoMetaRequest $request, RunAssistantPrompt $assistant): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $locale = $request->string('locale')->toString();

        $output = $assistant->handle(
            AssistantFeature::SeoMeta,
            new SeoMetaAgent($locale),
            $request->prompt(),
            $request->subject(),
            $user,
        );

        return response()->json(new SeoMetaSuggestionData(
            metaDescription: Str::limit(trim((string) ($output['meta_description'] ?? '')), SeoMetaAgent::META_DESCRIPTION_MAX, ''),
            slug: Str::limit(Str::slug((string) ($output['slug'] ?? ''), language: $locale), SeoMetaAgent::SLUG_MAX, ''),
        ));
    }
}
