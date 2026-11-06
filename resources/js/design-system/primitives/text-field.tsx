import { useId, useRef, type Ref } from 'react';
import { cn } from '@/lib/utils';
import { useFieldAssist, type FieldAssist } from './field-assist';
import type { FieldValueProps } from './field-value';

export type { FieldAssist, FieldAssistResult } from './field-assist';

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
    /** Moves focus into the field on mount, e.g. a field revealed by the user's own action. */
    autoFocus?: boolean;
    /**
     * Stable id for the control, e.g. so a parent can link an `ErrorSummary`
     * item to this exact field. Falls back to an internally generated id.
     */
    id?: string;
    ref?: Ref<HTMLInputElement>;
    /** Optional "suggest" helper; the suggestion is applied only on "use". */
    assist?: FieldAssist;
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
    autoFocus,
    id,
    ref,
    assist,
}: TextFieldProps) {
    const reactId = useId();
    const isRequired = required && !readOnly;
    const inputId = id ?? `${reactId}-input`;
    const descriptionId = description ? `${reactId}-description` : undefined;
    const errorId = error ? `${reactId}-error` : undefined;
    const describedBy =
        [descriptionId, errorId].filter(Boolean).join(' ') || undefined;
    const controlRef = useRef<HTMLInputElement | null>(null);
    const assistControls = useFieldAssist({
        assist: readOnly ? undefined : assist,
        disabled,
        onApply: (suggestion) => onChange?.(suggestion),
        focusControl: () => controlRef.current?.focus(),
        panelId: `${reactId}-assist`,
    });

    function setRefs(node: HTMLInputElement | null) {
        controlRef.current = node;

        if (typeof ref === 'function') {
            ref(node);
        } else if (ref) {
            ref.current = node;
        }
    }

    const labelElement = (
        <label htmlFor={inputId} className="text-sm leading-snug font-medium">
            {label}
            {isRequired && (
                <span className="text-status-danger ml-0.5" aria-hidden="true">
                    *
                </span>
            )}
        </label>
    );

    return (
        <div className="flex flex-col gap-1.5">
            {assistControls.trigger ? (
                <div className="flex flex-wrap items-center justify-between gap-x-2">
                    {labelElement}
                    {assistControls.trigger}
                </div>
            ) : (
                labelElement
            )}
            <input
                ref={setRefs}
                id={inputId}
                name={name}
                type={type}
                value={value}
                onChange={(event) => onChange?.(event.target.value)}
                onBlur={onBlur}
                placeholder={placeholder}
                autoComplete={autoComplete}
                autoFocus={autoFocus}
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
            {assistControls.panel}
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
