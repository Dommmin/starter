import { Link as InertiaLink } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

import type { RouteDefinition } from '@/wayfinder';

type LinkTone = 'default' | 'muted' | 'primary';

export type LinkProps = {
    children: ReactNode;
    href: string | RouteDefinition<'get'> | { url: string };
    tone?: LinkTone;
    prefetch?: boolean;
    ariaLabel?: string;
    /** Opens in a new tab with the required `rel` safety attributes. */
    external?: boolean;
    className?: never;
    style?: never;
};

const toneMap: Record<LinkTone, string> = {
    default: 'text-foreground decoration-border hover:decoration-current',
    muted: 'text-muted-foreground decoration-border hover:text-foreground hover:decoration-current',
    primary: 'text-primary decoration-primary/40 hover:decoration-current',
};

export function Link({
    children,
    href,
    tone = 'default',
    prefetch = false,
    ariaLabel,
    external = false,
}: LinkProps) {
    const targetHref =
        typeof href === 'object' && href !== null && 'url' in href
            ? href.url
            : href;
    const classes = cn(
        'underline underline-offset-4 transition-colors duration-150',
        'focus-visible:ring-ring rounded-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none',
        toneMap[tone],
    );

    if (external) {
        return (
            <a
                href={targetHref}
                target="_blank"
                rel="noopener noreferrer"
                aria-label={ariaLabel}
                className={classes}
            >
                {children}
            </a>
        );
    }

    return (
        <InertiaLink
            href={targetHref}
            prefetch={prefetch}
            aria-label={ariaLabel}
            className={classes}
        >
            {children}
        </InertiaLink>
    );
}
