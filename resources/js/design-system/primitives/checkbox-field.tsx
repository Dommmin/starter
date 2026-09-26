import * as CheckboxPrimitive from '@radix-ui/react-checkbox';
import { Check } from 'lucide-react';
import { useId, type Ref } from 'react';
import { cn } from '@/lib/utils';

export type CheckboxFieldProps = {
    name: string;
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    description?: string;
    error?: string;
    required?: boolean;
    disabled?: boolean;
    /**
     * Stable id for the control, e.g. so a parent can link an `ErrorSummary`
     * item to this exact field. Falls back to an internally generated id.
     */
    id?: string;
    ref?: Ref<HTMLButtonElement>;
    className?: never;
    style?: never;
};

export function CheckboxField({
    name,
    label,
    checked,
    onChange,
    description,
    error,
    required = false,
    disabled = false,
    id,
    ref,
}: CheckboxFieldProps) {
    const reactId = useId();
    const inputId = id ?? `${reactId}-input`;
    const descriptionId = description ? `${reactId}-description` : undefined;
    const errorId = error ? `${reactId}-error` : undefined;
    const describedBy =
        [descriptionId, errorId].filter(Boolean).join(' ') || undefined;

    return (
        <div className="flex flex-col gap-1.5">
            <div className="flex items-start gap-2.5">
                <CheckboxPrimitive.Root
                    ref={ref}
                    id={inputId}
                    name={name}
                    checked={checked}
                    onCheckedChange={(state) => onChange(state === true)}
                    disabled={disabled}
                    required={required}
                    aria-describedby={describedBy}
                    aria-invalid={error ? true : undefined}
                    className={cn(
                        'border-input data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=checked]:border-primary',
                        'focus-visible:ring-ring/50 mt-0.5 size-5 shrink-0 rounded-[4px] border shadow-xs outline-none focus-visible:ring-[3px]',
                        'disabled:cursor-not-allowed disabled:opacity-50',
                        error && 'border-destructive',
                    )}
                >
                    <CheckboxPrimitive.Indicator className="flex items-center justify-center text-current">
                        <Check className="size-3.5" aria-hidden="true" />
                    </CheckboxPrimitive.Indicator>
                </CheckboxPrimitive.Root>
                <label
                    htmlFor={inputId}
                    className="text-sm leading-tight font-medium"
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
            </div>
            {description && (
                <p
                    id={descriptionId}
                    className="text-muted-foreground pl-7.5 text-xs"
                >
                    {description}
                </p>
            )}
            {error && (
                <p
                    id={errorId}
                    role="alert"
                    className="text-destructive pl-7.5 text-xs"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
