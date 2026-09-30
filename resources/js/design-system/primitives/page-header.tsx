import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { Heading } from './heading';
import { Inline } from './inline';
import { Stack } from './stack';
import { Text } from './text';

type PageHeaderAlign = 'start' | 'center';

export type PageHeaderProps = {
    title: ReactNode;
    description?: ReactNode;
    badge?: ReactNode;
    actions?: ReactNode;
    align?: PageHeaderAlign;
    className?: never;
    style?: never;
};

export function PageHeader({
    title,
    description,
    badge,
    actions,
    align = 'start',
}: PageHeaderProps) {
    const isCenter = align === 'center';

    return (
        <div
            className={cn(
                'flex flex-col gap-4 py-4 sm:py-6',
                isCenter ? 'items-center text-center' : 'items-start text-left',
            )}
        >
            {badge && <div>{badge}</div>}

            <Stack gap="tight" align={isCenter ? 'center' : 'start'}>
                <Heading level={1} variant="page" align={align}>
                    {title}
                </Heading>

                {description && (
                    <Text variant="lead" tone="muted" align={align}>
                        {description}
                    </Text>
                )}
            </Stack>

            {actions && (
                <div className="mt-2">
                    <Inline
                        gap="default"
                        align="stretch"
                        justify={isCenter ? 'center' : 'start'}
                        wrap
                    >
                        {actions}
                    </Inline>
                </div>
            )}
        </div>
    );
}
