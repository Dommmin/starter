import { Link } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from '@/i18n';
import { cn } from '@/lib/utils';

import type { RouteDefinition } from '@/wayfinder';

type ButtonVariant =
    | 'primary'
    | 'secondary'
    | 'outline'
    | 'ghost'
    | 'destructive'
    | 'link';
type ButtonSize = 'sm' | 'default' | 'lg' | 'icon';

type ButtonHref = string | RouteDefinition<'get'> | { url: string };

/**
 * `external` opens `href` in a new tab (`<a target="_blank" rel="noopener
 * noreferrer">`, no Inertia visit or prefetch) and adds a visually hidden
 * "opens in a new tab" hint; it is only valid together with `href`.
 */
type ButtonTarget =
    | {
          /** A value starting with `#` renders a plain in-page anchor (no Inertia visit). */
          href?: ButtonHref;
          external?: false;
      }
    | {
          href: ButtonHref;
          external: true;
      };

export type ButtonProps = ButtonTarget & {
    children: ReactNode;
    variant?: ButtonVariant;
    size?: ButtonSize;
    /**
     * With `href`: render a plain `<a download>` (no Inertia visit) so the
     * browser saves the response. A string suggests the file name.
     */
    download?: boolean | string;
    isPending?: boolean;
    disabled?: boolean;
    type?: 'button' | 'submit' | 'reset';
    ariaLabel?: string;
    prefetch?: boolean;
    responsiveLabel?: boolean;
    onClick?: () => void;
    className?: never;
    style?: never;
};

const variantMap: Record<ButtonVariant, string> = {
    primary:
        'bg-primary text-primary-foreground shadow-sm hover:opacity-90 active:scale-[0.99]',
    secondary:
        'bg-secondary text-secondary-foreground hover:bg-secondary/80 active:scale-[0.99]',
    outline:
        'border border-border-subtle bg-transparent text-foreground hover:bg-surface-subtle active:scale-[0.99]',
    ghost: 'bg-transparent text-foreground hover:bg-surface-subtle active:scale-[0.99]',
    destructive:
        'bg-destructive text-destructive-foreground hover:opacity-90 active:scale-[0.99]',
    link: 'bg-transparent text-primary underline-offset-4 hover:underline p-0 min-h-0 min-w-0 shadow-none',
};

// WCAG 2.2 AA target size compliance: all buttons have minimum 44x44px touch targets.
const sizeMap: Record<ButtonSize, string> = {
    sm: 'min-h-[44px] min-w-[44px] px-3.5 py-1.5 text-xs rounded-(--control-radius) gap-1.5',
    default:
        'min-h-[44px] min-w-[44px] px-5 py-2.5 text-sm rounded-(--control-radius) gap-2',
    lg: 'min-h-[48px] min-w-[48px] px-8 py-3 text-base rounded-(--control-radius) gap-2.5 shadow-md',
    icon: 'min-h-[44px] min-w-[44px] p-2.5 rounded-(--control-radius)',
};

export function Button({
    children,
    variant = 'primary',
    size = 'default',
    href,
    external = false,
    download,
    isPending = false,
    disabled = false,
    type = 'button',
    ariaLabel,
    prefetch = false,
    responsiveLabel = false,
    onClick,
}: ButtonProps) {
    const baseClasses = cn(
        'group inline-flex items-center justify-center font-medium transition-all duration-150',
        // Child icons (raw Lucide SVGs default to 24px) follow the label size.
        "[&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
        'focus-visible:ring-ring focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none',
        'disabled:pointer-events-none disabled:opacity-50',
        variantMap[variant],
        variant !== 'link' && sizeMap[size],
    );

    const content = (
        <>
            {isPending && (
                <Loader2
                    className="h-4 w-4 animate-spin text-current"
                    aria-hidden="true"
                />
            )}
            {responsiveLabel ? (
                <span className="inline-flex items-center gap-1.5 [&>*:not(svg)]:hidden sm:[&>*:not(svg)]:inline">
                    {children}
                </span>
            ) : (
                children
            )}
        </>
    );

    if (href && !disabled && !isPending) {
        const targetHref =
            typeof href === 'object' && href !== null && 'url' in href
                ? href.url
                : href;

        if (external) {
            return (
                <a
                    href={targetHref}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label={ariaLabel}
                    className={baseClasses}
                    onClick={onClick}
                >
                    {content}
                    <NewTabHint />
                </a>
            );
        }

        // In-page anchor (`#section`): a plain link, no Inertia visit.
        if (download || targetHref.startsWith('#')) {
            return (
                <a
                    href={targetHref}
                    download={
                        download === undefined || download === false
                            ? undefined
                            : download === true
                              ? ''
                              : download
                    }
                    aria-label={ariaLabel}
                    className={baseClasses}
                    onClick={onClick}
                >
                    {content}
                </a>
            );
        }

        return (
            <Link
                href={targetHref}
                prefetch={prefetch}
                aria-label={ariaLabel}
                className={baseClasses}
                onClick={onClick}
            >
                {content}
            </Link>
        );
    }

    return (
        <button
            type={type}
            disabled={disabled || isPending}
            aria-label={ariaLabel}
            aria-busy={isPending}
            className={baseClasses}
            onClick={onClick}
        >
            {content}
        </button>
    );
}

/** Visually hidden suffix of the accessible name of a new-tab link. */
function NewTabHint() {
    const { t } = useTranslation();

    return <span className="sr-only"> ({t('a11y.opensInNewTab')})</span>;
}
