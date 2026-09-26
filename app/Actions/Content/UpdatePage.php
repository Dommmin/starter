<?php

namespace App\Actions\Content;

use App\Data\Content\PageTranslationInputData;
use App\Enums\PublicationStatus;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\User;
use App\Services\Content\RichTextRenderer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Update the translations of a page with optimistic locking.
 *
 * Active locales present in the input are created or updated; active locales
 * missing from the input lose their translation. `published_at` records the
 * first publication and is kept when a translation returns to draft.
 */
class UpdatePage
{
    public function __construct(private readonly RichTextRenderer $richText) {}

    /**
     * @param  array<string, PageTranslationInputData>  $translations  Keyed by locale.
     * @param  list<string>  $activeLocales  Locales managed by the form.
     * @param  string  $expectedUpdatedAt  Page version the form was loaded with.
     *
     * @throws ValidationException When the page changed in the meantime (`conflict`).
     */
    public function handle(Page $page, User $actor, array $translations, array $activeLocales, string $expectedUpdatedAt): Page
    {
        return DB::transaction(function () use ($page, $actor, $translations, $activeLocales, $expectedUpdatedAt): Page {
            $locked = Page::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();

            if ($locked->updated_at?->getTimestamp() !== Carbon::parse($expectedUpdatedAt)->getTimestamp()) {
                throw ValidationException::withMessages([
                    'conflict' => __('admin.pages.conflict'),
                ]);
            }

            $existing = $locked->translations()->get()->keyBy('locale');

            foreach ($activeLocales as $locale) {
                $input = $translations[$locale] ?? null;
                /** @var PageTranslation|null $translation */
                $translation = $existing->get($locale);

                if ($input === null) {
                    $translation?->delete();

                    continue;
                }

                $translation ??= $locked->translations()->make(['locale' => $locale]);

                $translation->fill([
                    'title' => $input->title,
                    'slug' => $input->slug,
                    'meta_description' => $input->metaDescription,
                    'body' => $input->body === null ? null : $this->richText->sanitize($input->body),
                    'status' => $input->status,
                ]);

                if ($input->status === PublicationStatus::Published && $translation->published_at === null) {
                    $translation->published_at = now();
                }

                $translation->save();
            }

            $locked->forceFill([
                'updated_by' => $actor->id,
                'updated_at' => $locked->freshTimestamp(),
            ])->save();

            return $locked;
        });
    }
}
