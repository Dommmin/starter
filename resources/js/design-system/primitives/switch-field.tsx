import * as SwitchPrimitive from '@radix-ui/react-switch';
import { useId, type Ref } from 'react';
import { cn } from '@/lib/utils';

export type SwitchFieldProps = {
    name: string;
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    description?: string;
    error?: string;
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

export function SwitchField({
    name,
    label,
    checked,
    onChange,
    description,
    error,
    disabled = false,
    id,
    ref,
}: SwitchFieldProps) {
    const reactId = useId();
    const inputId = id ?? `${reactId}-input`;
    const labelId = `${reactId}-label`;
    const descriptionId = description ? `${reactId}-description` : undefined;
    const errorId = error ? `${reactId}-error` : undefined;
    const describedBy =
        [descriptionId, errorId].filter(Boolean).join(' ') || undefined;

    return (
        <div className="flex flex-col gap-1.5">
            <div className="flex items-center justify-between gap-3">
                <label
                    id={labelId}
                    htmlFor={inputId}
                    className="text-sm leading-tight font-medium"
                >
                    {label}
                </label>
                <SwitchPrimitive.Root
                    ref={ref}
                    id={inputId}
                    name={name}
                    checked={checked}
                    onCheckedChange={onChange}
                    disabled={disabled}
                    aria-labelledby={labelId}
                    aria-describedby={describedBy}
                    aria-invalid={error ? true : undefined}
                    className={cn(
                        'bg-input data-[state=checked]:bg-primary focus-visible:ring-ring/50 relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors outline-none focus-visible:ring-[3px]',
                        'disabled:cursor-not-allowed disabled:opacity-50',
                    )}
                >
                    <SwitchPrimitive.Thumb className="bg-background block size-5 translate-x-0.5 rounded-full shadow-sm transition-transform data-[state=checked]:translate-x-[22px]" />
                </SwitchPrimitive.Root>
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
