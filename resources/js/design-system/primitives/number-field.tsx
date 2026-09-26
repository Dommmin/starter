import type { Ref } from 'react';
import { NativeInputField } from './native-input-field';

export type NumberFieldProps = {
    name: string;
    label: string;
    /** Controlled raw input; `''` means no value. Parse it on the server. */
    value: string;
    onChange: (value: string) => void;
    onBlur?: () => void;
    description?: string;
    /** Server or client validation message; presence marks the field invalid. */
    error?: string;
    required?: boolean;
    disabled?: boolean;
    placeholder?: string;
    min?: number;
    max?: number;
    /**
     * Allowed increment, e.g. `1` for integers, `0.01` for two decimals or
     * `'any'`. Integer steps get the numeric keyboard, others the decimal one.
     */
    step?: number | 'any';
    /** Stable id, e.g. for an `ErrorSummary` link. */
    id?: string;
    ref?: Ref<HTMLInputElement>;
    className?: never;
    style?: never;
};

/**
 * Native `<input type="number">` field with the `TextField` anatomy.
 */
export function NumberField({
    name,
    label,
    value,
    onChange,
    onBlur,
    description,
    error,
    required,
    disabled,
    placeholder,
    min,
    max,
    step,
    id,
    ref,
}: NumberFieldProps) {
    const isInteger = typeof step === 'number' && Number.isInteger(step);

    return (
        <NativeInputField
            type="number"
            inputMode={isInteger ? 'numeric' : 'decimal'}
            name={name}
            label={label}
            value={value}
            onChange={onChange}
            onBlur={onBlur}
            description={description}
            error={error}
            required={required}
            disabled={disabled}
            placeholder={placeholder}
            min={min}
            max={max}
            step={step}
            id={id}
            ref={ref}
        />
    );
}
