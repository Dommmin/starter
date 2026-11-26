<?php

namespace App\Http\Requests\Admin\Assistant;

use App\Enums\AssistantFeature;
use App\Models\Faq;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Source text pasted on the FAQ list and the language of the FAQ entries to
 * propose. 404 while the feature is unavailable; only users who may create
 * FAQ entries may ask.
 */
class GenerateFaqsRequest extends FormRequest
{
    public const int TEXT_MAX = 20_000;

    public function authorize(): bool
    {
        abort_unless(AssistantFeature::FaqGenerator->isAvailable(), 404);

        return $this->user()?->can('create', Faq::class) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(LocalizationConfig $localization): array
    {
        return [
            'locale' => ['required', 'string', Rule::in($localization->getPublicLocales())],
            'text' => ['required', 'string', 'max:'.self::TEXT_MAX],
        ];
    }

    public function prompt(): string
    {
        return "Text:\n{$this->string('text')->trim()}";
    }
}
