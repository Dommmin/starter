<?php

namespace App\Rules;

use App\Models\MediaAsset;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Accept only the id of a DAM image that passed the malware scan and already
 * has its public variants: status clean, an image MIME type from
 * `MediaAsset::IMAGE_MIMES` and variants present (the article cover rule).
 */
class DamImage implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);

        if ($id === false || ! MediaAsset::query()->whereKey($id)->usableImages()->exists()) {
            $fail(__('admin.richText.imageInvalid'));
        }
    }
}
