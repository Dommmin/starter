<?php

namespace App\Actions\Faqs;

use App\Models\Faq;

/**
 * Create a faq from validated field values.
 */
class CreateFaq
{
    /**
     * @param  array{question: string, answer: string, locale?: string|null, position?: int|null, published: bool}  $attributes
     */
    public function handle(array $attributes): Faq
    {
        return Faq::query()->create($attributes);
    }
}
