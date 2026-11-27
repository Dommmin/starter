<?php

namespace App\Actions\Faqs;

use App\Models\Faq;

/**
 * Create a faq from validated field values. Without a position it goes to
 * the end of the shared order of all languages.
 */
class CreateFaq
{
    /**
     * @param  array{question: string, answer: string, locale?: string|null, position?: int|null, published: bool}  $attributes
     */
    public function handle(array $attributes): Faq
    {
        if (($attributes['position'] ?? null) === null) {
            $attributes['position'] = self::nextPosition();
        }

        return Faq::query()->create($attributes);
    }

    /**
     * One past the highest position of all faqs. Concurrent creations may
     * share a position (it is not unique); lists then fall back to `id`.
     */
    public static function nextPosition(): int
    {
        $max = Faq::query()->max('position');

        return is_numeric($max) ? (int) $max + 1 : 1;
    }
}
