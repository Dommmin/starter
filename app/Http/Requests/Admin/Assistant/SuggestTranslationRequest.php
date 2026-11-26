<?php

namespace App\Http\Requests\Admin\Assistant;

use App\Enums\AssistantFeature;
use App\Models\Article;
use App\Models\Page;
use App\Rules\RichTextDocument;
use App\Services\Content\RichText\TextSegments;
use App\Services\Content\RichTextRenderer;
use App\Services\Localization\LocalizationConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Unsaved source language version of a page or article to translate into
 * another site language. 404 while the feature is unavailable; only users
 * who may create that content type may ask.
 *
 * The translation runs synchronously within the assistant timeout, so the
 * text to translate is capped at {@see self::TEXT_MAX} characters.
 */
class SuggestTranslationRequest extends FormRequest
{
    /**
     * Characters of all translated texts together (title, excerpt, meta
     * description and body text). The answer is about as long as the input,
     * and longer texts would not fit the request timeout of the cheap model.
     */
    public const int TEXT_MAX = 10_000;

    /**
     * @var array<string, class-string<Model>>
     */
    private const array SUBJECTS = [
        'article' => Article::class,
        'page' => Page::class,
    ];

    public function authorize(): bool
    {
        abort_unless(AssistantFeature::Translation->isAvailable(), 404);

        $subject = self::SUBJECTS[$this->string('subject')->toString()] ?? null;

        return $subject !== null && $this->user()?->can('create', $subject) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(LocalizationConfig $localization, RichTextRenderer $renderer): array
    {
        $locales = $localization->getPublicLocales();

        return [
            'subject' => ['required', 'string', Rule::in(array_keys(self::SUBJECTS))],
            'source_locale' => ['required', 'string', Rule::in($locales)],
            'target_locale' => ['required', 'string', Rule::in($locales), 'different:source_locale'],
            'title' => ['required', 'string', 'max:200'],
            'excerpt' => ['exclude_unless:subject,article', 'nullable', 'string', 'max:500'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'body' => ['nullable', 'array', new RichTextDocument($renderer)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => __('admin.assistant.translation.missingSourceTitle'),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $body = $this->body();
                $texts = [...array_values($this->texts()), ...($body === null ? [] : app(TextSegments::class)->extract($body))];
                $length = array_sum(array_map(mb_strlen(...), $texts));

                if ($length > self::TEXT_MAX) {
                    $validator->errors()->add('source', __('admin.assistant.translation.tooLong', [
                        'max' => self::TEXT_MAX,
                    ]));
                }
            },
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

    /**
     * Non-empty plain text fields of the source, keyed by request field.
     *
     * @return array<string, string>
     */
    public function texts(): array
    {
        $fields = $this->string('subject')->toString() === 'article'
            ? ['title', 'excerpt', 'meta_description']
            : ['title', 'meta_description'];

        $texts = [];

        foreach ($fields as $field) {
            $value = $this->string($field)->trim()->toString();

            if ($value !== '') {
                $texts[$field] = $value;
            }
        }

        return $texts;
    }

    /**
     * Source body document, or null when it has no text to translate.
     *
     * @return array<string, mixed>|null
     */
    public function body(): ?array
    {
        $body = $this->input('body');

        if (! is_array($body)) {
            return null;
        }

        /** @var array<string, mixed> $body */
        $segments = app(TextSegments::class)->extract($body);

        return array_filter($segments, fn (string $text): bool => trim($text) !== '') === [] ? null : $body;
    }
}
