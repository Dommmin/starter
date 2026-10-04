import * as CheckboxPrimitive from '@radix-ui/react-checkbox';
import { Check, Minus } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Internal checkbox box shared by `CheckboxGroupField` and the row selection
 * of `ResourceTable`. Not exported from the design-system barrel: screens use
 * the field primitives, which own the label, description and error.
 */
export type CheckboxControlProps = {
    id?: string;
    name?: string;
    value?: string;
    checked: boolean | 'indeterminate';
    onChange: (checked: boolean) => void;
    disabled?: boolean;
    invalid?: boolean;
    /** Accessible name when no `<label htmlFor>` points at the control. */
    label?: string;
    describedBy?: string;
};

export function CheckboxControl({
    id,
    name,
    value,
    checked,
    onChange,
    disabled = false,
    invalid = false,
    label,
    describedBy,
}: CheckboxControlProps) {
    return (
        <CheckboxPrimitive.Root
            id={id}
            name={name}
            value={value}
            checked={checked}
            onCheckedChange={(state) => onChange(state === true)}
            disabled={disabled}
            aria-label={label}
            aria-describedby={describedBy}
            aria-invalid={invalid ? true : undefined}
            className={cn(
                'border-input data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=checked]:border-primary',
                'data-[state=indeterminate]:bg-primary data-[state=indeterminate]:text-primary-foreground data-[state=indeterminate]:border-primary',
                'focus-visible:ring-ring/50 mt-0.5 size-5 shrink-0 rounded-[4px] border shadow-xs outline-none focus-visible:ring-[3px]',
                'disabled:cursor-not-allowed disabled:opacity-50',
                invalid && 'border-destructive',
            )}
        >
            <CheckboxPrimitive.Indicator className="flex items-center justify-center text-current">
                {checked === 'indeterminate' ? (
                    <Minus className="size-3.5" aria-hidden="true" />
                ) : (
                    <Check className="size-3.5" aria-hidden="true" />
                )}
            </CheckboxPrimitive.Indicator>
        </CheckboxPrimitive.Root>
    );
}
