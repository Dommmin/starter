import type { ReactNode } from 'react';
import { Stack } from './stack';
import { Text } from './text';

export type FieldsetProps = {
    legend: string;
    description?: string;
    children: ReactNode;
    disabled?: boolean;
    className?: never;
    style?: never;
};

export function Fieldset({
    legend,
    description,
    children,
    disabled = false,
}: FieldsetProps) {
    return (
        <fieldset
            disabled={disabled}
            className="border-border-subtle rounded-lg border p-4 disabled:opacity-60 sm:p-6"
        >
            <legend className="text-foreground px-1 text-sm font-semibold">
                {legend}
            </legend>
            <Stack gap="default">
                {description && (
                    <Text variant="caption" tone="muted" as="p">
                        {description}
                    </Text>
                )}
                {children}
            </Stack>
        </fieldset>
    );
}
