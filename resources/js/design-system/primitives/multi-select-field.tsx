import * as PopoverPrimitive from '@radix-ui/react-popover';
import { Check, ChevronDown, X } from 'lucide-react';
import {
    useId,
    useMemo,
    useRef,
    useState,
    type KeyboardEvent,
    type Ref,
} from 'react';
import { cn } from '@/lib/utils';

export type MultiSelectOption = {
    value: string;
    label: string;
    disabled?: boolean;
};

export type MultiSelectFieldLabels = {
    /** Shown in the list when the query matches no option. */
    noResults: string;
    /** Accessible name of a chip's remove button, e.g. `Remove ${label}`. */
    remove: (label: string) => string;
    /** Polite announcement after the selection changes, e.g. `3 selected`. */
    selected: (count: number) => string;
};

export type MultiSelectFieldProps = {
    name: string;
    label: string;
    /** Selected option values, in selection order. */
    value: string[];
    onChange: (value: string[]) => void;
    /** Static options; filtered on the client by the typed query. */
    options: MultiSelectOption[];
    labels: MultiSelectFieldLabels;
    placeholder?: string;
    description?: string;
    error?: string;
    required?: boolean;
    disabled?: boolean;
    /** Upper bound of selected values; further options become unavailable. */
    max?: number;
    /**
     * Stable id for the text input, e.g. so a parent can link an
     * `ErrorSummary` item to this field. Falls back to a generated id.
     */
    id?: string;
    ref?: Ref<HTMLInputElement>;
    className?: never;
    style?: never;
};

function normalize(text: string): string {
    return text
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLocaleLowerCase();
}

/**
 * Searchable multiple choice (WAI-ARIA combobox with a multi-selectable
 * listbox). Focus stays in the text input: arrows move the active option,
 * Enter toggles it, Escape closes the list, Backspace on an empty query
 * removes the last chip. Selected values render as removable chips.
 */
