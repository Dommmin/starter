<?php

namespace App\Http\Requests\Admin\Articles;

use App\Data\Content\ArticleTranslationInputData;
use App\Models\Article;
use Illuminate\Foundation\Http\FormRequest;

class UpdateArticleRequest extends FormRequest
{
    /**
     * The route checks `update`; publishing any translation additionally
     * requires the `publish` ability.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $article = $this->route('article');

        if ($user === null || ! $article instanceof Article || ! $user->can('update', $article)) {
            return false;
        }

        return ! $this->inputRules()->requestsPublication($this)
            || $user->can('publish', Article::class);
    }

    /**
     * `updated_at` is the version the form was loaded with (optimistic locking).
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var Article $article */
        $article = $this->route('article');

        return [
            'updated_at' => ['required', 'string', 'date'],
            ...$this->inputRules()->rules($this, $article->id),
        ];
    }

    public function coverMediaId(): ?int
    {
        return $this->inputRules()->coverMediaId($this->validated());
    }

    /**
     * @return array<string, ArticleTranslationInputData>
     */
    public function translations(): array
    {
        return $this->inputRules()->toInput($this->validated());
    }

    public function expectedUpdatedAt(): string
    {
        return $this->string('updated_at')->toString();
    }

    private function inputRules(): ArticleInputRules
    {
        return app(ArticleInputRules::class);
    }
}
