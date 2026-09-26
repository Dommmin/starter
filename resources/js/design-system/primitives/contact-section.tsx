import { useId, type ReactNode } from 'react';
import { Button } from './button';
import { ErrorSummary, type ErrorSummaryItem } from './error-summary';
import { Heading } from './heading';
import { Section } from './section';
import { Stack } from './stack';
import { Text } from './text';
import { TextField } from './text-field';
import { TextareaField } from './textarea-field';

export type ContactSectionValues = {
    name: string;
    email: string;
    message: string;
};

export type ContactSectionErrors = Partial<
    Record<keyof ContactSectionValues, string>
>;

export type ContactSectionLabels = {
    name: string;
    email: string;
    message: string;
};

export type ContactSectionProps = {
    title: string;
    description?: string;
    labels: ContactSectionLabels;
    /** Translated heading for the error summary shown above the form when validation fails. */
    errorSummaryTitle: string;
    values: ContactSectionValues;
    onChange: (values: ContactSectionValues) => void;
    /** The caller owns the actual submission request; no backend contract is assumed. */
    onSubmit: () => void;
    errors?: ContactSectionErrors;
    submitLabel: string;
    isPending?: boolean;
    /** Rendered instead of the form after a successful submission. */
    success?: ReactNode;
};

export function ContactSection({
    title,
    description,
    labels,
    errorSummaryTitle,
    values,
    onChange,
    onSubmit,
    errors = {},
    submitLabel,
    isPending = false,
    success,
}: ContactSectionProps) {
    const reactId = useId();
    const nameFieldId = `${reactId}-name`;
    const emailFieldId = `${reactId}-email`;
    const messageFieldId = `${reactId}-message`;

    const summaryItems: ErrorSummaryItem[] = [];
    if (errors.name) {
        summaryItems.push({
            fieldId: nameFieldId,
            label: labels.name,
            message: errors.name,
        });
    }
    if (errors.email) {
        summaryItems.push({
            fieldId: emailFieldId,
            label: labels.email,
            message: errors.email,
        });
    }
    if (errors.message) {
        summaryItems.push({
            fieldId: messageFieldId,
            label: labels.message,
            message: errors.message,
        });
    }

    return (
        <Section spacing="default" container="reading">
            <Stack gap="default">
                <Stack gap="tight">
                    <Heading level={2} align="center">
                        {title}
                    </Heading>
                    {description && (
                        <Text variant="lead" tone="muted" align="center">
                            {description}
                        </Text>
                    )}
                </Stack>
                {success ?? (
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (isPending) {
                                return;
                            }
                            onSubmit();
                        }}
                        className="flex flex-col gap-4"
                        noValidate
                    >
                        <ErrorSummary
                            title={errorSummaryTitle}
                            items={summaryItems}
                        />
                        <TextField
                            id={nameFieldId}
                            name="name"
                            label={labels.name}
                            value={values.name}
                            onChange={(name) => onChange({ ...values, name })}
                            error={errors.name}
                            disabled={isPending}
                            required
                        />
                        <TextField
                            id={emailFieldId}
                            name="email"
                            type="email"
                            label={labels.email}
                            value={values.email}
                            onChange={(email) => onChange({ ...values, email })}
                            error={errors.email}
                            disabled={isPending}
                            required
                        />
                        <TextareaField
                            id={messageFieldId}
                            name="message"
                            label={labels.message}
                            value={values.message}
                            onChange={(message) =>
                                onChange({ ...values, message })
                            }
                            error={errors.message}
                            disabled={isPending}
                            required
                        />
                        <Button type="submit" isPending={isPending}>
                            {submitLabel}
                        </Button>
                    </form>
                )}
            </Stack>
        </Section>
    );
}
