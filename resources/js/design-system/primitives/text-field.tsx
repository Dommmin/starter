import { useId, type Ref } from 'react';
import { cn } from '@/lib/utils';
import type { FieldValueProps } from './field-value';

type TextFieldType = 'text' | 'email' | 'url' | 'tel' | 'search';

export type TextFieldProps = FieldValueProps & {
    name: string;
    label: string;
    value: string;
    onBlur?: () => void;
    type?: TextFieldType;
    description?: string;
    /** Server or client validation message; presence marks the field invalid. */
    error?: string;
    required?: boolean;
    disabled?: boolean;
    placeholder?: string;
    autoComplete?: string;
    /**
     * Stable id for the control, e.g. so a parent can link an `ErrorSummary`
     * item to this exact field. Falls back to an internally generated id.
     */
    id?: string;
    ref?: Ref<HTMLInputElement>;
    className?: never;
    style?: never;
};

export function TextField({
    name,
    label,
    value,
    onChange,
    onBlur,
    type = 'text',
    description,
    error,
    required = false,
    disabled = false,
    readOnly = false,
    placeholder,
    autoComplete,
    id,
    ref,
}: TextFieldProps) {
    const reactId = useId();
    const isRequired = required && !readOnly;
    const inputId = id ?? `${reactId}-input`;
    const descriptionId = description ? `${reactId}-description` : undefined;
    const errorId = error ? `${reactId}-error` : undefined;
    const describedBy =
        [descriptionId, errorId].filter(Boolean).join(' ') || undefined;

    return (
        <div className="flex flex-col gap-1.5">
            <label
                htmlFor={inputId}
                className="text-sm leading-snug font-medium"
            >
                {label}
                {isRequired && (
                    <span
                        className="text-status-danger ml-0.5"
                        aria-hidden="true"
                    >
                        *
                    </span>
                )}
            </label>
            <input
                ref={ref}
                id={inputId}
                name={name}
                type={type}
                value={value}
                onChange={(event) => onChange?.(event.target.value)}
                onBlur={onBlur}
                placeholder={placeholder}
                autoComplete={autoComplete}
                disabled={disabled}
                readOnly={readOnly}
                required={isRequired}
                aria-describedby={describedBy}
                aria-invalid={error ? true : undefined}
                className={cn(
                    'border-input placeholder:text-muted-foreground flex h-11 w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none',
                    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                    'disabled:pointer-events-none disabled:opacity-50',
                    readOnly && 'bg-surface-subtle cursor-default',
                    error &&
                        'border-destructive focus-visible:ring-destructive/20',
                )}
            />
            {description && (
                <p id={descriptionId} className="text-muted-foreground text-xs">
                    {description}
                </p>
            )}
            {error && (
                <p
                    id={errorId}
                    role="alert"
                    className="text-status-danger text-xs"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
