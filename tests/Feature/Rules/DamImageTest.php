<?php

use App\Models\MediaAsset;
use App\Rules\DamImage;
use Illuminate\Support\Facades\Validator;

function passesDamImage(mixed $value): bool
{
    return Validator::make(['image' => $value], ['image' => [new DamImage]])->passes();
}

test('a clean image with variants is accepted', function () {
    $image = MediaAsset::factory()->withVariants()->create();

    expect(passesDamImage($image->id))->toBeTrue()
        ->and(passesDamImage((string) $image->id))->toBeTrue();
});

test('files that are not usable DAM images are rejected', function (Closure $value) {
    expect(passesDamImage($value()))->toBeFalse();
})->with([
    'quarantined image' => [fn () => MediaAsset::factory()->create()->id],
    'rejected image' => [fn () => MediaAsset::factory()->rejected()->create()->id],
    'clean image without variants' => [fn () => MediaAsset::factory()->clean()->create()->id],
    'clean PDF' => [fn () => MediaAsset::factory()->pdf()->clean()->create()->id],
    'missing id' => [fn () => 999_999],
    'not an id' => [fn () => 'abc'],
]);

test('the failure message is the shared DAM image message', function () {
    $validator = Validator::make(['image' => 999_999], ['image' => [new DamImage]]);

    expect($validator->errors()->first('image'))->toBe(__('admin.richText.imageInvalid'));
});
