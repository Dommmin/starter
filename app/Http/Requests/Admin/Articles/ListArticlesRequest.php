<?php

namespace App\Http\Requests\Admin\Articles;

use App\Enums\PublicationStatus;
use App\Services\Localization\LocalizationConfig;
use App\Support\Listing\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Foundation\Http\FormRequest;

class ListArticlesRequest extends FormRequest
{
    /**
     * Authorization is enforced on the route (`can:viewAny,App\Models\Article`);
     * this request only validates input.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Single source of the article list contract. Every row is described by the
     * translation in the selected content `locale` (default: the admin
     * language when it is a public locale, otherwise the default public
     * locale); search, status filter and title sort apply to that translation.
     */
    public function listQuery(): ListQuery
    {
        $config = app(LocalizationConfig::class);

        return ListQuery::make()
            ->searchable('article_translations.title', 'article_translations.slug')
            ->sortable(
                ['title', 'updated_at'],
                default: 'updated_at',
                defaultDirection: 'desc',
                columnMap: ['title' => 'article_translations.title'],
            )
            ->filter('locale', $config->getPublicLocales(), default: $this->defaultContentLocale(), apply: function (Builder $query, string $locale): void {
                $query->leftJoin('article_translations', function (JoinClause $join) use ($locale): void {
                    $join->on('article_translations.article_id', '=', 'articles.id')
                        ->where('article_translations.locale', '=', $locale);
                });
            })
            ->filter('status', ['all', 'draft', 'published'], default: 'all', apply: function (Builder $query, string $value): void {
                match ($value) {
                    'draft' => $query->where('article_translations.status', PublicationStatus::Draft->value),
                    'published' => $query->where('article_translations.status', PublicationStatus::Published->value),
                    default => null,
                };
            })
            ->perPage(15);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->listQuery()->rules();
    }

    public function defaultContentLocale(): string
    {
        $config = app(LocalizationConfig::class);
        $adminLocale = app()->getLocale();

        return $config->isPublicLocale($adminLocale) ? $adminLocale : $config->getPublicDefault();
    }
}
