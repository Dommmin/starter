import { OTPInput, REGEXP_ONLY_DIGITS } from 'input-otp';
import { useId, type Ref } from 'react';
import { cn } from '@/lib/utils';

export type OtpFieldProps = {
    name: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    /** Called once every cell is filled, e.g. to submit the code. */
    onComplete?: (value: string) => void;
    /** Number of digit cells. Defaults to 6 (TOTP). */
    length?: number;
    description?: string;
    /** Server or client validation message; presence marks the field invalid. */
    error?: string;
    required?: boolean;
    disabled?: boolean;
    /** Request in flight: the field is locked and marked busy. */
    isPending?: boolean;
    autoFocus?: boolean;
    /**
     * Stable id of the underlying input, e.g. so `focusFirstError` can move
     * focus back to this field after a 422. Falls back to a generated id.
     */
    id?: string;
    ref?: Ref<HTMLInputElement>;
    className?: never;
    style?: never;
};

/** Keeps only digits, so a pasted `123 456` or `123-456` fills all cells. */
function digitsOnly(pasted: string): string {
    return pasted.replace(/\D/g, '');
}

/**
 * One-time code field (2FA). A single real `<input>` carries the value,
 * `autocomplete="one-time-code"` and `inputmode="numeric"`, so paste, SMS/
 * password-manager autofill, arrows and Backspace behave natively; the cells
 * are its visual rendering only (`aria-hidden`).
 */
export function OtpField({
    name,
    label,
    value,
    onChange,
    onComplete,
    length = 6,
    description,
    error,
    required = false,
    disabled = false,
    isPending = false,
    autoFocus = false,
    id,
    ref,
}: OtpFieldProps) {
    const reactId = useId();
    const inputId = id ?? `${reactId}-input`;
    const descriptionId = description ? `${reactId}-description` : undefined;
    const errorId = error ? `${reactId}-error` : undefined;
    const describedBy =
        [descriptionId, errorId].filter(Boolean).join(' ') || undefined;
    const isLocked = disabled || isPending;

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
            <OTPInput
                ref={ref}
                id={inputId}
                name={name}
                value={value}
                onChange={onChange}
                onComplete={onComplete}
                maxLength={length}
                pattern={REGEXP_ONLY_DIGITS}
                pasteTransformer={digitsOnly}
                inputMode="numeric"
                autoComplete="one-time-code"
                autoFocus={autoFocus}
                required={required}
                disabled={isLocked}
                aria-busy={isPending || undefined}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy}
                noScriptCSSFallback={null}
                containerClassName="flex w-fit items-center has-[:disabled]:opacity-50"
                className="disabled:cursor-not-allowed"
                render={({ slots }) => (
                    <div className="flex items-center gap-2" aria-hidden="true">
                        {slots.map((slot, index) => (
                            <div
                                key={index}
                                className={cn(
                                    'border-input bg-background relative flex h-11 w-10 items-center justify-center rounded-md border text-lg font-medium shadow-xs transition-[color,box-shadow]',
                                    slot.isActive &&
                                        'border-ring ring-ring/50 z-10 ring-[3px]',
                                    error && 'border-destructive',
                                )}
                            >
                                {slot.char}
                                {slot.hasFakeCaret && (
                                    <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
                                        <div className="bg-foreground h-5 w-px" />
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
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
