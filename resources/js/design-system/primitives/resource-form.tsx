import { useEffect, useId, useRef } from 'react';
import { Alert } from './alert';
import { Button, type ButtonProps } from './button';
import { CheckboxField } from './checkbox-field';
import { ErrorSummary, type ErrorSummaryItem } from './error-summary';
import { FormActions } from './form-actions';
import { FormSection } from './form-section';
import {
    RichTextField,
    type RichTextDocument,
    type RichTextFieldLabels,
} from './rich-text-field';
import { SelectField, type SelectFieldOption } from './select-field';
import { Stack } from './stack';
import { SwitchField } from './switch-field';
import { TextField } from './text-field';
import { TextareaField } from './textarea-field';

/** Form values handled by `ResourceForm`: flat string/boolean fields and rich text documents. */
export type ResourceFormValues = Record<
    string,
    string | boolean | RichTextDocument
>;

type KeysOfType<Values, Type> = {
    [Key in keyof Values & string]: Values[Key] extends Type ? Key : never;
}[keyof Values & string];

function stringValue(value: unknown): string {
    return typeof value === 'string' ? value : '';
}

type ResourceFormFieldBase = {
    /** Translated label. */
    label: string;
    /** Translated help text rendered under the control. */
    hint?: string;
    required?: boolean;
    disabled?: boolean;
};

export type ResourceFormField<Values extends ResourceFormValues> =
    ResourceFormFieldBase &
        (
            | {
                  type: 'text';
                  name: KeysOfType<Values, string>;
                  inputType?: 'text' | 'email' | 'url' | 'tel';
                  autoComplete?: string;
                  placeholder?: string;
              }
            | {
                  type: 'textarea';
                  name: KeysOfType<Values, string>;
                  rows?: number;
                  placeholder?: string;
              }
            | {
                  type: 'select';
                  name: KeysOfType<Values, string>;
                  options: SelectFieldOption[];
                  placeholder?: string;
              }
            | {
                  type: 'switch' | 'checkbox';
                  name: KeysOfType<Values, boolean>;
              }
            | {
                  type: 'richText';
                  name: KeysOfType<Values, RichTextDocument>;
                  /** Translated toolbar/link-dialog labels. */
                  labels: RichTextFieldLabels;
              }
        );

export type ResourceFormSection<Values extends ResourceFormValues> = {
    id: string;
    title: string;
    description?: string;
    fields: ResourceFormField<Values>[];
};

export type ResourceFormLabels = {
    submit: string;
    cancel?: string;
    /** Heading of the error summary, e.g. "Please correct the highlighted fields". */
    errorSummaryTitle: string;
    /** Confirmation shown while `recentlySuccessful` is true. */
    saved?: string;
};

export type ResourceFormProps<Values extends ResourceFormValues> = {
    /** Shared create/edit definition; fields render in declaration order. */
    sections: ResourceFormSection<Values>[];
    values: Values;
    /** Server (422) or client messages keyed by field name, e.g. Inertia `form.errors`. */
    errors?: Partial<Record<string, string>>;
    onChange: <Key extends keyof Values & string>(
        name: Key,
        value: Values[Key],
    ) => void;
    /** Issue the request, e.g. `form.submit(update(user))`. Never called while pending. */
    onSubmit: () => void;
    /** Typically Inertia `form.processing`. */
    isPending?: boolean;
    /** Typically Inertia `form.recentlySuccessful`. */
    recentlySuccessful?: boolean;
    labels: ResourceFormLabels;
    cancelHref?: ButtonProps['href'];
    onCancel?: () => void;
    className?: never;
    style?: never;
};

/**
 * Create/edit form composed from one field definition: sections, typed
 * fields with inline errors, an `ErrorSummary` that moves focus to the first
 * invalid field after a failed submit, and pending-safe actions.
 */
