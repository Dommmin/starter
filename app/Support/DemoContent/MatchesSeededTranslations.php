<?php

namespace App\Support\DemoContent;

use App\Models\Article;
use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared lookup of seeded translatable content (pages, articles) by the
 * exact `locale => slug` map of its translations.
 */
trait MatchesSeededTranslations
{
    /**
     * @param  Builder<Page>|Builder<Article>  $query
     * @param  array<string, array<string, string>>  $seeded
     * @return list<Page|Article>
     */
    private function matchingRecords(Builder $query, array $seeded): array
    {
        $records = [];

        foreach ($seeded as $slugs) {
            ksort($slugs);
            $firstLocale = (string) array_key_first($slugs);

            $candidates = (clone $query)
                ->with('translations')
                ->whereHas('translations', fn (Builder $translation) => $translation
                    ->where('locale', $firstLocale)
                    ->where('slug', $slugs[$firstLocale]))
                ->get();

            foreach ($candidates as $candidate) {
                $current = $candidate->translations->pluck('slug', 'locale')->all();
                ksort($current);

                if ($current === $slugs) {
                    $records[] = $candidate;
                }
            }
        }

        return $records;
    }

    /**
     * A record counts as edited when it or any translation changed after creation.
     */
    private function wasModified(Page|Article $record): bool
    {
        return collect([$record, ...$record->translations->all()])
            ->contains(fn (Model $model): bool => $model->updated_at !== null
                && $model->created_at !== null
                && $model->updated_at->gt($model->created_at));
    }

    private function slugs(Page|Article $record): string
    {
        return $record->translations->sortBy('locale')->pluck('slug')->implode(', ');
    }
}
