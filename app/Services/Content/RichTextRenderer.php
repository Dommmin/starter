<?php

namespace App\Services\Content;

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
 * marks and attributes below survive: no images, embeds, raw HTML, inline
 * styles, classes or event handler attributes. Rendering always sanitizes
 * first, so HTML output never depends on the stored JSON being trusted.
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
    ];

    /** @var list<string> */
    public const MARK_TYPES = ['bold', 'italic', 'strike', 'code', 'link'];

    /** @var list<int> */
    public const HEADING_LEVELS = [2, 3, 4];

    /** @var list<string> */
    public const LINK_SCHEMES = ['http', 'https', 'mailto'];

    private const MAX_HREF_LENGTH = 2048;

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
        $this->collectViolations($document, 0, $violations);

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

        return [
            'type' => 'doc',
            'content' => $this->sanitizeChildren($content, 1),
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
    private function collectViolations(array $node, int $depth, array &$violations): void
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

            $this->collectViolations($child, $depth + 1, $violations);
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
    private function sanitizeChildren(array $children, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            return [];
        }

        $result = [];

        foreach ($children as $child) {
            if (! is_array($child)) {
                continue;
            }

            $node = $this->sanitizeNode($child, $depth);

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
    private function sanitizeNode(array $node, int $depth): ?array
    {
        $type = $node['type'] ?? null;

        if (! is_string($type) || $type === 'doc' || ! in_array($type, self::NODE_TYPES, true)) {
            return null;
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
        $sanitized['content'] = $this->sanitizeChildren($content, $depth + 1);

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
}
