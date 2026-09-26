import { Link } from '@inertiajs/react';
import { Loader2, type LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

import type { RouteDefinition } from '@/wayfinder';

type IconButtonVariant =
    | 'primary'
    | 'secondary'
    | 'outline'
    | 'ghost'
    | 'destructive';
type IconButtonSize = 'sm' | 'default' | 'lg';

export type IconButtonProps = {
    icon: LucideIcon;
    /** Required: an icon-only control must expose its accessible name. */
    ariaLabel: string;
    variant?: IconButtonVariant;
    size?: IconButtonSize;
    href?: string | RouteDefinition<'get'> | { url: string };
    isPending?: boolean;
    disabled?: boolean;
    type?: 'button' | 'submit' | 'reset';
    prefetch?: boolean;
    onClick?: () => void;
    className?: never;
    style?: never;
};

const variantMap: Record<IconButtonVariant, string> = {
    primary:
        'bg-primary text-primary-foreground shadow-sm hover:opacity-90 active:scale-[0.99]',
    secondary:
        'bg-secondary text-secondary-foreground hover:bg-secondary/80 active:scale-[0.99]',
    outline:
        'border border-border-subtle bg-transparent text-foreground hover:bg-surface-subtle active:scale-[0.99]',
    ghost: 'bg-transparent text-foreground hover:bg-surface-subtle active:scale-[0.99]',
    destructive:
        'bg-destructive text-destructive-foreground hover:opacity-90 active:scale-[0.99]',
};

// WCAG 2.2 AA target size compliance: minimum 44x44px touch target at every size.
const sizeMap: Record<IconButtonSize, string> = {
    sm: 'size-11 [&_svg]:size-4',
    default: 'size-11 [&_svg]:size-5',
    lg: 'size-12 [&_svg]:size-5',
};

export function IconButton({
    icon: IconComponent,
    ariaLabel,
    variant = 'ghost',
    size = 'default',
    href,
    isPending = false,
    disabled = false,
    type = 'button',
    prefetch = false,
    onClick,
}: IconButtonProps) {
    const baseClasses = cn(
        'inline-flex items-center justify-center rounded-full transition-all duration-150',
        'focus-visible:ring-ring focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none',
        'disabled:pointer-events-none disabled:opacity-50',
        variantMap[variant],
        sizeMap[size],
    );

    const content = isPending ? (
        <Loader2 className="animate-spin" aria-hidden="true" />
    ) : (
        <IconComponent aria-hidden="true" />
    );

    if (href && !disabled && !isPending) {
        const targetHref =
            typeof href === 'object' && href !== null && 'url' in href
                ? href.url
                : href;
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
