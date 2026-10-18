<?php

namespace App\Repositories\Content;

use App\Contracts\Search\AdminSearchSource;
use App\Data\Admin\Search\AdminSearchResultData;
use App\Models\Faq;
use App\Support\Search\LikeSearch;

/**
 * Named read queries of the Content module for faqs.
 */
class FaqRepository implements AdminSearchSource
{
    public function model(): string
    {
        return Faq::class;
    }

    /**
     * Faqs whose question contains the term.
     */
    public function search(string $term, int $limit): array
    {
        $query = Faq::query()->orderByDesc('updated_at')->orderByDesc('id');
        LikeSearch::apply($query, ['question'], $term);

        $results = [];
        foreach ($query->limit($limit)->get() as $faq) {
            $results[] = new AdminSearchResultData(
                type: 'faqs',
                id: $faq->id,
                label: $faq->question,
                description: $faq->locale === null ? null : mb_strtoupper($faq->locale),
                url: route('admin.faqs.edit', $faq),
            );
        }

        return $results;
    }
}
