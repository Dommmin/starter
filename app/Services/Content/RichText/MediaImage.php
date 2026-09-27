<?php

namespace App\Services\Content\RichText;

use Closure;
use Tiptap\Core\Node;

/**
 * Block image node of the closed rich text schema. It stores only a DAM
 * reference (`mediaId`) and alternative text; the markup (`<picture>` with
 * AVIF/WebP sources and a fallback) is produced by the `render` option from
 * the current variants, never from stored URLs.
 */
class MediaImage extends Node
{
    /**
     * @var string
     */
    public static $name = 'image';

    /**
     * @return array{render: Closure(object): string}
     */
    public function addOptions()
    {
        return [
            'render' => fn (object $attributes): string => '',
        ];
    }

    /**
     * Rendering only; HTML is never parsed back into documents.
     *
     * @return list<array<string, mixed>>
     */
    public function parseHTML()
    {
        return [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function addAttributes()
    {
        return [
            'mediaId' => ['renderHTML' => fn (): null => null],
            'alt' => ['renderHTML' => fn (): null => null],
        ];
    }

    /**
     * @param  object  $node
     * @param  array<string, mixed>  $HTMLAttributes
     * @return array{content: string}
     */
    public function renderHTML($node, $HTMLAttributes = [])
    {
        return ['content' => ($this->options['render'])($node->attrs ?? (object) [])];
    }
}
