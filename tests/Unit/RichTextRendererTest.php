<?php

use App\Services\Content\RichTextRenderer;

/**
 * @param  list<array<string, mixed>>  $content
 * @return array<string, mixed>
 */
function doc(array $content): array
{
    return ['type' => 'doc', 'content' => $content];
}

/**
 * @param  list<array<string, mixed>>  $marks
 * @return array<string, mixed>
 */
function linkedText(string $href, array $marks = []): array
{
    return ['type' => 'paragraph', 'content' => [[
        'type' => 'text',
        'text' => 'link',
        'marks' => [['type' => 'link', 'attrs' => ['href' => $href, 'target' => '_self', 'onclick' => 'alert(1)']], ...$marks],
    ]]];
}

test('allowlisted nodes and marks render to html', function () {
    $html = (new RichTextRenderer)->toHtml(doc([
        ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [['type' => 'text', 'text' => 'Title']]],
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'bold', 'marks' => [['type' => 'bold']]],
            ['type' => 'hardBreak'],
            ['type' => 'text', 'text' => 'code', 'marks' => [['type' => 'code']]],
        ]],
        ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'item']]]]]]],
        ['type' => 'orderedList', 'attrs' => ['start' => 3], 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'three']]]]]]],
        ['type' => 'blockquote', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'quote']]]]],
        ['type' => 'horizontalRule'],
    ]));

    expect($html)->toBe(
        '<h3>Title</h3><p><strong>bold</strong><br><code>code</code></p>'
        .'<ul><li><p>item</p></li></ul><ol start="3"><li><p>three</p></li></ol>'
        .'<blockquote><p>quote</p></blockquote><hr>'
    );
});

test('links keep only an allowed href and get a safe rel', function (string $href) {
    $html = (new RichTextRenderer)->toHtml(doc([linkedText($href)]));

    expect($html)->toBe('<p><a rel="noopener noreferrer" href="'.htmlspecialchars($href).'">link</a></p>');
})->with(['https://example.com/a?b=1', 'http://example.com', 'mailto:hello@example.com']);

test('unsafe links lose the link mark', function (string $href) {
    $renderer = new RichTextRenderer;
    $html = $renderer->toHtml(doc([linkedText($href)]));

    expect($html)->toBe('<p>link</p>')
        ->and($renderer->violations(doc([linkedText($href)])))->toContain('invalid_link');
})->with(['javascript:alert(1)', ' JavaScript:alert(1)', 'java&#10;script:x', 'data:text/html,x', 'vbscript:x', '/relative', '//evil.example', 'https:evil', 'ftp://example.com']);

test('text is escaped and unknown nodes, marks and attributes never reach the html', function () {
    $renderer = new RichTextRenderer;
    $document = doc([
        ['type' => 'paragraph', 'attrs' => ['onclick' => 'alert(1)', 'class' => 'x', 'style' => 'color:red'], 'content' => [
            ['type' => 'text', 'text' => '<script>alert(1)</script>', 'marks' => [['type' => 'textStyle', 'attrs' => ['style' => 'x']]]],
        ]],
        ['type' => 'image', 'attrs' => ['src' => 'x', 'onerror' => 'alert(1)']],
        ['type' => 'iframe', 'content' => [['type' => 'text', 'text' => 'hidden']]],
        ['type' => 'heading', 'attrs' => ['level' => 1, 'onmouseover' => 'x'], 'content' => [['type' => 'text', 'text' => 'h']]],
    ]);

    $html = $renderer->toHtml($document);

    expect($html)->toBe('<p>&lt;script&gt;alert(1)&lt;/script&gt;</p><h2>h</h2>')
        ->and($html)->not->toContain('<script')
        ->and($html)->not->toContain('on')
        ->and($renderer->violations($document))->toContain('unknown_node', 'unknown_mark', 'invalid_heading');
});

test('sanitize keeps the allowed structure and drops everything else', function () {
    $sanitized = (new RichTextRenderer)->sanitize([
        'type' => 'doc',
        'attrs' => ['x' => 1],
        'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'a', 'marks' => [['type' => 'italic', 'attrs' => ['x' => 1]]]]]],
            ['type' => 'script', 'content' => []],
            'not a node',
        ],
    ]);

    expect($sanitized)->toBe(doc([
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'a', 'marks' => [['type' => 'italic']]]]],
    ]));
});

test('violations reject non documents, oversized and too deep documents', function () {
    $renderer = new RichTextRenderer;

    $deep = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'x']]];
    for ($i = 0; $i < RichTextRenderer::MAX_DEPTH + 1; $i++) {
        $deep = ['type' => 'blockquote', 'content' => [$deep]];
    }

    expect($renderer->violations('<p>html</p>'))->toBe(['not_a_document'])
        ->and($renderer->violations(['type' => 'paragraph']))->toBe(['not_a_document'])
        ->and($renderer->violations(doc([['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => str_repeat('a', RichTextRenderer::MAX_BYTES)]]]])))->toBe(['too_large'])
        ->and($renderer->violations(doc([$deep])))->toBe(['too_deep'])
        ->and($renderer->violations(doc([])))->toBe([])
        ->and($renderer->toHtml(null))->toBe('');
});
