import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { Container, type ContainerProps } from './container';

type SectionSpacing = 'compact' | 'default' | 'relaxed';
type SectionTone = 'default' | 'subtle' | 'raised' | 'inverted';

export type SectionProps = {
    children: ReactNode;
    spacing?: SectionSpacing;
    tone?: SectionTone;
    container?: ContainerProps['width'] | 'none';
    id?: string;
    ariaLabelledBy?: string;
    className?: never;
    style?: never;
};

const spacingMap: Record<SectionSpacing, string> = {
    compact: 'py-6 sm:py-10',
    default: 'py-12 sm:py-16',
    relaxed: 'py-16 sm:py-24',
};

const toneMap: Record<SectionTone, string> = {
    default: 'bg-background text-foreground',
    subtle: 'bg-surface-subtle text-foreground',
    raised: 'bg-surface-raised text-foreground',
    inverted: 'bg-surface-inverted text-surface-inverted-foreground',
};

export function Section({
    children,
    spacing = 'default',
    tone = 'default',
    container = 'wide',
    id,
    ariaLabelledBy,
}: SectionProps) {
    const content =
        container === 'none' ? (
            children
        ) : (
            <Container width={container}>{children}</Container>
        );

    return (
        <section
            id={id}
            aria-labelledby={ariaLabelledBy}
            className={cn(
                'relative w-full',
                spacingMap[spacing],
                toneMap[tone],
            )}
        >
            {content}
        </section>
    );
}
