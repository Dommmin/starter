<?php

namespace App\Repositories\Home;

use App\Models\Faq;
use App\Models\HomeSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Named read queries of the home page module.
 */
class HomeSectionRepository
{
    /**
     * Every section of the locale in display order (admin list).
     *
     * @return Collection<int, HomeSection>
     */
    public function forLocale(string $locale): Collection
    {
        return HomeSection::query()
            ->where('locale', $locale)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * Enabled sections of the locale in display order (public page).
     *
     * @return Collection<int, HomeSection>
     */
    public function enabledFor(string $locale): Collection
    {
        return HomeSection::query()
            ->where('locale', $locale)
            ->where('enabled', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * Published questions shown in the locale: those of the locale and those
     * of every locale (`locale` null), by position.
     *
     * @return Collection<int, Faq>
     */
    public function publishedFaqs(string $locale, ?int $limit): Collection
    {
        return Faq::query()
            ->where('published', true)
            ->where(function (Builder $query) use ($locale): void {
                $query->where('locale', $locale)->orWhereNull('locale');
            })
            ->orderByRaw('position is null')
            ->orderBy('position')
            ->orderBy('id')
            ->when($limit !== null, fn (Builder $query) => $query->limit((int) $limit))
            ->get();
    }
}
