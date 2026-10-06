import { useId } from 'react';
import { DateField } from './date-field';

/** Inclusive range of `YYYY-MM-DD` dates; `''` leaves that bound open. */
export type DateRangeValue = {
    from: string;
    to: string;
};

export type DateRangeFieldProps = {
    /** Base name; the inputs are named `{name}_from` and `{name}_to`. */
    name: string;
    /** Group label rendered as the fieldset legend. */
    label: string;
    value: DateRangeValue;
    onChange: (value: DateRangeValue) => void;
    /** Visible labels of the two inputs, e.g. "From" / "To". */
    labels: { from: string; to: string };
    description?: string;
    /** Messages per bound, matching the server keys `{name}_from`/`{name}_to`. */
    errors?: { from?: string; to?: string };
    required?: boolean;
    disabled?: boolean;
    /** Earliest selectable date for both bounds, `YYYY-MM-DD`. */
    min?: string;
    /** Latest selectable date for both bounds, `YYYY-MM-DD`. */
    max?: string;
    className?: never;
    style?: never;
};

/**
 * Two native date inputs under one legend. Each bound limits the other
 * (`to.min = from`, `from.max = to`), so the browser picker cannot produce an
 * inverted range; the server still validates the order. No calendar library.
 */
export function DateRangeField({
    name,
    label,
    value,
    onChange,
    labels,
    description,
    errors,
    required = false,
    disabled = false,
    min,
    max,
}: DateRangeFieldProps) {
    const descriptionId = `${useId()}-description`;

    return (
        <fieldset
            disabled={disabled}
            aria-describedby={description ? descriptionId : undefined}
            className="flex min-w-0 flex-col gap-1.5"
        >
            <legend className="mb-1.5 text-sm leading-snug font-medium">
                {label}
                {required && (
                    <span
                        className="text-status-danger ml-0.5"
                        aria-hidden="true"
                    >
                        *
                    </span>
                )}
            </legend>
            <div className="grid gap-3 sm:grid-cols-2">
                <DateField
                    name={`${name}_from`}
                    label={labels.from}
                    value={value.from}
                    onChange={(from) => onChange({ ...value, from })}
                    error={errors?.from}
                    required={required}
                    disabled={disabled}
                    min={min}
                    max={value.to || max}
                />
                <DateField
                    name={`${name}_to`}
                    label={labels.to}
                    value={value.to}
                    onChange={(to) => onChange({ ...value, to })}
                    error={errors?.to}
                    required={required}
                    disabled={disabled}
                    min={value.from || min}
                    max={max}
                />
            </div>
            {description && (
                <p id={descriptionId} className="text-muted-foreground text-xs">
                    {description}
                </p>
            )}
        </fieldset>
    );
}
