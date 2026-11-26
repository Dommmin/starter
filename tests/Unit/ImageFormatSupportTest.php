<?php

use App\Services\Media\ImageFormatSupport;

test('the probe accepts the formats GD writes and rejects unknown ones', function () {
    $support = new ImageFormatSupport;

    expect($support->encodes('jpeg'))->toBeTrue()
        ->and($support->encodes('png'))->toBeTrue()
        ->and($support->encodes('tiff'))->toBeFalse();
});
