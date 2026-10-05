import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type SurfaceTone = 'default' | 'subtle' | 'raised' | 'inverted';
type SurfacePadding = 'none' | 'compact' | 'default' | 'relaxed';
type SurfaceRadius = 'none' | 'sm' | 'default' | 'lg' | 'full';

export type SurfaceProps = {
    children: ReactNode;
    tone?: SurfaceTone;
    padding?: SurfacePadding;
    radius?: SurfaceRadius;
    border?: boolean;
    as?: 'div' | 'article' | 'aside' | 'section';
    className?: never;
    style?: never;
};

const toneMap: Record<SurfaceTone, string> = {
    default: 'bg-card text-card-foreground',
    subtle: 'bg-surface-subtle text-foreground',
    raised: 'bg-surface-raised text-foreground shadow-md',
    inverted: 'bg-surface-emphasis text-surface-emphasis-foreground shadow-xl',
};

const paddingMap: Record<SurfacePadding, string> = {
    none: 'p-0',
    compact: 'p-4 sm:p-5',
    default: 'p-6 sm:p-8',
    relaxed: 'p-8 sm:p-12',
};

const radiusMap: Record<SurfaceRadius, string> = {
    none: 'rounded-none',
    sm: 'rounded-sm',
    default: 'rounded-lg',
    lg: 'rounded-2xl',
    full: 'rounded-full',
};

export function Surface({
    children,
    tone = 'default',
    padding = 'default',
    radius = 'default',
    border = true,
    as: Component = 'div',
}: SurfaceProps) {
    return (
        <Component
            className={cn(
                'relative overflow-hidden',
                toneMap[tone],
                paddingMap[padding],
                radiusMap[radius],
                border && 'border-border-subtle border',
            )}
        >
            {children}
        </Component>
    );
}
