<?php

namespace App\Http\Requests\Admin\Pages;

use App\Enums\PublicationStatus;
use App\Services\Localization\LocalizationConfig;
use App\Support\Listing\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Foundation\Http\FormRequest;

class ListPagesRequest extends FormRequest
{
    /**
     * Authorization is enforced on the route (`can:viewAny,App\Models\Page`);
     * this request only validates input.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Single source of the page list contract. Every row is described by the
     * translation in the selected content `locale` (default: the admin
     * language when it is a public locale, otherwise the default public
     * locale); search, status filter and title sort apply to that translation.
     */
    public function listQuery(): ListQuery
    {
        $config = app(LocalizationConfig::class);

        return ListQuery::make()
            ->searchable('page_translations.title', 'page_translations.slug')
            ->sortable(
                ['title', 'updated_at'],
                default: 'updated_at',
                defaultDirection: 'desc',
                columnMap: ['title' => 'page_translations.title'],
            )
            ->filter('locale', $config->getPublicLocales(), default: $this->defaultContentLocale(), apply: function (Builder $query, string $locale): void {
                $query->leftJoin('page_translations', function (JoinClause $join) use ($locale): void {
                    $join->on('page_translations.page_id', '=', 'pages.id')
                        ->where('page_translations.locale', '=', $locale);
                });
            })
            ->filter('status', ['all', 'draft', 'published'], default: 'all', apply: function (Builder $query, string $value): void {
                match ($value) {
                    'draft' => $query->where('page_translations.status', PublicationStatus::Draft->value),
                    'published' => $query->where('page_translations.status', PublicationStatus::Published->value),
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
