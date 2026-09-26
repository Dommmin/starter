import * as RadioGroupPrimitive from '@radix-ui/react-radio-group';
import { useId } from 'react';
import { cn } from '@/lib/utils';

export type RadioGroupOption = {
    value: string;
    label: string;
    description?: string;
    disabled?: boolean;
};

export type RadioGroupFieldProps = {
    name: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: RadioGroupOption[];
    description?: string;
    error?: string;
    required?: boolean;
    disabled?: boolean;
    /**
     * Stable id prefix for the group's options, e.g. so a parent can link an
     * `ErrorSummary` item to this field's first option. Falls back to an
     * internally generated id.
     */
    id?: string;
    className?: never;
    style?: never;
};

export function RadioGroupField({
    name,
    label,
    value,
    onChange,
    options,
    description,
    error,
    required = false,
    disabled = false,
    id,
}: RadioGroupFieldProps) {
    const generatedId = useId();
    const reactId = id ?? generatedId;
    const groupLabelId = `${reactId}-label`;
    const descriptionId = description ? `${reactId}-description` : undefined;
    const errorId = error ? `${reactId}-error` : undefined;
    const describedBy =
        [descriptionId, errorId].filter(Boolean).join(' ') || undefined;

    return (
        <div className="flex flex-col gap-1.5">
            <span
                id={groupLabelId}
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
            </span>
            <RadioGroupPrimitive.Root
                value={value}
                onValueChange={onChange}
                name={name}
                disabled={disabled}
                required={required}
                aria-labelledby={groupLabelId}
                aria-describedby={describedBy}
                aria-invalid={error ? true : undefined}
                className="flex flex-col gap-2.5"
            >
                {options.map((option) => {
                    const optionId = `${reactId}-${option.value}`;

                    return (
                        <div
                            key={option.value}
                            className="flex items-start gap-2.5"
                        >
                            <RadioGroupPrimitive.Item
                                id={optionId}
                                value={option.value}
                                disabled={option.disabled}
                                className={cn(
                                    'border-input data-[state=checked]:border-primary mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border shadow-xs outline-none',
                                    'focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                                    'disabled:cursor-not-allowed disabled:opacity-50',
                                    error && 'border-destructive',
                                )}
                            >
                                <RadioGroupPrimitive.Indicator className="bg-primary size-2.5 rounded-full" />
                            </RadioGroupPrimitive.Item>
                            <label
                                htmlFor={optionId}
                                className="flex flex-col gap-0.5"
                            >
                                <span className="text-sm leading-tight font-medium">
                                    {option.label}
                                </span>
                                {option.description && (
                                    <span className="text-muted-foreground text-xs">
                                        {option.description}
                                    </span>
                                )}
                            </label>
                        </div>
                    );
                })}
            </RadioGroupPrimitive.Root>
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
