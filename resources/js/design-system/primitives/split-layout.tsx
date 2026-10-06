import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type SplitLayoutRatio = 'even' | 'primary-wide' | 'primary-narrow' | 'sidebar';
type SplitLayoutGap = 'tight' | 'default' | 'relaxed';

export type SplitLayoutProps = {
    primary: ReactNode;
    secondary: ReactNode;
    ratio?: SplitLayoutRatio;
    gap?: SplitLayoutGap;
    /** Stack `secondary` above `primary` on narrow viewports. */
    reverseOnMobile?: boolean;
    className?: never;
    style?: never;
};

const ratioMap: Record<SplitLayoutRatio, string> = {
    even: 'lg:grid-cols-2',
    'primary-wide': 'lg:grid-cols-[2fr_1fr]',
    'primary-narrow': 'lg:grid-cols-[1fr_2fr]',
    /** Fixed 14 rem `primary` column (section navigation) beside a fluid `secondary`. */
    sidebar: 'lg:grid-cols-[14rem_minmax(0,1fr)]',
};

const gapMap: Record<SplitLayoutGap, string> = {
    tight: 'gap-4',
    default: 'gap-6 sm:gap-8',
    relaxed: 'gap-8 sm:gap-12',
};

export function SplitLayout({
    primary,
    secondary,
    ratio = 'even',
    gap = 'default',
    reverseOnMobile = false,
}: SplitLayoutProps) {
    return (
        <div className={cn('grid grid-cols-1', ratioMap[ratio], gapMap[gap])}>
            <div className={cn(reverseOnMobile && 'order-2 lg:order-1')}>
                {primary}
            </div>
            <div className={cn(reverseOnMobile && 'order-1 lg:order-2')}>
                {secondary}
            </div>
        </div>
    );
}
