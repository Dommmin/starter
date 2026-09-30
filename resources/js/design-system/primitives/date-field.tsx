import type { Ref } from 'react';
import { NativeInputField } from './native-input-field';
import type { FieldValueProps } from './field-value';

export type DateFieldProps = FieldValueProps & {
    name: string;
    label: string;
    /** Controlled `YYYY-MM-DD` value; `''` means no date. */
    value: string;
    onBlur?: () => void;
    description?: string;
    /** Server or client validation message; presence marks the field invalid. */
    error?: string;
    required?: boolean;
    disabled?: boolean;
    /** Earliest allowed date, `YYYY-MM-DD`. */
    min?: string;
    /** Latest allowed date, `YYYY-MM-DD`. */
    max?: string;
    /** Stable id, e.g. for an `ErrorSummary` link. */
    id?: string;
    ref?: Ref<HTMLInputElement>;
    className?: never;
    style?: never;
};

/**
 * Native `<input type="date">` field (browser date picker, locale display
 * format, `YYYY-MM-DD` value) with the `TextField` anatomy.
 */
export function DateField({
    name,
    label,
    value,
    onChange,
    onBlur,
    description,
    error,
    required,
    disabled,
    readOnly,
    min,
    max,
    id,
    ref,
}: DateFieldProps) {
    return (
        <NativeInputField
            type="date"
            name={name}
            label={label}
            value={value}
            onChange={onChange}
            onBlur={onBlur}
            description={description}
            error={error}
            required={required}
            disabled={disabled}
            readOnly={readOnly}
            min={min}
            max={max}
            id={id}
            ref={ref}
        />
    );
}
