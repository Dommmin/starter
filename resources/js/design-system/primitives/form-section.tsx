import { useId, type ReactNode } from 'react';
import { Heading } from './heading';
import { Stack } from './stack';
import { Text } from './text';

export type FormSectionProps = {
    title: string;
    description?: string;
    children: ReactNode;
    className?: never;
    style?: never;
};

export function FormSection({
    title,
    description,
    children,
}: FormSectionProps) {
    const headingId = useId();

    return (
        <section
            aria-labelledby={headingId}
            className="border-border-subtle border-b pb-6 last:border-b-0 last:pb-0"
        >
            <Stack gap="default">
                <Stack gap="tight">
                    <Heading id={headingId} level={3} variant="group">
                        {title}
                    </Heading>
                    {description && (
                        <Text variant="caption" tone="muted">
                            {description}
                        </Text>
                    )}
                </Stack>
                <Stack gap="default">{children}</Stack>
            </Stack>
        </section>
    );
}
