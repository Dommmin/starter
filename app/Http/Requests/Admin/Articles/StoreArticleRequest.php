<?php

namespace App\Http\Requests\Admin\Articles;

use App\Data\Content\ArticleTranslationInputData;
use App\Models\Article;
use Illuminate\Foundation\Http\FormRequest;

class StoreArticleRequest extends FormRequest
{
    /**
     * The route checks `create`; publishing any translation additionally
     * requires the `publish` ability.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->can('create', Article::class)) {
            return false;
        }

        return ! $this->inputRules()->requestsPublication($this)
            || $user->can('publish', Article::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->inputRules()->rules($this);
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

    private function inputRules(): ArticleInputRules
    {
        return app(ArticleInputRules::class);
    }
}
