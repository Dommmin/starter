import { useEffect, useId, useRef } from 'react';
import { Alert } from './alert';
import { Button, type ButtonProps } from './button';
import { CheckboxField } from './checkbox-field';
import { DateField } from './date-field';
import { ErrorSummary, type ErrorSummaryItem } from './error-summary';
import { FormActions } from './form-actions';
import { FormSection } from './form-section';
import {
    MultiSelectField,
    type MultiSelectFieldLabels,
    type MultiSelectOption,
} from './multi-select-field';
import {
    ImagePickerField,
    type ImagePickerFieldLabels,
} from './image-picker-field';
import { NumberField } from './number-field';
import {
    RepeaterField,
    type RepeaterFieldLabels,
    type RepeaterItem,
    type RepeaterItemField,
} from './repeater-field';
import {
    RichTextField,
    type RichTextDocument,
    type RichTextFieldLabels,
    type RichTextImagePicker,
} from './rich-text-field';
import { SelectField, type SelectFieldOption } from './select-field';
import { Stack } from './stack';
import { SwitchField } from './switch-field';
import { TextField } from './text-field';
import { TextareaField } from './textarea-field';
import { useSubmitErrorFocus } from './use-submit-error-focus';

/**
 * Form values handled by `ResourceForm`: flat string/boolean fields (number
 * and date inputs keep their raw string), string lists (multi-select),
 * rich text documents and repeated items (lists of flat string records).
 */
export type ResourceFormValues = Record<
    string,
    string | boolean | string[] | RichTextDocument | ResourceFormRepeaterItem[]
>;

/** One item of a `repeater` field. */
export type ResourceFormRepeaterItem = RepeaterItem;
export type ResourceFormRepeaterItemField = RepeaterItemField;
export type ResourceFormRepeaterLabels = RepeaterFieldLabels;

type KeysOfType<Values, Type> = {
    [Key in keyof Values & string]: Values[Key] extends Type ? Key : never;
}[keyof Values & string];

function stringValue(value: unknown): string {
    return typeof value === 'string' ? value : '';
}

function repeaterItems(value: unknown): ResourceFormRepeaterItem[] {
    return Array.isArray(value)
        ? value.filter(
              (item): item is ResourceFormRepeaterItem =>
                  typeof item === 'object' && item !== null,
          )
        : [];
}

