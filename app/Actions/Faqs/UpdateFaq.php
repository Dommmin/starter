<?php

namespace App\Actions\Faqs;

use App\Models\Faq;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Update a faq with optimistic locking on `updated_at`. A cleared position
 * moves the faq to the end of the shared order of all languages.
 */
class UpdateFaq
{
    /**
     * @param  array<string, mixed>  $attributes  Validated field values.
     * @param  string  $expectedUpdatedAt  Version the form was loaded with.
     *
     * @throws ValidationException When the record changed in the meantime (`conflict`).
     */
    public function handle(Faq $faq, array $attributes, string $expectedUpdatedAt): Faq
    {
        return DB::transaction(function () use ($faq, $attributes, $expectedUpdatedAt): Faq {
            $locked = Faq::query()->whereKey($faq->id)->lockForUpdate()->firstOrFail();

            if ($locked->updated_at?->getTimestamp() !== Carbon::parse($expectedUpdatedAt)->getTimestamp()) {
                throw ValidationException::withMessages([
                    'conflict' => __('admin.faqs.conflict'),
                ]);
            }

            if (array_key_exists('position', $attributes) && $attributes['position'] === null) {
                $attributes['position'] = CreateFaq::nextPosition();
            }

            $locked->fill($attributes)
                ->forceFill(['updated_at' => $locked->freshTimestamp()])
                ->save();

            return $locked;
        });
    }
}
