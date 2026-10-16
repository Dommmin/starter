import {
    lazy,
    Suspense,
    useEffect,
    useId,
    useRef,
    type ReactNode,
} from 'react';
import { Alert } from './alert';
import { Button } from './button';
import { ErrorSummary, type ErrorSummaryItem } from './error-summary';
import { Grid } from './grid';
import { Heading } from './heading';
import { Section } from './section';
import type { SelectFieldOption } from './select-field';
import { Stack } from './stack';
import { Surface } from './surface';
import { Text } from './text';
import { TextField } from './text-field';
import { TextareaField } from './textarea-field';
import { useSubmitErrorFocus } from './use-submit-error-focus';

// Select and checkbox (Radix) load only for forms with extra fields, so the
// default contact form keeps the public page chunk small (bundle budget).
const ContactExtraFields = lazy(() =>
    import('./contact-extra-fields').then((module) => ({
        default: module.ContactExtraFields,
    })),
);

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
 * Configured extra field rendered after the message (e.g. a budget select
 * of a quote form). Text fields take a string, checkboxes a boolean.
 */
export type ContactSectionExtraField =
    | {
          name: string;
          type: 'text' | 'textarea' | 'select';
          label: string;
          value: string;
          onChange: (value: string) => void;
          required?: boolean;
          error?: string;
          /** Select only. */
          options?: SelectFieldOption[];
          /** Select only: translated prompt shown before a choice. */
          placeholder?: string;
      }
    | {
          name: string;
          type: 'checkbox';
          label: string;
          value: boolean;
          onChange: (value: boolean) => void;
          required?: boolean;
          error?: string;
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
    /** Short label above the title. */
    eyebrow?: string;
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
    /**
     * Supporting content (e.g. a reply promise and the next steps) under the
     * heading. With it the heading column sits beside the form on large
     * screens and the form gets a raised panel.
     */
    aside?: ReactNode;
    /** Band background, e.g. to alternate with the neighbouring sections. */
    tone?: 'default' | 'subtle';
    /** Configured fields after the message, in order. */
    extraFields?: ContactSectionExtraField[];
};

export function ContactSection({
    eyebrow,
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
    aside,
    tone = 'default',
    extraFields = [],
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

    const extraFieldId = (name: string) => `${reactId}-extra-${name}`;
    for (const field of extraFields) {
        if (field.error) {
            summaryItems.push({
                fieldId: extraFieldId(field.name),
                label: field.label,
                message: field.error,
            });
        }
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

    const align = aside ? 'start' : 'center';
    const heading = (
        <Stack gap="tight">
            {eyebrow && (
                <Text variant="label" tone="primary" align={align}>
                    {eyebrow}
                </Text>
            )}
            <Heading
                level={2}
                variant={aside ? 'page' : 'section'}
                align={align}
            >
                {title}
            </Heading>
            {description && (
                <Text variant="lead" tone="muted" align={align}>
                    {description}
                </Text>
            )}
        </Stack>
    );
    const content = hasSuccess ? (
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
            <ErrorSummary title={errorSummaryTitle} items={summaryItems} />
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
                onChange={(message) => onChange({ ...values, message })}
                error={errors.message}
                disabled={isPending}
                required
            />
            {extraFields.length > 0 && (
                <Suspense fallback={null}>
                    <ContactExtraFields
                        fields={extraFields}
                        fieldId={extraFieldId}
                        disabled={isPending}
                    />
                </Suspense>
            )}
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
            <Button type="submit" size="lg" isPending={isPending}>
                {submitLabel}
            </Button>
        </form>
    );

    if (aside) {
        return (
            <Section spacing="relaxed" tone={tone} container="wide">
                <Grid layout="split-asymmetric" gap="relaxed">
                    <Stack gap="relaxed">
                        {heading}
                        {aside}
                    </Stack>
                    <Surface tone="raised" radius="lg" padding="default">
                        {content}
                    </Surface>
                </Grid>
            </Section>
        );
    }

    return (
        <Section spacing="default" tone={tone} container="reading">
            <Stack gap="default">
                {heading}
                {content}
            </Stack>
        </Section>
    );
}