function stringListValue(value: unknown): string[] {
    return Array.isArray(value)
        ? value.filter((item): item is string => typeof item === 'string')
        : [];
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
                  /** Native number input; the value stays a string (`''` = empty). */
                  type: 'number';
                  name: KeysOfType<Values, string>;
                  min?: number;
                  max?: number;
                  /** e.g. `1` for integers, `0.01` for two decimals, `'any'`. */
                  step?: number | 'any';
                  placeholder?: string;
              }
            | {
                  /** Native date input; the value is `YYYY-MM-DD` or `''`. */
                  type: 'date';
                  name: KeysOfType<Values, string>;
                  min?: string;
                  max?: string;
              }
            | {
                  type: 'select';
                  name: KeysOfType<Values, string>;
                  options: SelectFieldOption[];
                  placeholder?: string;
              }
            | {
                  /**
                   * Searchable multiple choice; the value is the list of
                   * selected option values. Item errors
                   * (`errors['<name>.<index>']`) are shown on the field.
                   */
                  type: 'multiSelect';
                  name: KeysOfType<Values, string[]>;
                  options: MultiSelectOption[];
                  labels: MultiSelectFieldLabels;
                  placeholder?: string;
                  max?: number;
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
                  /** Enables inserting images from the DAM picker. */
                  imagePicker?: RichTextImagePicker;
              }
            | {
                  /** One DAM image; the value is the asset id as a string (`''` = none). */
                  type: 'image';
                  name: KeysOfType<Values, string>;
                  picker: RichTextImagePicker;
                  /** Translated choose/change/remove/empty/preview labels. */
                  labels: ImagePickerFieldLabels;
              }
            | {
                  /**
                   * List of structured items (add/remove/move up/down, at
                   * most `maxItems`). Item errors are read from
                   * `errors['<name>.<index>.<field>']`.
                   */
                  type: 'repeater';
                  name: KeysOfType<Values, ResourceFormRepeaterItem[]>;
                  itemFields: ResourceFormRepeaterItemField[];
                  newItem: () => ResourceFormRepeaterItem;
                  maxItems: number;
                  labels: ResourceFormRepeaterLabels;
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
            const own = message
                ? [
                      {
                          fieldId: fieldId(field.name),
                          label: field.label,
                          message,
                      },
                  ]
                : [];

            if (field.type !== 'repeater') {
                return own;
            }

            const nested = repeaterItems(values[field.name]).flatMap(
                (_, index) =>
                    field.itemFields.flatMap((itemField) => {
                        const itemMessage =
                            errors[`${field.name}.${index}.${itemField.name}`];

                        return itemMessage
                            ? [
                                  {
                                      fieldId: `${fieldId(field.name)}-${index}-${itemField.name}`,
                                      label: `${field.labels.item(index + 1)}: ${itemField.label}`,
                                      message: itemMessage,
                                  },
                              ]
                            : [];
                    }),
            );

            return [...own, ...nested];
        }),
    );

    const markSubmitted = useSubmitErrorFocus(
        summaryItems.map((item) => item.fieldId),
        errors,
        isPending,
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
            case 'number':
                return (
                    <NumberField
                        key={field.name}
                        {...common}
                        min={field.min}
                        max={field.max}
                        step={field.step}
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
            case 'date':
                return (
                    <DateField
                        key={field.name}
                        {...common}
                        min={field.min}
                        max={field.max}
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
            case 'multiSelect': {
                const itemError = Object.entries(errors).find(([key]) =>
                    key.startsWith(`${field.name}.`),
                )?.[1];

                return (
                    <MultiSelectField
                        key={field.name}
                        {...common}
                        error={common.error ?? itemError}
                        options={field.options}
                        labels={field.labels}
                        placeholder={field.placeholder}
                        max={field.max}
                        required={field.required}
                        value={stringListValue(values[field.name])}
                        onChange={(value) =>
                            onChange(
                                field.name,
                                value as Values[typeof field.name],
                            )
                        }
                    />
                );
            }
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
            case 'image':
                return (
                    <ImagePickerField
                        key={field.name}
                        id={common.id}
                        name={common.name}
                        label={common.label}
                        hint={common.description}
                        error={common.error}
                        disabled={common.disabled}
                        required={field.required}
                        picker={field.picker}
                        labels={field.labels}
                        value={stringValue(values[field.name])}
                        onChange={(value) =>
                            onChange(
                                field.name,
                                value as Values[typeof field.name],
                            )
                        }
                    />
                );
            case 'repeater': {
                const prefix = `${field.name}.`;
                const itemErrors: Partial<Record<string, string>> = {};
                for (const [key, message] of Object.entries(errors)) {
                    if (key.startsWith(prefix)) {
                        itemErrors[key.slice(prefix.length)] = message;
                    }
                }
                return (
                    <RepeaterField
                        key={field.name}
                        id={common.id}
                        name={common.name}
                        label={common.label}
                        hint={common.description}
                        error={common.error}
                        itemErrors={itemErrors}
                        disabled={common.disabled}
                        items={repeaterItems(values[field.name])}
                        itemFields={field.itemFields}
                        newItem={field.newItem}
                        maxItems={field.maxItems}
                        labels={field.labels}
                        onChange={(next) =>
                            onChange(
                                field.name,
                                next as Values[typeof field.name],
                            )
                        }
                    />
                );
            }
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
                        imagePicker={field.imagePicker}
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
                markSubmitted();
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
