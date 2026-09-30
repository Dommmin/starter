<?php

use App\Support\Csv\CsvCell;

test('text that a spreadsheet would evaluate is prefixed with an apostrophe', function (string $value) {
    expect(CsvCell::safe($value))->toBe("'".$value);
})->with([
    'formula' => ['=cmd|\' /C calc\'!A0'],
    'plus' => ['+SUM(A1)'],
    'minus' => ['-2+3'],
    'at' => ['@SUM(A1)'],
    'tab' => ["\t=1"],
    'carriage return' => ["\r=1"],
]);

test('plain text, numbers and numeric strings are left unchanged', function (string|int|float $value) {
    expect(CsvCell::safe($value))->toBe($value);
})->with([
    'text' => ['Plain text = fine'],
    'empty string' => [''],
    'integer' => [-42],
    'float' => [-1.5],
    'decimal string' => ['-12.50'],
    'integer string' => ['7'],
]);

test('null and booleans become empty and numeric cells', function () {
    expect(CsvCell::safe(null))->toBe('')
        ->and(CsvCell::safe(true))->toBe(1)
        ->and(CsvCell::safe(false))->toBe(0);
});
