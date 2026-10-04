import { useId } from 'react';
import { CheckboxControl } from './checkbox-control';

export type CheckboxGroupOption = {
    value: string;
    label: string;
    description?: string;
    disabled?: boolean;
};

export type CheckboxGroupFieldProps = {
    name: string;
    label: string;
    /** Checked option values, in option order. */
    value: string[];
    onChange: (value: string[]) => void;
    options: CheckboxGroupOption[];
    description?: string;
    error?: string;
    /** At least one option must be checked; enforced by the server. */
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

/**
 * Several independent choices under one label (e.g. notification channels,
 * tags of a record). Each option is a native-like checkbox: Tab moves between
 * options, Space toggles. The value keeps the order of `options`.
 */
export function CheckboxGroupField({
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
}: CheckboxGroupFieldProps) {
    const generatedId = useId();
    const reactId = id ?? generatedId;
    const groupLabelId = `${reactId}-label`;
    const descriptionId = description ? `${reactId}-description` : undefined;
    const errorId = error ? `${reactId}-error` : undefined;
    const describedBy =
        [descriptionId, errorId].filter(Boolean).join(' ') || undefined;

    const toggle = (optionValue: string, checked: boolean): void => {
        const next = new Set(value);

        if (checked) {
            next.add(optionValue);
        } else {
            next.delete(optionValue);
        }

        onChange(
            options
                .map((option) => option.value)
                .filter((candidate) => next.has(candidate)),
        );
    };

    return (
        <div className="flex flex-col gap-1.5">
            <span
                id={groupLabelId}
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
            </span>
            <div
                role="group"
                aria-labelledby={groupLabelId}
                aria-describedby={describedBy}
                aria-required={required ? true : undefined}
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
                            <CheckboxControl
                                id={optionId}
                                name={`${name}[]`}
                                value={option.value}
                                checked={value.includes(option.value)}
                                onChange={(checked) =>
                                    toggle(option.value, checked)
                                }
                                disabled={disabled || option.disabled}
                                invalid={Boolean(error)}
                            />
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
                    className="text-status-danger text-xs"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
