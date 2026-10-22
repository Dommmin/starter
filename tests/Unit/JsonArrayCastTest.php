<?php

use App\Models\PageTranslation;

/**
 * A translation whose `body` was read back as MySQL returns the json type:
 * keys sorted, its own spacing.
 */
function translationReadFromMysql(string $storedBody): PageTranslation
{
    $translation = new PageTranslation;
    $translation->setRawAttributes(['body' => $storedBody], sync: true);

    return $translation;
}

test('the same data with reordered object keys is not a change', function () {
    $translation = translationReadFromMysql('{"type": "doc", "content": [{"text": "Hi", "type": "text"}]}');

    $translation->body = ['content' => [['type' => 'text', 'text' => 'Hi']], 'type' => 'doc'];

    expect($translation->isDirty('body'))->toBeFalse();
});

test('changed values or list order are still a change', function (array $body) {
    $translation = translationReadFromMysql('{"type": "doc", "content": [1, 2]}');

    $translation->body = $body;

    expect($translation->isDirty('body'))->toBeTrue();
})->with([
    'value' => [['type' => 'paragraph', 'content' => [1, 2]]],
    'list order' => [['type' => 'doc', 'content' => [2, 1]]],
    'missing key' => [['type' => 'doc']],
]);

test('the cast reads and writes arrays like the built-in array cast', function () {
    $translation = new PageTranslation;
    $translation->body = ['type' => 'doc', 'content' => []];

    expect($translation->getAttributes()['body'])->toBe('{"type":"doc","content":[]}')
        ->and($translation->body)->toBe(['type' => 'doc', 'content' => []]);
});
