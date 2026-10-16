import { CheckboxField } from './checkbox-field';
import type { ContactSectionExtraField } from './contact-section';
import { SelectField } from './select-field';
import { TextField } from './text-field';
import { TextareaField } from './textarea-field';

export type ContactExtraFieldsProps = {
    fields: ContactSectionExtraField[];
    /** Element id of a field, shared with the error summary. */
    fieldId: (name: string) => string;
    disabled: boolean;
};

/**
 * Configured extra fields of `ContactSection`, loaded on demand (see there).
 */
export function ContactExtraFields({
    fields,
    fieldId,
    disabled,
}: ContactExtraFieldsProps) {
    return (
        <>
            {fields.map((field) => {
                const common = {
                    id: fieldId(field.name),
                    name: field.name,
                    label: field.label,
                    error: field.error,
                    required: field.required,
                    disabled,
                };

                if (field.type === 'checkbox') {
                    return (
                        <CheckboxField
                            key={field.name}
                            {...common}
                            checked={field.value}
                            onChange={field.onChange}
                        />
                    );
                }

                if (field.type === 'select') {
                    return (
                        <SelectField
                            key={field.name}
                            {...common}
                            value={field.value}
                            onChange={field.onChange}
                            options={field.options ?? []}
                            placeholder={field.placeholder}
                        />
                    );
                }

                return field.type === 'textarea' ? (
                    <TextareaField
                        key={field.name}
                        {...common}
                        value={field.value}
                        onChange={field.onChange}
                    />
                ) : (
                    <TextField
                        key={field.name}
                        {...common}
                        value={field.value}
                        onChange={field.onChange}
                    />
                );
            })}
        </>
    );
}
