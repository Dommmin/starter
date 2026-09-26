import type { ReactNode } from 'react';
import { Heading } from './heading';
import { Section } from './section';
import { Stack } from './stack';
import { Text } from './text';

type CTATone = 'default' | 'inverted';

export type CTAProps = {
    title: string;
    description?: string;
    /** Typically one or two <Button>s. */
    actions: ReactNode;
    tone?: CTATone;
};

export function CTA({
    title,
    description,
    actions,
    tone = 'inverted',
}: CTAProps) {
    return (
        <Section spacing="default" tone={tone} container="content">
            <Stack gap="default" align="center">
                <Heading
                    level={2}
                    align="center"
                    tone={tone === 'inverted' ? 'inverted' : 'default'}
                >
                    {title}
                </Heading>
                {description && (
                    <Text
                        variant="lead"
                        tone={tone === 'inverted' ? 'inverted' : 'muted'}
                        align="center"
                    >
                        {description}
                    </Text>
                )}
                <div className="flex flex-col gap-3 sm:flex-row">{actions}</div>
            </Stack>
        </Section>
    );
}
