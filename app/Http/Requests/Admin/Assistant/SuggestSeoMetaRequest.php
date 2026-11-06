<?php

namespace App\Http\Requests\Admin\Assistant;

use App\Enums\AssistantFeature;
use App\Models\Article;
use App\Models\Page;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Unsaved title and plain text of a page or article translation for an SEO
 * suggestion. 404 while the feature is unavailable; only users who may
 * create that content type may ask.
 */
class SuggestSeoMetaRequest extends FormRequest
{
    public const int TEXT_MAX = 20_000;

    /**
     * @var array<string, class-string<Model>>
     */
    private const array SUBJECTS = [
        'article' => Article::class,
        'page' => Page::class,
    ];

    public function authorize(): bool
    {
        abort_unless(AssistantFeature::SeoMeta->isAvailable(), 404);

        $subject = self::SUBJECTS[$this->string('subject')->toString()] ?? null;

        return $subject !== null && $this->user()?->can('create', $subject) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(LocalizationConfig $localization): array
    {
        return [
            'subject' => ['required', 'string', Rule::in(array_keys(self::SUBJECTS))],
            'locale' => ['required', 'string', Rule::in($localization->getPublicLocales())],
            'title' => ['required', 'string', 'max:200'],
            'text' => ['nullable', 'string', 'max:'.self::TEXT_MAX],
        ];
    }

    /**
     * Unsaved instance of the content type, the audit subject of the suggestion.
     */
    public function subject(): Model
    {
        $class = self::SUBJECTS[$this->string('subject')->toString()];

        return new $class;
    }

    public function prompt(): string
    {
        return "Title: {$this->string('title')->trim()}\n\nText:\n{$this->string('text')->trim()}";
    }
}
