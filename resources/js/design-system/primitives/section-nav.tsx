import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export type SectionNavItem = {
    id: string;
    label: string;
    href: string;
    /** The entry for the page being shown; rendered with `aria-current="page"`. */
    current: boolean;
};

export type SectionNavProps = {
    items: SectionNavItem[];
    /** Translated accessible label for the navigation landmark. */
    ariaLabel: string;
    className?: never;
    style?: never;
};

/**
 * Vertical list of links between sibling pages of one section (e.g. account
 * settings). Each link keeps a 44 px touch target; the current page is marked
 * visually and for assistive technology.
 */
export function SectionNav({ items, ariaLabel }: SectionNavProps) {
    return (
        <nav aria-label={ariaLabel} className="flex flex-col gap-1.5">
            {items.map((item) => (
                <Link
                    key={item.id}
                    href={item.href}
                    aria-current={item.current ? 'page' : undefined}
                    className={cn(
                        'flex min-h-[44px] items-center rounded-lg px-3.5 py-2 text-sm transition-colors',
                        'focus-visible:ring-ring focus-visible:ring-2 focus-visible:outline-none',
                        item.current
                            ? 'bg-surface-subtle text-foreground font-semibold'
                            : 'text-text-subtle hover:bg-surface-subtle/50 hover:text-foreground font-medium',
                    )}
                >
                    {item.label}
                </Link>
            ))}
        </nav>
    );
}
