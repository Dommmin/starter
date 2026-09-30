import { useEffect, useId, useRef, type ReactNode } from 'react';
import { Alert } from './alert';
import { Button } from './button';
import { ErrorSummary, type ErrorSummaryItem } from './error-summary';
import { Heading } from './heading';
import { Section } from './section';
import { Stack } from './stack';
import { Text } from './text';
import { TextField } from './text-field';
import { TextareaField } from './textarea-field';
import { useSubmitErrorFocus } from './use-submit-error-focus';

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

/**
 * Honeypot field for spam bots: rendered off-screen, hidden from assistive
 * technology and removed from the tab order. People never fill it in.
 */
export type ContactSectionSpamTrap = {
    name: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
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
    /** Form-level error not tied to a field (e.g. rate limit, expired form). */
    formError?: string;
    spamTrap?: ContactSectionSpamTrap;
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
    formError,
    spamTrap,
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

    const hasSuccess = success !== undefined && success !== null;
    const successRef = useRef<HTMLDivElement>(null);
    const hadSuccessRef = useRef(hasSuccess);

    // After a successful submission the form (and its focused submit button)
    // disappears; move focus to the announced message instead of the body.
    // A message present on first render (e.g. a static demo) takes no focus.
    useEffect(() => {
        if (hasSuccess && !hadSuccessRef.current) {
            successRef.current?.focus();
        }
        hadSuccessRef.current = hasSuccess;
    }, [hasSuccess]);

    const markSubmitted = useSubmitErrorFocus(
        summaryItems.map((item) => item.fieldId),
        errors,
        isPending,
    );

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
                {hasSuccess ? (
                    <div
                        ref={successRef}
                        role="status"
                        tabIndex={-1}
                        className="focus-visible:ring-ring rounded-lg focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                    >
                        {success}
                    </div>
                ) : (
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (isPending) {
                                return;
                            }
                            markSubmitted();
                            onSubmit();
                        }}
                        className="flex flex-col gap-4"
                        noValidate
                    >
                        {formError && <Alert title={formError} tone="danger" />}
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
                            autoComplete="name"
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
                            autoComplete="email"
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
                        {spamTrap && (
                            <div aria-hidden="true" className="sr-only">
                                <label htmlFor={`${reactId}-${spamTrap.name}`}>
                                    {spamTrap.label}
                                </label>
                                <input
                                    id={`${reactId}-${spamTrap.name}`}
                                    type="text"
                                    name={spamTrap.name}
                                    value={spamTrap.value}
                                    onChange={(event) =>
                                        spamTrap.onChange(event.target.value)
                                    }
                                    tabIndex={-1}
                                    autoComplete="off"
                                />
                            </div>
                        )}
                        <Button type="submit" isPending={isPending}>
                            {submitLabel}
                        </Button>
                    </form>
                )}
            </Stack>
        </Section>
    );
}
