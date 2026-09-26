import { Eye, EyeOff } from 'lucide-react';
import { useId, useState, type Ref } from 'react';
import { cn } from '@/lib/utils';

export type PasswordFieldProps = {
    name: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    onBlur?: () => void;
    description?: string;
    /** Server or client validation message; presence marks the field invalid. */
    error?: string;
    required?: boolean;
    disabled?: boolean;
    autoComplete?: string;
    /** Translated label for the reveal-toggle when the password is hidden. */
    showPasswordLabel: string;
    /** Translated label for the reveal-toggle when the password is shown. */
    hidePasswordLabel: string;
    /**
     * Stable id for the control, e.g. so a parent can link an `ErrorSummary`
     * item to this exact field. Falls back to an internally generated id.
     */
    id?: string;
    ref?: Ref<HTMLInputElement>;
    className?: never;
    style?: never;
};

export function PasswordField({
    name,
    label,
    value,
    onChange,
    onBlur,
    description,
    error,
    required = false,
    disabled = false,
    autoComplete = 'current-password',
    showPasswordLabel,
    hidePasswordLabel,
    id,
    ref,
}: PasswordFieldProps) {
    const [visible, setVisible] = useState(false);
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
                className="text-sm leading-none font-medium"
            >
                {label}
                {required && (
                    <span
                        className="text-destructive ml-0.5"
                        aria-hidden="true"
                    >
                        *
                    </span>
                )}
            </label>
            <div className="relative">
                <input
                    ref={ref}
                    id={inputId}
                    name={name}
                    type={visible ? 'text' : 'password'}
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    onBlur={onBlur}
                    autoComplete={autoComplete}
                    disabled={disabled}
                    required={required}
                    aria-describedby={describedBy}
                    aria-invalid={error ? true : undefined}
                    className={cn(
                        'border-input flex h-11 w-full min-w-0 rounded-md border bg-transparent py-2 pr-11 pl-3 text-sm shadow-xs transition-[color,box-shadow] outline-none',
                        'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                        'disabled:pointer-events-none disabled:opacity-50',
                        error &&
                            'border-destructive focus-visible:ring-destructive/20',
                    )}
                />
                <button
                    type="button"
                    onClick={() => setVisible((prev) => !prev)}
                    disabled={disabled}
                    aria-label={visible ? hidePasswordLabel : showPasswordLabel}
                    aria-pressed={visible}
                    className="text-muted-foreground hover:text-foreground focus-visible:ring-ring absolute inset-y-0 right-0 flex items-center rounded-r-md px-3 focus-visible:ring-2 focus-visible:outline-none"
                >
                    {visible ? (
                        <EyeOff className="size-4" aria-hidden="true" />
                    ) : (
                        <Eye className="size-4" aria-hidden="true" />
                    )}
                </button>
            </div>
            {description && (
                <p id={descriptionId} className="text-muted-foreground text-xs">
                    {description}
                </p>
            )}
            {error && (
                <p
                    id={errorId}
                    role="alert"
                    className="text-destructive text-xs"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
