import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { Heading } from './heading';
import { Section } from './section';
import { Stack } from './stack';
import { Text } from './text';

type HeroAlign = 'start' | 'center';

export type HeroProps = {
    eyebrow?: string;
    title: string;
    description?: string;
    /** Typically one or two <Button>s. */
    actions?: ReactNode;
    /** Optional visual, e.g. an <img>, rendered beside the copy on wide screens. */
    media?: ReactNode;
    align?: HeroAlign;
};

export function Hero({
    eyebrow,
    title,
    description,
    actions,
    media,
    align = 'start',
}: HeroProps) {
    const copy = (
        <Stack gap="default" align={align === 'center' ? 'center' : 'start'}>
            {eyebrow && (
                <Text variant="label" tone="primary" align={align}>
                    {eyebrow}
                </Text>
            )}
            <Heading level={1} variant="display" align={align}>
                {title}
            </Heading>
            {description && (
                <Text variant="lead" tone="muted" align={align}>
                    {description}
                </Text>
            )}
            {actions && (
                <div
                    className={cn(
                        'flex flex-col gap-3 sm:flex-row',
                        align === 'center' && 'sm:justify-center',
                    )}
                >
                    {actions}
                </div>
            )}
        </Stack>
    );

    return (
        <Section spacing="relaxed">
            {media ? (
                <div className="grid grid-cols-1 items-center gap-8 lg:grid-cols-2 lg:gap-12">
                    {copy}
                    <div>{media}</div>
                </div>
            ) : (
                <div
                    className={cn(
                        'mx-auto max-w-3xl',
                        align === 'center' && 'text-center',
                    )}
                >
                    {copy}
                </div>
            )}
        </Section>
    );
}
