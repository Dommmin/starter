<?php

use App\Models\MediaAsset;
use App\Services\Content\RichTextRenderer;
use Illuminate\Support\Facades\Storage;

/**
 * @param  array<string, mixed>  $attrs
 * @return array<string, mixed>
 */
function imageNode(array $attrs): array
{
    return ['type' => 'image', 'attrs' => $attrs];
}

/**
 * @param  list<array<string, mixed>>  $content
 * @return array{type: 'doc', content: list<array<string, mixed>>}
 */
function imageDoc(array $content): array
{
    return ['type' => 'doc', 'content' => $content];
}

beforeEach(function () {
    Storage::fake('public');
});

test('an image of a clean dam asset renders a responsive picture', function () {
    $asset = MediaAsset::factory()->withVariants()->create();

    $html = app(RichTextRenderer::class)->toHtml(imageDoc([
        imageNode(['mediaId' => $asset->id, 'alt' => 'A "quoted" <b>alt</b>', 'src' => 'https://evil.test/x.png', 'onerror' => 'alert(1)']),
    ]));

    $url = fn (string $file): string => Storage::disk('public')->url("media/{$asset->uuid}/{$file}");

    expect($html)->toStartWith('<picture><source type="image/avif" srcset="'.$url('abc123-320.avif').' 320w, '.$url('abc123-640.avif').' 640w"')
        ->toContain('<source type="image/webp"')
        ->toContain('<img src="'.$url('abc123-640.jpg').'" srcset="'.$url('abc123-320.jpg').' 320w, '.$url('abc123-640.jpg').' 640w"')
        ->toContain('sizes="(min-width: 48rem) 48rem, 100vw"')
        ->toContain('width="640" height="360"')
        ->toContain('alt="A &quot;quoted&quot; &lt;b&gt;alt&lt;/b&gt;"')
        ->toContain('loading="lazy" decoding="async"')
        ->not->toContain('evil.test')
        ->not->toContain('onerror');
});

test('sanitize keeps only the media id and alt of usable images', function () {
    $asset = MediaAsset::factory()->withVariants()->create();
    $quarantined = MediaAsset::factory()->create(['variants' => $asset->variants]);
    $rejected = MediaAsset::factory()->rejected()->create(['variants' => $asset->variants]);

    $sanitized = app(RichTextRenderer::class)->sanitize(imageDoc([
        imageNode(['mediaId' => $asset->id, 'alt' => ' Kept ', 'src' => 'https://evil.test/x.png']),
        imageNode(['mediaId' => $quarantined->id, 'alt' => 'quarantine']),
        imageNode(['mediaId' => $rejected->id, 'alt' => 'rejected']),
        imageNode(['mediaId' => 999_999, 'alt' => 'missing']),
        imageNode(['mediaId' => (string) $asset->id, 'alt' => 'string id']),
        imageNode(['src' => 'https://evil.test/x.png']),
        ['type' => 'paragraph', 'content' => [imageNode(['mediaId' => $asset->id, 'alt' => 'inline'])]],
        ['type' => 'blockquote', 'content' => [imageNode(['mediaId' => $asset->id])]],
    ]));

    expect($sanitized['content'])->toBe([
        imageNode(['mediaId' => $asset->id, 'alt' => 'Kept']),
        ['type' => 'paragraph', 'content' => []],
        ['type' => 'blockquote', 'content' => [imageNode(['mediaId' => $asset->id, 'alt' => ''])]],
    ]);
});

test('validation reports images that are not clean dam images', function () {
    $asset = MediaAsset::factory()->withVariants()->create();
    $renderer = app(RichTextRenderer::class);

    expect($renderer->violations(imageDoc([imageNode(['mediaId' => $asset->id, 'alt' => 'ok'])])))->toBe([])
        ->and($renderer->violations(imageDoc([imageNode(['mediaId' => $asset->id + 1])])))->toBe(['invalid_image'])
        ->and($renderer->violations(imageDoc([imageNode(['mediaId' => $asset->id, 'alt' => str_repeat('a', 501)])])))->toBe(['invalid_image'])
        ->and($renderer->violations(imageDoc([['type' => 'paragraph', 'content' => [imageNode(['mediaId' => $asset->id])]]])))->toBe(['invalid_image']);
});

test('a deleted asset silently disappears from rendered content', function () {
    $asset = MediaAsset::factory()->withVariants()->create();
    $document = imageDoc([imageNode(['mediaId' => $asset->id, 'alt' => 'x'])]);
    $asset->delete();

    expect(app(RichTextRenderer::class)->toHtml($document))->toBe('');
});

test('a renderer without the media repository accepts no images', function () {
    $asset = MediaAsset::factory()->withVariants()->create();
    $renderer = new RichTextRenderer(null);

    expect($renderer->violations(imageDoc([imageNode(['mediaId' => $asset->id])])))->toBe(['invalid_image'])
        ->and($renderer->toHtml(imageDoc([imageNode(['mediaId' => $asset->id])])))->toBe('');
});
