import * as SelectPrimitive from '@radix-ui/react-select';
import { Check, ChevronDown } from 'lucide-react';
import { useId, type Ref } from 'react';
import { cn } from '@/lib/utils';

export type SelectFieldOption = {
    value: string;
    label: string;
    disabled?: boolean;
};

export type SelectFieldProps = {
    name: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: SelectFieldOption[];
    placeholder?: string;
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

export function SelectField({
    name,
    label,
    value,
    onChange,
    options,
    placeholder,
    description,
    error,
    required = false,
    disabled = false,
    id,
    ref,
}: SelectFieldProps) {
    const reactId = useId();
    const triggerId = id ?? `${reactId}-trigger`;
    const labelId = `${reactId}-label`;
    const descriptionId = description ? `${reactId}-description` : undefined;
    const errorId = error ? `${reactId}-error` : undefined;
    const describedBy =
        [descriptionId, errorId].filter(Boolean).join(' ') || undefined;

    return (
        <div className="flex flex-col gap-1.5">
            <label
                id={labelId}
                htmlFor={triggerId}
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
            <SelectPrimitive.Root
                name={name}
                value={value}
                onValueChange={onChange}
                disabled={disabled}
                required={required}
            >
                <SelectPrimitive.Trigger
                    ref={ref}
                    id={triggerId}
                    aria-labelledby={labelId}
                    aria-describedby={describedBy}
                    aria-invalid={error ? true : undefined}
                    className={cn(
                        'border-input data-[placeholder]:text-muted-foreground flex h-11 w-full items-center justify-between gap-2 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none',
                        'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                        'disabled:pointer-events-none disabled:opacity-50',
                        error &&
                            'border-destructive focus-visible:ring-destructive/20',
                    )}
                >
                    <SelectPrimitive.Value placeholder={placeholder} />
                    <SelectPrimitive.Icon asChild>
                        <ChevronDown
                            className="text-muted-foreground size-4 shrink-0"
                            aria-hidden="true"
                        />
                    </SelectPrimitive.Icon>
                </SelectPrimitive.Trigger>
                <SelectPrimitive.Portal>
                    <SelectPrimitive.Content
                        position="popper"
                        sideOffset={4}
                        className="bg-popover text-popover-foreground border-border-subtle relative z-50 max-h-(--radix-select-content-available-height) min-w-(--radix-select-trigger-width) overflow-hidden rounded-md border shadow-md"
                    >
                        <SelectPrimitive.Viewport className="p-1">
                            {options.map((option) => (
                                <SelectPrimitive.Item
                                    key={option.value}
                                    value={option.value}
                                    disabled={option.disabled}
                                    className={cn(
                                        'focus:bg-surface-subtle relative flex w-full cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-sm outline-none select-none',
                                        'data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
                                    )}
                                >
                                    <SelectPrimitive.ItemText>
                                        {option.label}
                                    </SelectPrimitive.ItemText>
                                    <SelectPrimitive.ItemIndicator className="absolute right-2 flex items-center">
                                        <Check
                                            className="size-3.5"
                                            aria-hidden="true"
                                        />
                                    </SelectPrimitive.ItemIndicator>
                                </SelectPrimitive.Item>
                            ))}
                        </SelectPrimitive.Viewport>
                    </SelectPrimitive.Content>
                </SelectPrimitive.Portal>
            </SelectPrimitive.Root>
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
