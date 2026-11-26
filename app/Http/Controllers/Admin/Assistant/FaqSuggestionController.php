<?php

namespace App\Http\Controllers\Admin\Assistant;

use App\Actions\Assistant\RunAssistantPrompt;
use App\Ai\Agents\FaqGeneratorAgent;
use App\Data\Admin\Assistant\FaqSuggestionData;
use App\Data\Admin\Assistant\FaqSuggestionItemData;
use App\Enums\AssistantFeature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Assistant\GenerateFaqsRequest;
use App\Models\Faq;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * JSON proposals of FAQ entries from a pasted text; nothing is saved. The
 * admin ticks the entries to keep and saves them through `admin.faqs.store-many`.
 */
class FaqSuggestionController extends Controller
{
    public function __invoke(GenerateFaqsRequest $request, RunAssistantPrompt $assistant): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $output = $assistant->handle(
            AssistantFeature::FaqGenerator,
            new FaqGeneratorAgent($request->string('locale')->toString()),
            $request->prompt(),
            new Faq,
            $user,
        );

        return response()->json(new FaqSuggestionData(items: $this->normalize($output['items'] ?? [])));
    }

    /**
     * Complete entries only, trimmed and cut to the column limits, at most
     * {@see FaqGeneratorAgent::MAX_ITEMS} whatever the model returned.
     *
     * @return list<FaqSuggestionItemData>
     */
    private function normalize(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $item) {
            if (! is_array($item) || ! is_string($item['question'] ?? null) || ! is_string($item['answer'] ?? null)) {
                continue;
            }

            $question = Str::limit(trim($item['question']), FaqGeneratorAgent::QUESTION_MAX, '');
            $answer = Str::limit(trim($item['answer']), FaqGeneratorAgent::ANSWER_MAX, '');

            if ($question === '' || $answer === '') {
                continue;
            }

            $normalized[] = new FaqSuggestionItemData(question: $question, answer: $answer);

            if (count($normalized) === FaqGeneratorAgent::MAX_ITEMS) {
                break;
            }
        }

        return $normalized;
    }
}
