import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

type IconSize = 'sm' | 'default' | 'lg';

export type IconProps = {
    icon: LucideIcon;
    size?: IconSize;
    tone?: 'default' | 'muted' | 'subtle' | 'primary' | 'success' | 'danger';
    /**
     * Accessible name for an icon that conveys meaning on its own (e.g. a
     * status glyph with no adjacent text). Omit for purely decorative icons.
     */
    label?: string;
};

const sizeMap: Record<IconSize, string> = {
    sm: 'size-3.5',
    default: 'size-4',
    lg: 'size-5',
};

const toneMap: Record<NonNullable<IconProps['tone']>, string> = {
    default: 'text-foreground',
    muted: 'text-muted-foreground',
    subtle: 'text-text-subtle',
    primary: 'text-primary',
    success: 'text-status-success',
    danger: 'text-destructive',
};

export function Icon({
    icon: IconComponent,
    size = 'default',
    tone = 'default',
    label,
}: IconProps) {
    return (
        <IconComponent
            className={cn(sizeMap[size], toneMap[tone])}
            aria-hidden={label ? undefined : true}
            role={label ? 'img' : undefined}
            aria-label={label}
        />
    );
}
