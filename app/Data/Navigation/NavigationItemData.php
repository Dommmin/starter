<?php

namespace App\Data\Navigation;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One resolved, visible public navigation entry; matches the `NavItem` type
 * of the design system. Only the rendered values are exposed (no target
 * ids, statuses or dates). A `group` has no `href`.
 */
#[TypeScript]
class NavigationItemData extends Data
{
    /**
     * @param  list<NavigationItemData>  $children  One level deep.
     */
    public function __construct(
        public int $id,
        public string $label,
        public string|Optional $href,
        #[LiteralTypeScriptType("'internal' | 'anchor' | 'external' | 'group'")]
        public string $kind,
        public bool $newTab,
        public array $children,
    ) {}

    /**
     * Rebuild from the cached array form (see PublicNavigation).
     *
     * @param  array<string, mixed>  $item
     */
    public static function fromArray(array $item): self
    {
        $children = [];
        foreach (is_array($item['children'] ?? null) ? $item['children'] : [] as $child) {
            if (is_array($child)) {
                $children[] = self::fromArray($child);
            }
        }

        $href = $item['href'] ?? null;

        return new self(
            id: is_int($item['id'] ?? null) ? $item['id'] : 0,
            label: is_string($item['label'] ?? null) ? $item['label'] : '',
            href: is_string($href) ? $href : Optional::create(),
            kind: is_string($item['kind'] ?? null) ? $item['kind'] : 'internal',
            newTab: ($item['newTab'] ?? false) === true,
            children: $children,
        );
    }
}
