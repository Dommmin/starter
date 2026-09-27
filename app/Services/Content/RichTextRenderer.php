<?php

namespace App\Services\Content;

use App\Data\Media\MediaImageData;
use App\Models\MediaAsset;
use App\Repositories\Media\MediaAssetRepository;
use App\Services\Content\RichText\MediaImage;
use Tiptap\Editor;
use Tiptap\Marks\Bold;
use Tiptap\Marks\Code;
use Tiptap\Marks\Italic;
use Tiptap\Marks\Link;
use Tiptap\Marks\Strike;
use Tiptap\Nodes\Blockquote;
use Tiptap\Nodes\BulletList;
use Tiptap\Nodes\Document;
use Tiptap\Nodes\HardBreak;
use Tiptap\Nodes\Heading;
use Tiptap\Nodes\HorizontalRule;
use Tiptap\Nodes\ListItem;
use Tiptap\Nodes\OrderedList;
use Tiptap\Nodes\Paragraph;
use Tiptap\Nodes\Text;

/**
 * Closed rich text schema shared by validation, storage and rendering.
 *
 * Content is stored as a Tiptap JSON document. Only the allowlisted nodes,
 * marks and attributes below survive: no embeds, raw HTML, inline styles,
 * classes or event handler attributes. Images exist only as block `image`
 * nodes referencing a clean DAM image (`mediaId` + `alt`); URLs are never
 * stored and the `<picture>` markup is built from the current variants.
 * Rendering always sanitizes first, so HTML output never depends on the
 * stored JSON being trusted.
 */
final class RichTextRenderer
{
    /** Maximum size of the JSON-encoded document in bytes. */
    public const MAX_BYTES = 200_000;

    /** Maximum nesting depth of the document tree. */
    public const MAX_DEPTH = 24;

    /** @var list<string> */
    public const NODE_TYPES = [
        'doc',
        'paragraph',
        'heading',
        'text',
        'bulletList',
        'orderedList',
        'listItem',
        'blockquote',
        'hardBreak',
        'horizontalRule',
        'image',
    ];

    /** Nodes that may contain block content, and therefore images. */
    public const IMAGE_PARENTS = ['doc', 'blockquote', 'listItem'];

    /** Maximum number of images in one document. */
    public const MAX_IMAGES = 100;

    public const MAX_ALT_LENGTH = 500;

    /** `sizes` of rich text images: the content column is at most 48rem wide. */
    public const IMAGE_SIZES = '(min-width: 48rem) 48rem, 100vw';

    /** @var list<string> */
    public const MARK_TYPES = ['bold', 'italic', 'strike', 'code', 'link'];

    /** @var list<int> */
    public const HEADING_LEVELS = [2, 3, 4];

    /** @var list<string> */
    public const LINK_SCHEMES = ['http', 'https', 'mailto'];

    private const MAX_HREF_LENGTH = 2048;

    /**
     * Clean images referenced by the document being processed, keyed by id.
     *
     * @var array<int, MediaAsset>
     */
    private array $images = [];

    /**
     * Without the media repository (`null`, e.g. isolated unit use) no image
     * reference can be verified, so every image node is rejected.
     */
    public function __construct(private readonly ?MediaAssetRepository $media) {}

    /**
     * Describe why the value is not an acceptable document. An empty list
     * means the document may be stored.
     *
     * @return list<string> Machine-readable violation codes.
     */
    public function violations(mixed $document): array
    {
        if (! is_array($document) || ($document['type'] ?? null) !== 'doc') {
            return ['not_a_document'];
        }

        $encoded = json_encode($document);
        if ($encoded === false || strlen($encoded) > self::MAX_BYTES) {
            return ['too_large'];
        }

        $violations = [];
        $this->loadImages($document);
        $this->collectViolations($document, 0, $violations, null);

        return array_values(array_unique($violations));
    }

    /**
     * Reduce a document to the allowlisted schema: unknown nodes are removed
     * with their content, unknown marks and attributes are dropped and links
     * with a non-allowlisted scheme lose their link mark.
     *
     * @param  array<mixed>  $document
     * @return array{type: 'doc', content: list<array<string, mixed>>}
     */
    public function sanitize(array $document): array
    {
        $content = is_array($document['content'] ?? null) ? $document['content'] : [];
        $this->loadImages($document);

        return [
            'type' => 'doc',
            'content' => $this->sanitizeChildren($content, 1, 'doc'),
        ];
    }

