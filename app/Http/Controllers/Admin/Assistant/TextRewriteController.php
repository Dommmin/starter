<?php

namespace App\Http\Controllers\Admin\Assistant;

use App\Actions\Assistant\RunAssistantPrompt;
use App\Ai\Agents\TextRewriteAgent;
use App\Data\Admin\Assistant\TextRewriteSuggestionData;
use App\Enums\AssistantFeature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Assistant\RewriteTextRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * JSON rewrite of a plain text fragment in one fixed mode; nothing is saved.
 */
class TextRewriteController extends Controller
{
    public function __invoke(RewriteTextRequest $request, RunAssistantPrompt $assistant): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $maxLength = $request->maxLength();

        $output = $assistant->handle(
            AssistantFeature::TextRewrite,
            new TextRewriteAgent($request->mode(), $maxLength),
            $request->prompt(),
            $request->subject(),
            $user,
        );

        return response()->json(new TextRewriteSuggestionData(
            text: Str::limit(trim((string) ($output['text'] ?? '')), $maxLength, ''),
        ));
    }
}
