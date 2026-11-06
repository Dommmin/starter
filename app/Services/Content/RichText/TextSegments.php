<?php

namespace App\Services\Content\RichText;

use InvalidArgumentException;

/**
 * Text nodes of a Tiptap document as an ordered list of strings, and the
 * reverse: the same document with every text node replaced by a new string.
 * Only the `text` values change, so node types, attributes, marks and links
 * stay exactly as in the source (used by the AI translation, which never
 * sees the structure).
 */
final class TextSegments
{
    /**
     * Texts of all text nodes, depth first in document order.
     *
     * @param  array<string, mixed>  $document
     * @return list<string>
     */
    public function extract(array $document): array
    {
        $segments = [];
        $this->collect($document, $segments);

        return $segments;
    }

    /**
     * The document with the text nodes replaced in order. Leading and
     * trailing whitespace of each source node is kept, so words next to
     * marks and links stay separated.
     *
     * @param  array<string, mixed>  $document
     * @param  list<string>  $segments
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException When the number of segments does not match or a segment is blank.
     */
    public function replace(array $document, array $segments): array
    {
        if (count($segments) !== count($this->extract($document))) {
            throw new InvalidArgumentException('The number of segments does not match the document.');
        }

        $index = 0;

        return $this->apply($document, $segments, $index);
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @param  list<string>  $segments
     */
    private function collect(array $node, array &$segments): void
    {
        if (($node['type'] ?? null) === 'text' && is_string($node['text'] ?? null)) {
            $segments[] = $node['text'];

            return;
        }

        if (! is_array($node['content'] ?? null)) {
            return;
        }

        foreach ($node['content'] as $child) {
            if (is_array($child)) {
                $this->collect($child, $segments);
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @param  list<string>  $segments
     * @return array<array-key, mixed>
     */
    private function apply(array $node, array $segments, int &$index): array
    {
        if (($node['type'] ?? null) === 'text' && is_string($node['text'] ?? null)) {
            $node['text'] = $this->withSourceWhitespace($node['text'], $segments[$index]);
            $index++;

            return $node;
        }

        if (! is_array($node['content'] ?? null)) {
            return $node;
        }

        foreach ($node['content'] as $key => $child) {
            if (is_array($child)) {
                $node['content'][$key] = $this->apply($child, $segments, $index);
            }
        }

        return $node;
    }

    private function withSourceWhitespace(string $source, string $translation): string
    {
        if (trim($source) === '') {
            return $source;
        }

        $translated = trim($translation);

        if ($translated === '') {
            throw new InvalidArgumentException('A translated segment is blank.');
        }

        $leading = substr($source, 0, strlen($source) - strlen(ltrim($source)));
        $trailing = substr($source, strlen(rtrim($source)));

        return $leading.$translated.$trailing;
    }
}