    /**
     * Render a stored document as safe HTML.
     *
     * @param  array<mixed>|null  $document
     */
    public function toHtml(?array $document): string
    {
        if ($document === null) {
            return '';
        }

        $sanitized = $this->sanitize($document);

        if ($sanitized['content'] === []) {
            return '';
        }

        $editor = new Editor([
            'extensions' => [
                new Document,
                new Text,
                new Paragraph,
                new Heading(['levels' => self::HEADING_LEVELS]),
                new BulletList,
                new OrderedList,
                new ListItem,
                new Blockquote,
                new HardBreak,
                new HorizontalRule,
                new Bold,
                new Italic,
                new Strike,
                new Code,
                new Link([
                    'HTMLAttributes' => ['rel' => 'noopener noreferrer'],
                    'allowedProtocols' => self::LINK_SCHEMES,
                ]),
                new MediaImage([
                    'render' => fn (object $attributes): string => $this->renderImage($attributes),
                ]),
            ],
        ]);

        return $editor->setContent($sanitized)->getHTML();
    }

    public function isAllowedHref(mixed $href): bool
    {
        if (! is_string($href) || $href === '' || strlen($href) > self::MAX_HREF_LENGTH) {
            return false;
        }

        if (preg_match('/[\x00-\x20\x7F]/', $href) === 1) {
            return false;
        }

        if (preg_match('/^([a-z][a-z0-9+.\-]*):(.+)$/i', $href, $matches) !== 1) {
            return false;
        }

        $scheme = strtolower($matches[1]);

        if (! in_array($scheme, self::LINK_SCHEMES, true)) {
            return false;
        }

        if ($scheme === 'mailto') {
            return true;
        }

        return str_starts_with($matches[2], '//')
            && filter_var($href, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * @param  array<mixed>  $node
     * @param  list<string>  $violations
     */
    private function collectViolations(array $node, int $depth, array &$violations, ?string $parentType): void
    {
        if ($depth > self::MAX_DEPTH) {
            $violations[] = 'too_deep';

            return;
        }

        $type = $node['type'] ?? null;

        if (! is_string($type) || ! in_array($type, self::NODE_TYPES, true)) {
            $violations[] = 'unknown_node';

            return;
        }

        if ($type === 'doc' && $depth > 0) {
            $violations[] = 'unknown_node';

            return;
        }

        if ($type === 'heading' && ! in_array($node['attrs']['level'] ?? null, self::HEADING_LEVELS, true)) {
            $violations[] = 'invalid_heading';
        }

        if ($type === 'text' && (! is_string($node['text'] ?? null) || $node['text'] === '')) {
            $violations[] = 'invalid_text';
        }

        if ($type === 'image' && ! $this->isValidImage($node, $parentType)) {
            $violations[] = 'invalid_image';
        }

        foreach ($this->listOrEmpty($node['marks'] ?? null, $violations) as $mark) {
            $markType = is_array($mark) ? ($mark['type'] ?? null) : null;

            if (! is_string($markType) || ! in_array($markType, self::MARK_TYPES, true)) {
                $violations[] = 'unknown_mark';

                continue;
            }

            if ($markType === 'link' && ! $this->isAllowedHref($mark['attrs']['href'] ?? null)) {
                $violations[] = 'invalid_link';
            }
        }

        foreach ($this->listOrEmpty($node['content'] ?? null, $violations) as $child) {
            if (! is_array($child)) {
                $violations[] = 'unknown_node';

                continue;
            }

            $this->collectViolations($child, $depth + 1, $violations, $type);
        }
    }

    /**
     * @param  list<string>  $violations
     * @return list<mixed>
     */
    private function listOrEmpty(mixed $value, array &$violations): array
    {
        if ($value === null) {
            return [];
        }

        if (! is_array($value) || ! array_is_list($value)) {
            $violations[] = 'invalid_structure';

            return [];
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $children
     * @return list<array<string, mixed>>
     */
    private function sanitizeChildren(array $children, int $depth, string $parentType): array
    {
        if ($depth > self::MAX_DEPTH) {
            return [];
        }

        $result = [];

        foreach ($children as $child) {
            if (! is_array($child)) {
                continue;
            }

            $node = $this->sanitizeNode($child, $depth, $parentType);

            if ($node !== null) {
                $result[] = $node;
            }
        }

        return $result;
    }

    /**
     * @param  array<mixed>  $node
     * @return array<string, mixed>|null
     */
    private function sanitizeNode(array $node, int $depth, string $parentType): ?array
    {
        $type = $node['type'] ?? null;

        if (! is_string($type) || $type === 'doc' || ! in_array($type, self::NODE_TYPES, true)) {
            return null;
        }

        if ($type === 'image') {
            if (! $this->isValidImage($node, $parentType)) {
                return null;
            }

            $alt = $node['attrs']['alt'] ?? null;

            return ['type' => 'image', 'attrs' => [
                'mediaId' => $node['attrs']['mediaId'],
                'alt' => is_string($alt) ? trim($alt) : '',
            ]];
        }

        if ($type === 'text') {
            $text = $node['text'] ?? null;

            if (! is_string($text) || $text === '') {
                return null;
            }

            $marks = $this->sanitizeMarks($node['marks'] ?? null);

            return $marks === []
                ? ['type' => 'text', 'text' => $text]
                : ['type' => 'text', 'text' => $text, 'marks' => $marks];
        }

        $sanitized = ['type' => $type];

        if ($type === 'heading') {
            $level = $node['attrs']['level'] ?? null;
            $sanitized['attrs'] = [
                'level' => in_array($level, self::HEADING_LEVELS, true) ? $level : self::HEADING_LEVELS[0],
            ];
        }

        if ($type === 'orderedList') {
            $start = $node['attrs']['start'] ?? null;

            if (is_int($start) && $start > 1 && $start < 100_000) {
                $sanitized['attrs'] = ['start' => $start];
            }
        }

        if (in_array($type, ['hardBreak', 'horizontalRule'], true)) {
            return $sanitized;
        }

        $content = is_array($node['content'] ?? null) ? $node['content'] : [];
        $sanitized['content'] = $this->sanitizeChildren($content, $depth + 1, $type);

        return $sanitized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sanitizeMarks(mixed $marks): array
    {
        if (! is_array($marks)) {
            return [];
        }

        $result = [];
        $seen = [];

        foreach ($marks as $mark) {
            $type = is_array($mark) ? ($mark['type'] ?? null) : null;

            if (! is_string($type) || ! in_array($type, self::MARK_TYPES, true) || isset($seen[$type])) {
                continue;
            }

            if ($type === 'link') {
                $href = $mark['attrs']['href'] ?? null;

                if (! $this->isAllowedHref($href)) {
                    continue;
                }

                $result[] = ['type' => 'link', 'attrs' => ['href' => $href]];
            } else {
                $result[] = ['type' => $type];
            }

            $seen[$type] = true;
        }

        return $result;
    }

    /**
     * An image node is valid as a block child, with an integer `mediaId` of
     * a clean DAM image that has variants, and an optional bounded `alt`.
     *
     * @param  array<mixed>  $node
     */
    private function isValidImage(array $node, ?string $parentType): bool
    {
        if (! in_array($parentType, self::IMAGE_PARENTS, true)) {
            return false;
        }

        $attrs = $node['attrs'] ?? null;

        if (! is_array($attrs)) {
            return false;
        }

        $mediaId = $attrs['mediaId'] ?? null;
        $alt = $attrs['alt'] ?? null;

        if ($alt !== null && (! is_string($alt) || mb_strlen($alt) > self::MAX_ALT_LENGTH)) {
            return false;
        }

        return is_int($mediaId) && isset($this->images[$mediaId]);
    }

    /**
     * Load every referenced clean image with one query (bounded count).
     *
     * @param  array<mixed>  $document
     */
    private function loadImages(array $document): void
    {
        $this->images = [];

        if ($this->media === null) {
            return;
        }

        $ids = [];
        $this->collectImageIds($document, 0, $ids);

        if ($ids === []) {
            return;
        }

        $this->images = $this->media
            ->usableImagesByIds(array_slice(array_values(array_unique($ids)), 0, self::MAX_IMAGES))
            ->all();
    }

    /**
     * @param  array<mixed>  $node
     * @param  list<int>  $ids
     */
    private function collectImageIds(array $node, int $depth, array &$ids): void
    {
        if ($depth > self::MAX_DEPTH || count($ids) >= self::MAX_IMAGES) {
            return;
        }

        if (($node['type'] ?? null) === 'image') {
            $mediaId = $node['attrs']['mediaId'] ?? null;

            if (is_int($mediaId) && $mediaId > 0) {
                $ids[] = $mediaId;
            }

            return;
        }

        $content = $node['content'] ?? null;

        if (! is_array($content)) {
            return;
        }

        foreach ($content as $child) {
            if (is_array($child)) {
                $this->collectImageIds($child, $depth + 1, $ids);
            }
        }
    }

    /**
     * `<picture>` with AVIF/WebP sources and a fallback `<img>` carrying the
     * intrinsic size, lazy loading and async decoding. Every value is escaped.
     */
    private function renderImage(object $attributes): string
    {
        $mediaId = $attributes->mediaId ?? null;
        $asset = is_int($mediaId) ? ($this->images[$mediaId] ?? null) : null;
        $image = $asset === null ? null : MediaImageData::fromAsset($asset);

        if ($image === null) {
            return '';
        }

        $alt = is_string($attributes->alt ?? null) ? $attributes->alt : '';
        $sizes = e(self::IMAGE_SIZES);
        $html = '<picture>';

        foreach ($image->sources as $source) {
            $html .= '<source type="'.e($source->type).'" srcset="'.e($source->srcset).'" sizes="'.$sizes.'">';
        }

        return $html.'<img src="'.e($image->src).'" srcset="'.e($image->srcset).'" sizes="'.$sizes.'"'
            .' width="'.$image->width.'" height="'.$image->height.'" alt="'.e($alt).'"'
            .' loading="lazy" decoding="async"></picture>';
    }
}
