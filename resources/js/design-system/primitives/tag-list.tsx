import { Link as InertiaLink } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export type TagListItem = {
    /** Stable key, e.g. the tag slug. */
    id: string;
    label: string;
    href: string;
    /** Marks the active filter (`aria-current="page"`). */
    current?: boolean;
};

export type TagListProps = {
    /** Accessible name of the list, e.g. "Tags" or "Filter by tag". */
    label: string;
    items: TagListItem[];
    /**
     * `inline`: small links in an article card or header. `filter`: larger
     * links in a `nav` landmark that filters a list, one of them current.
     */
    variant?: 'inline' | 'filter';
    className?: never;
    style?: never;
};

const sizeMap = {
    inline: 'min-h-6 px-2 py-0.5 text-xs',
    filter: 'min-h-9 px-3 py-1.5 text-sm',
} as const;

/**
 * Tags of an article as pill links, or the tag filter of an article list.
 * An empty list renders nothing.
 */
export function TagList({ label, items, variant = 'inline' }: TagListProps) {
    if (items.length === 0) {
        return null;
    }

    const list = (
        <ul
            aria-label={variant === 'inline' ? label : undefined}
            className="flex flex-wrap gap-2"
        >
            {items.map((item) => (
                <li key={item.id}>
                    <InertiaLink
                        href={item.href}
                        aria-current={item.current ? 'page' : undefined}
                        className={cn(
                            'inline-flex items-center rounded-md border font-medium transition-colors duration-150',
                            'focus-visible:ring-ring focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none',
                            sizeMap[variant],
                            item.current
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border-subtle text-foreground hover:bg-secondary bg-transparent',
                        )}
                    >
                        {item.label}
                    </InertiaLink>
                </li>
            ))}
        </ul>
    );

    return variant === 'filter' ? <nav aria-label={label}>{list}</nav> : list;
}
