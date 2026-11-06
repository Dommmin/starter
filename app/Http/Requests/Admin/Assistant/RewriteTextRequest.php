<?php

namespace App\Http\Requests\Admin\Assistant;

use App\Enums\AssistantFeature;
use App\Enums\TextRewriteMode;
use App\Models\Article;
use App\Models\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Unsaved plain text of a page or article field to rewrite in one of the
 * fixed modes. 404 while the feature is unavailable; only users who may
 * create that content type may ask.
 */
class RewriteTextRequest extends FormRequest
{
    /**
     * Upper limit of the input and of the rewritten text in characters.
     */
    public const int TEXT_MAX = 5_000;

    /**
     * Lower limit of the requested result length.
     */
    public const int MAX_LENGTH_MIN = 20;

    /**
     * @var array<string, class-string<Model>>
     */
    private const array SUBJECTS = [
        'article' => Article::class,
        'page' => Page::class,
    ];

    public function authorize(): bool
    {
        abort_unless(AssistantFeature::TextRewrite->isAvailable(), 404);

        $subject = self::SUBJECTS[$this->string('subject')->toString()] ?? null;

        return $subject !== null && $this->user()?->can('create', $subject) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', Rule::in(array_keys(self::SUBJECTS))],
            'mode' => ['required', 'string', Rule::enum(TextRewriteMode::class)],
            'text' => ['required', 'string', 'max:'.self::TEXT_MAX],
            'max_length' => ['nullable', 'integer', 'min:'.self::MAX_LENGTH_MIN, 'max:'.self::TEXT_MAX],
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

    public function mode(): TextRewriteMode
    {
        return TextRewriteMode::from($this->string('mode')->toString());
    }

    /**
     * Length limit of the rewritten text: the field limit sent by the client or the input cap.
     */
    public function maxLength(): int
    {
        return $this->filled('max_length') ? $this->integer('max_length') : self::TEXT_MAX;
    }

    public function prompt(): string
    {
        return "Text:\n{$this->string('text')->trim()}";
    }
}
