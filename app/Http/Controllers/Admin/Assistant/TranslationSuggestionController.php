<?php

namespace App\Http\Controllers\Admin\Assistant;

use App\Actions\Assistant\Exceptions\AssistantUnavailable;
use App\Actions\Assistant\RunAssistantPrompt;
use App\Ai\Agents\TranslationAgent;
use App\Data\Admin\Assistant\TranslationSuggestionData;
use App\Enums\AssistantFeature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Assistant\SuggestTranslationRequest;
use App\Models\User;
use App\Services\Content\RichText\TextSegments;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * JSON draft of a page or article language version translated from another
 * site language; nothing is saved. The body keeps the structure of the
 * source document: only its text nodes are sent to the model and put back.
 */
class TranslationSuggestionController extends Controller
{
    /**
     * Column limits of the translation tables.
     *
     * @var array<string, int>
     */
    private const array LIMITS = ['title' => 200, 'excerpt' => 500, 'meta_description' => 320];

    public function __invoke(
        SuggestTranslationRequest $request,
        RunAssistantPrompt $assistant,
        TextSegments $segments,
        LocalizationConfig $localization,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $texts = $request->texts();
        $body = $request->body();
        $sourceSegments = $body === null ? [] : $segments->extract($body);

        $output = $assistant->handle(
            AssistantFeature::Translation,
            new TranslationAgent(
                $this->language($localization, $request->string('source_locale')->toString()),
                $this->language($localization, $request->string('target_locale')->toString()),
                array_keys($texts),
                count($sourceSegments),
            ),
            json_encode(
                [...$texts, 'body' => $sourceSegments],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ),
            $request->subject(),
            $user,
        );

        $translated = [];

        foreach (array_keys(self::LIMITS) as $field) {
            $translated[$field] = isset($texts[$field])
                ? Str::limit(trim(is_string($output[$field] ?? null) ? $output[$field] : ''), self::LIMITS[$field], '')
                : '';
        }

        if ($translated['title'] === '') {
            throw new AssistantUnavailable;
        }

        return response()->json(new TranslationSuggestionData(
            title: $translated['title'],
            excerpt: $request->string('subject')->toString() === 'article' ? $translated['excerpt'] : null,
            metaDescription: $translated['meta_description'],
            body: $body === null ? null : $this->translatedBody($segments, $body, $output['body'] ?? null),
        ));
    }

    /**
     * The source document with its text nodes replaced. An answer that does
     * not match the source fragments is rejected instead of guessed.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function translatedBody(TextSegments $segments, array $body, mixed $output): array
    {
        if (! is_array($output) || ! array_is_list($output) || array_filter($output, is_string(...)) !== $output) {
            throw new AssistantUnavailable;
        }

        try {
            /** @var list<string> $output */
            return $segments->replace($body, $output);
        } catch (InvalidArgumentException $exception) {
            throw new AssistantUnavailable(previous: $exception);
        }
    }

    private function language(LocalizationConfig $localization, string $locale): string
    {
        $name = $localization->getRegistry()[$locale]['name'] ?? $locale;

        return "{$name} ({$locale})";
    }
}