export function MultiSelectField({
    name,
    label,
    value,
    onChange,
    options,
    labels,
    placeholder,
    description,
    error,
    required = false,
    disabled = false,
    max,
    id,
    ref,
}: MultiSelectFieldProps) {
    const reactId = useId();
    const inputId = id ?? `${reactId}-input`;
    const labelId = `${reactId}-label`;
    const listboxId = `${reactId}-listbox`;
    const descriptionId = description ? `${reactId}-description` : undefined;
    const errorId = error ? `${reactId}-error` : undefined;
    const describedBy =
        [descriptionId, errorId].filter(Boolean).join(' ') || undefined;

    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [activeIndex, setActiveIndex] = useState(0);
    const [announcement, setAnnouncement] = useState('');
    const inputRef = useRef<HTMLInputElement | null>(null);
    const anchorRef = useRef<HTMLDivElement | null>(null);

    const optionsByValue = useMemo(
        () => new Map(options.map((option) => [option.value, option])),
        [options],
    );
    const visibleOptions = useMemo(() => {
        const needle = normalize(query.trim());

        return needle === ''
            ? options
            : options.filter((option) =>
                  normalize(option.label).includes(needle),
              );
    }, [options, query]);
    const isFull = max !== undefined && value.length >= max;
    const isUnavailable = (option: MultiSelectOption): boolean =>
        Boolean(option.disabled) || (isFull && !value.includes(option.value));
    const optionId = (index: number): string => `${reactId}-option-${index}`;
    const activeOption = open ? visibleOptions[activeIndex] : undefined;

    const commit = (next: string[]): void => {
        onChange(next);
        setAnnouncement(labels.selected(next.length));
    };

    const toggle = (option: MultiSelectOption): void => {
        if (value.includes(option.value)) {
            commit(value.filter((selected) => selected !== option.value));
        } else if (!isUnavailable(option)) {
            commit([...value, option.value]);
        }
    };

    const remove = (optionValue: string): void => {
        commit(value.filter((selected) => selected !== optionValue));
        inputRef.current?.focus();
    };

    const moveActive = (delta: number): void => {
        if (!open) {
            setOpen(true);

            return;
        }

        if (visibleOptions.length === 0) {
            return;
        }

        setActiveIndex(
            (current) =>
                (current + delta + visibleOptions.length) %
                visibleOptions.length,
        );
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>): void => {
        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                moveActive(1);
                break;
            case 'ArrowUp':
                event.preventDefault();
                moveActive(-1);
                break;
            case 'Home':
                if (open) {
                    event.preventDefault();
                    setActiveIndex(0);
                }

                break;
            case 'End':
                if (open) {
                    event.preventDefault();
                    setActiveIndex(Math.max(visibleOptions.length - 1, 0));
                }

                break;
            case 'Enter':
                if (open && activeOption) {
                    event.preventDefault();
                    toggle(activeOption);
                }

                break;
            case 'Escape':
                if (open) {
                    event.preventDefault();
                    setOpen(false);
                }

                break;
            case 'Backspace':
                if (query === '' && value.length > 0) {
                    remove(value[value.length - 1]);
                }

                break;
        }
    };

    return (
        <div className="flex flex-col gap-1.5">
            <label
                id={labelId}
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
            <PopoverPrimitive.Root open={open} onOpenChange={setOpen}>
                <PopoverPrimitive.Anchor asChild>
                    <div
                        ref={anchorRef}
                        className={cn(
                            'border-input flex min-h-11 w-full flex-wrap items-center gap-1.5 rounded-md border bg-transparent px-2 py-1.5 text-sm shadow-xs transition-[color,box-shadow]',
                            'focus-within:border-ring focus-within:ring-ring/50 focus-within:ring-[3px]',
                            disabled && 'pointer-events-none opacity-50',
                            error &&
                                'border-destructive focus-within:ring-destructive/20',
                        )}
                    >
                        {value.map((selected) => {
                            const optionLabel =
                                optionsByValue.get(selected)?.label ?? selected;

                            return (
                                <span
                                    key={selected}
                                    className="bg-surface-subtle text-foreground inline-flex max-w-full min-w-0 items-center gap-1 rounded-sm py-0.5 pr-0.5 pl-2 text-xs font-medium"
                                >
                                    <span className="truncate">
                                        {optionLabel}
                                    </span>
                                    {!disabled && (
                                        <button
                                            type="button"
                                            onClick={() => remove(selected)}
                                            aria-label={labels.remove(
                                                optionLabel,
                                            )}
                                            className="text-muted-foreground hover:text-foreground focus-visible:ring-ring inline-flex size-6 shrink-0 items-center justify-center rounded-sm outline-none focus-visible:ring-2"
                                        >
                                            <X
                                                className="size-3.5"
                                                aria-hidden="true"
                                            />
                                        </button>
                                    )}
                                </span>
                            );
                        })}
                        <input
                            ref={(node) => {
                                inputRef.current = node;

                                if (typeof ref === 'function') {
                                    ref(node);
                                } else if (ref) {
                                    ref.current = node;
                                }
                            }}
                            id={inputId}
                            type="text"
                            role="combobox"
                            autoComplete="off"
                            aria-autocomplete="list"
                            aria-expanded={open}
                            aria-controls={listboxId}
                            aria-activedescendant={
                                activeOption ? optionId(activeIndex) : undefined
                            }
                            aria-describedby={describedBy}
                            aria-invalid={error ? true : undefined}
                            aria-required={required ? true : undefined}
                            value={query}
                            placeholder={
                                value.length === 0 ? placeholder : undefined
                            }
                            disabled={disabled}
                            onChange={(event) => {
                                setQuery(event.target.value);
                                setActiveIndex(0);
                                setOpen(true);
                            }}
                            onClick={() => setOpen(true)}
                            onKeyDown={onKeyDown}
                            className="placeholder:text-muted-foreground h-7 min-w-24 flex-1 bg-transparent px-1 outline-none"
                        />
                        <ChevronDown
                            className="text-muted-foreground size-4 shrink-0"
                            aria-hidden="true"
                        />
                    </div>
                </PopoverPrimitive.Anchor>
                <PopoverPrimitive.Portal>
                    <PopoverPrimitive.Content
                        align="start"
                        sideOffset={4}
                        onOpenAutoFocus={(event) => event.preventDefault()}
                        onCloseAutoFocus={(event) => event.preventDefault()}
                        onInteractOutside={(event) => {
                            const target = event.target;

                            if (
                                target instanceof Node &&
                                anchorRef.current?.contains(target)
                            ) {
                                event.preventDefault();
                            }
                        }}
                        className="bg-popover text-popover-foreground border-border-subtle z-50 max-h-(--radix-popover-content-available-height) w-(--radix-popover-trigger-width) overflow-y-auto rounded-md border p-1 shadow-md"
                    >
                        <ul
                            id={listboxId}
                            role="listbox"
                            aria-labelledby={labelId}
                            aria-multiselectable="true"
                        >
                            {visibleOptions.map((option, index) => {
                                const isSelected = value.includes(option.value);
                                const unavailable = isUnavailable(option);

                                return (
                                    <li
                                        key={option.value}
                                        id={optionId(index)}
                                        role="option"
                                        aria-selected={isSelected}
                                        aria-disabled={
                                            unavailable ? true : undefined
                                        }
                                        data-active={
                                            index === activeIndex
                                                ? ''
                                                : undefined
                                        }
                                        onMouseDown={(event) =>
                                            event.preventDefault()
                                        }
                                        onMouseMove={() =>
                                            setActiveIndex(index)
                                        }
                                        onClick={() => toggle(option)}
                                        className={cn(
                                            'data-[active]:bg-surface-subtle relative flex w-full cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-sm select-none',
                                            unavailable &&
                                                'pointer-events-none opacity-50',
                                        )}
                                    >
                                        <span className="min-w-0 break-words">
                                            {option.label}
                                        </span>
                                        {isSelected && (
                                            <Check
                                                className="absolute right-2 size-3.5"
                                                aria-hidden="true"
                                            />
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                        {visibleOptions.length === 0 && (
                            <p className="text-muted-foreground px-2 py-1.5 text-sm">
                                {labels.noResults}
                            </p>
                        )}
                    </PopoverPrimitive.Content>
                </PopoverPrimitive.Portal>
            </PopoverPrimitive.Root>
            {value.map((selected) => (
                <input
                    key={selected}
                    type="hidden"
                    name={`${name}[]`}
                    value={selected}
                />
            ))}
            <span role="status" aria-live="polite" className="sr-only">
                {announcement}
            </span>
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
