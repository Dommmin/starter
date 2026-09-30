import { useId, type HTMLAttributes, type Ref } from 'react';
import { cn } from '@/lib/utils';

/**
 * Internal field anatomy shared by `NumberField` and `DateField`: label,
 * native input, description and error linked through `aria-describedby`,
 * with the same tokens as `TextField`. Not part of the public API.
 */
export type NativeInputFieldProps = {
    type: 'number' | 'date';
    name: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    onBlur?: () => void;
    description?: string;
    error?: string;
    required?: boolean;
    disabled?: boolean;
    placeholder?: string;
    min?: number | string;
    max?: number | string;
    step?: number | 'any';
    inputMode?: HTMLAttributes<HTMLInputElement>['inputMode'];
    id?: string;
    ref?: Ref<HTMLInputElement>;
};

export function NativeInputField({
    type,
    name,
    label,
    value,
    onChange,
    onBlur,
    description,
    error,
    required = false,
    disabled = false,
    placeholder,
    min,
    max,
    step,
    inputMode,
    id,
    ref,
}: NativeInputFieldProps) {
    const reactId = useId();
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
                {required && (
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
                onChange={(event) => onChange(event.target.value)}
                onBlur={onBlur}
                placeholder={placeholder}
                min={min}
                max={max}
                step={step}
                inputMode={inputMode}
                disabled={disabled}
                required={required}
                aria-describedby={describedBy}
                aria-invalid={error ? true : undefined}
                className={cn(
                    'border-input placeholder:text-muted-foreground flex h-11 w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none',
                    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                    'disabled:pointer-events-none disabled:opacity-50',
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
