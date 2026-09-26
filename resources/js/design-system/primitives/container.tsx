import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type ContainerWidth = 'reading' | 'content' | 'wide' | 'full';
type ContainerPadding = 'page' | 'compact' | 'none';

export type ContainerProps = {
    children: ReactNode;
    width?: ContainerWidth;
    padding?: ContainerPadding;
    className?: never;
    style?: never;
};

const widthMap: Record<ContainerWidth, string> = {
    reading: 'max-w-3xl',
    content: 'max-w-5xl',
    wide: 'max-w-6xl',
    full: 'w-full',
};

const paddingMap: Record<ContainerPadding, string> = {
    page: 'px-4 sm:px-6 lg:px-8',
    compact: 'px-4 sm:px-6',
    none: 'px-0',
};

export function Container({
    children,
    width = 'wide',
    padding = 'page',
}: ContainerProps) {
    return (
        <div
            className={cn(
                'mx-auto w-full',
                widthMap[width],
                paddingMap[padding],
            )}
        >
            {children}
        </div>
    );
}
