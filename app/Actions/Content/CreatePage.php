<?php

namespace App\Actions\Content;

use App\Data\Content\PageTranslationInputData;
use App\Enums\PublicationStatus;
use App\Models\Page;
use App\Models\User;
use App\Services\Content\RichTextRenderer;
use Illuminate\Support\Facades\DB;

/**
 * Create a page with its initial translations in one transaction.
 */
class CreatePage
{
    public function __construct(private readonly RichTextRenderer $richText) {}

    /**
     * @param  array<string, PageTranslationInputData>  $translations  Keyed by locale.
     */
    public function handle(User $actor, array $translations): Page
    {
        return DB::transaction(function () use ($actor, $translations): Page {
            $page = Page::query()->create([
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            foreach ($translations as $locale => $input) {
                $page->translations()->create([
                    'locale' => $locale,
                    'title' => $input->title,
                    'slug' => $input->slug,
                    'meta_description' => $input->metaDescription,
                    'body' => $input->body === null ? null : $this->richText->sanitize($input->body),
                    'status' => $input->status,
                    'published_at' => $input->status === PublicationStatus::Published ? now() : null,
                ]);
            }

            return $page;
        });
    }
}