export function ResourceForm<Values extends ResourceFormValues>({
    sections,
    values,
    errors = {},
    onChange,
    onSubmit,
    isPending = false,
    recentlySuccessful = false,
    labels,
    cancelHref,
    onCancel,
}: ResourceFormProps<Values>) {
    const formId = useId();
    const submitLockRef = useRef(false);

    useEffect(() => {
        if (!isPending) {
            submitLockRef.current = false;
        }
    });

    const fieldId = (name: string) => `${formId}-${name}`;

    const summaryItems: ErrorSummaryItem[] = sections.flatMap((section) =>
        section.fields.flatMap((field) => {
            const message = errors[field.name];

            return message
                ? [
                      {
                          fieldId: fieldId(field.name),
                          label: field.label,
                          message,
                      },
                  ]
                : [];
        }),
    );

    function renderField(field: ResourceFormField<Values>) {
        const common = {
            id: fieldId(field.name),
            name: field.name,
            label: field.label,
            description: field.hint,
            error: errors[field.name],
            disabled: isPending || field.disabled,
        };

        switch (field.type) {
            case 'text':
                return (
                    <TextField
                        key={field.name}
                        {...common}
                        type={field.inputType}
                        autoComplete={field.autoComplete}
                        placeholder={field.placeholder}
                        required={field.required}
                        value={stringValue(values[field.name])}
                        onChange={(value) =>
                            onChange(
                                field.name,
                                value as Values[typeof field.name],
                            )
                        }
                    />
                );
            case 'textarea':
                return (
                    <TextareaField
                        key={field.name}
                        {...common}
                        rows={field.rows}
                        placeholder={field.placeholder}
                        required={field.required}
                        value={stringValue(values[field.name])}
                        onChange={(value) =>
                            onChange(
                                field.name,
                                value as Values[typeof field.name],
                            )
                        }
                    />
                );
            case 'select':
                return (
                    <SelectField
                        key={field.name}
                        {...common}
                        options={field.options}
                        placeholder={field.placeholder}
                        required={field.required}
                        value={stringValue(values[field.name])}
                        onChange={(value) =>
                            onChange(
                                field.name,
                                value as Values[typeof field.name],
                            )
                        }
                    />
                );
            case 'switch':
                return (
                    <SwitchField
                        key={field.name}
                        {...common}
                        checked={values[field.name] === true}
                        onChange={(checked) =>
                            onChange(
                                field.name,
                                checked as Values[typeof field.name],
                            )
                        }
                    />
                );
            case 'checkbox':
                return (
                    <CheckboxField
                        key={field.name}
                        {...common}
                        required={field.required}
                        checked={values[field.name] === true}
                        onChange={(checked) =>
                            onChange(
                                field.name,
                                checked as Values[typeof field.name],
                            )
                        }
                    />
                );
            case 'richText':
                return (
                    <RichTextField
                        key={field.name}
                        id={common.id}
                        name={common.name}
                        label={common.label}
                        hint={common.description}
                        error={common.error}
                        disabled={common.disabled}
                        required={field.required}
                        labels={field.labels}
                        value={values[field.name] as RichTextDocument}
                        onChange={(value) =>
                            onChange(
                                field.name,
                                value as Values[typeof field.name],
                            )
                        }
                    />
                );
        }
    }

    return (
        <form
            noValidate
            aria-busy={isPending}
            onSubmit={(event) => {
                event.preventDefault();

                if (isPending || submitLockRef.current) {
                    return;
                }

                submitLockRef.current = true;
                onSubmit();
            }}
        >
            <Stack gap="default">
                <ErrorSummary
                    title={labels.errorSummaryTitle}
                    items={summaryItems}
                />

                {recentlySuccessful && labels.saved && (
                    <Alert tone="success" title={labels.saved} />
                )}

                {sections.map((section) => (
                    <FormSection
                        key={section.id}
                        title={section.title}
                        description={section.description}
                    >
                        {section.fields.map(renderField)}
                    </FormSection>
                ))}

                <FormActions>
                    {labels.cancel && (cancelHref || onCancel) && (
                        <Button
                            variant="outline"
                            href={cancelHref}
                            onClick={onCancel}
                            disabled={isPending}
                        >
                            {labels.cancel}
                        </Button>
                    )}
                    <Button type="submit" isPending={isPending}>
                        {labels.submit}
                    </Button>
                </FormActions>
            </Stack>
        </form>
    );
}
