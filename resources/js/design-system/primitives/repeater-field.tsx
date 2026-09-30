import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from '@/i18n';
import { Button } from './button';
import { IconButton } from './icon-button';
import { SelectField, type SelectFieldOption } from './select-field';
import { TextField } from './text-field';
import { TextareaField } from './textarea-field';

/** One repeated item: flat string values keyed by item field name. */
export type RepeaterItem = Record<string, string>;

export type RepeaterItemField =
    | {
          type: 'text';
          name: string;
          label: string;
          hint?: string;
          required?: boolean;
          placeholder?: string;
      }
    | {
          type: 'textarea';
          name: string;
          label: string;
          hint?: string;
          required?: boolean;
          rows?: number;
          placeholder?: string;
      }
    | {
          type: 'select';
          name: string;
          label: string;
          hint?: string;
          required?: boolean;
          options: SelectFieldOption[];
          placeholder?: string;
      };

export type RepeaterFieldLabels = {
    /** Text of the add button, e.g. "Add feature". */
    add: string;
    /** Accessible name of one item, e.g. `Feature ${position}`. */
    item: (position: number) => string;
};

export type RepeaterFieldProps = {
    /** DOM id prefix; item controls get `${id}-${index}-${field}`. */
    id: string;
    name: string;
    label: string;
    hint?: string;
    /** Error of the whole list, e.g. "At most 12 items". */
    error?: string;
    /** Errors keyed `${index}.${field}` (Laravel `items.0.title` without the list name). */
    itemErrors?: Partial<Record<string, string>>;
    items: RepeaterItem[];
    itemFields: RepeaterItemField[];
    /** Blank item appended by the add button. */
    newItem: () => RepeaterItem;
    maxItems: number;
    labels: RepeaterFieldLabels;
    disabled?: boolean;
    onChange: (items: RepeaterItem[]) => void;
    className?: never;
    style?: never;
};

type PendingFocus =
    | { kind: 'move'; key: string; direction: 'up' | 'down' }
    | { kind: 'added'; key: string }
    | { kind: 'removed'; index: number };

/** Editable controls of an item (a select trigger is a combobox button). */
const ITEM_CONTROLS = 'input, textarea, button[role="combobox"]';

let keySequence = 0;

function nextKey(): string {
    keySequence += 1;

    return `item-${keySequence}`;
}

/**
 * Editable list of structured items for `ResourceForm`: add (up to
 * `maxItems`), remove and move up/down with buttons (no drag and drop).
 * Every item is a labelled group, every control has its own label and
 * inline error, changes are announced in a polite live region and focus
 * follows the affected item.
 */
export function RepeaterField({
    id,
    name,
    label,
    hint,
    error,
    itemErrors = {},
    items,
    itemFields,
    newItem,
    maxItems,
    labels,
    disabled = false,
    onChange,
}: RepeaterFieldProps) {
    const { t } = useTranslation();
    const listRef = useRef<HTMLOListElement>(null);
    const addButtonRef = useRef<HTMLDivElement>(null);
    const pendingFocus = useRef<PendingFocus | null>(null);
    const [keys, setKeys] = useState<string[]>(() => items.map(nextKey));
    const [announcement, setAnnouncement] = useState('');

    // Items replaced from outside (reset, reload): new identities.
    const itemKeys =
        keys.length === items.length
            ? keys
            : items.map((_, index) => keys[index] ?? `${id}-${index}`);

    useEffect(() => {
        if (keys.length !== items.length) {
            setKeys(items.map(nextKey));
        }
    }, [items, keys.length]);

    useEffect(() => {
        const pending = pendingFocus.current;
        if (!pending || !listRef.current) {
            return;
        }
        pendingFocus.current = null;

        if (pending.kind === 'removed') {
            const rows = listRef.current.children;
            const row = rows.item(Math.min(pending.index, rows.length - 1));
            const target =
                row?.querySelector<HTMLElement>(ITEM_CONTROLS) ??
                addButtonRef.current?.querySelector<HTMLElement>('button');
            target?.focus();

            return;
        }

        const index = itemKeys.indexOf(pending.key);
        const row = listRef.current.children.item(index);

        if (pending.kind === 'added') {
            row?.querySelector<HTMLElement>(ITEM_CONTROLS)?.focus();

            return;
        }

        const up = row?.querySelector<HTMLButtonElement>(
            '[data-move="up"] button',
        );
        const down = row?.querySelector<HTMLButtonElement>(
            '[data-move="down"] button',
        );
        const preferred = pending.direction === 'up' ? up : down;
        const fallback = pending.direction === 'up' ? down : up;
        (preferred && !preferred.disabled ? preferred : fallback)?.focus();
    }, [itemKeys]);

    const canAdd = !disabled && items.length < maxItems;
    const errorId = error ? `${id}-error` : undefined;
    const hintId = hint ? `${id}-hint` : undefined;

    function update(index: number, field: string, value: string) {
        onChange(
            items.map((item, itemIndex) =>
                itemIndex === index ? { ...item, [field]: value } : item,
            ),
        );
    }

    function add() {
        if (!canAdd) {
            return;
        }
        const key = nextKey();
        setKeys([...itemKeys, key]);
        pendingFocus.current = { kind: 'added', key };
        setAnnouncement(
            t('repeater.added', {
                label: labels.item(items.length + 1),
            }),
        );
        onChange([...items, newItem()]);
    }

    function remove(index: number) {
        setKeys(itemKeys.filter((_, keyIndex) => keyIndex !== index));
        pendingFocus.current = { kind: 'removed', index };
        setAnnouncement(
            t('repeater.removed', { label: labels.item(index + 1) }),
        );
        onChange(items.filter((_, itemIndex) => itemIndex !== index));
    }

    function move(index: number, direction: 'up' | 'down') {
        const target = direction === 'up' ? index - 1 : index + 1;
        if (target < 0 || target >= items.length) {
            return;
        }

        const reorder = <T,>(list: T[]): T[] => {
            const copy = [...list];
            const [moved] = copy.splice(index, 1);
            copy.splice(target, 0, moved);

            return copy;
        };

        pendingFocus.current = {
            kind: 'move',
            key: itemKeys[index],
            direction,
        };
        setKeys(reorder(itemKeys));
        setAnnouncement(
            t('orderable.moved', {
                label: labels.item(index + 1),
                position: target + 1,
                total: items.length,
            }),
        );
        onChange(reorder(items));
    }

    return (
        <fieldset
            id={id}
            tabIndex={-1}
            aria-describedby={
                [hintId, errorId].filter(Boolean).join(' ') || undefined
            }
            aria-invalid={error ? true : undefined}
            className="flex min-w-0 flex-col gap-3 outline-none"
        >
            <legend className="text-sm leading-snug font-medium">
                {label}
            </legend>
            {hint && (
                <p id={hintId} className="text-muted-foreground text-xs">
                    {hint}
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

            <ol ref={listRef} className="flex flex-col gap-3">
                {items.map((item, index) => {
                    const itemLabel = labels.item(index + 1);

                    return (
                        <li
                            key={itemKeys[index]}
                            className="border-border-subtle rounded-lg border p-4"
                        >
                            <fieldset className="flex min-w-0 flex-col gap-4">
                                <legend className="sr-only">{itemLabel}</legend>
                                <div className="flex items-center justify-between gap-2">
                                    <span
                                        aria-hidden="true"
                                        className="text-muted-foreground text-xs font-medium"
                                    >
                                        {itemLabel}
                                    </span>
                                    <div className="flex shrink-0 items-center gap-1">
                                        <span
                                            data-move="up"
                                            className="contents"
                                        >
                                            <IconButton
                                                icon={ArrowUp}
                                                size="sm"
                                                variant="ghost"
                                                ariaLabel={t(
                                                    'orderable.moveUp',
                                                    {
                                                        label: itemLabel,
                                                    },
                                                )}
                                                disabled={
                                                    disabled || index === 0
                                                }
                                                onClick={() =>
                                                    move(index, 'up')
                                                }
                                            />
                                        </span>
                                        <span
                                            data-move="down"
                                            className="contents"
                                        >
                                            <IconButton
                                                icon={ArrowDown}
                                                size="sm"
                                                variant="ghost"
                                                ariaLabel={t(
                                                    'orderable.moveDown',
                                                    {
                                                        label: itemLabel,
                                                    },
                                                )}
                                                disabled={
                                                    disabled ||
                                                    index === items.length - 1
                                                }
                                                onClick={() =>
                                                    move(index, 'down')
                                                }
                                            />
                                        </span>
                                        <IconButton
                                            icon={Trash2}
                                            size="sm"
                                            variant="ghost"
                                            ariaLabel={t('repeater.remove', {
                                                label: itemLabel,
                                            })}
                                            disabled={disabled}
                                            onClick={() => remove(index)}
                                        />
                                    </div>
                                </div>
                                {itemFields.map((field) => {
                                    const fieldId = `${id}-${index}-${field.name}`;
                                    const common = {
                                        id: fieldId,
                                        name: `${name}[${index}][${field.name}]`,
                                        label: field.label,
                                        description: field.hint,
                                        error: itemErrors[
                                            `${index}.${field.name}`
                                        ],
                                        required: field.required,
                                        disabled,
                                        value: item[field.name] ?? '',
                                        onChange: (value: string) =>
                                            update(index, field.name, value),
                                    };

                                    switch (field.type) {
                                        case 'text':
                                            return (
                                                <TextField
                                                    key={field.name}
                                                    {...common}
                                                    placeholder={
                                                        field.placeholder
                                                    }
                                                />
                                            );
                                        case 'textarea':
                                            return (
                                                <TextareaField
                                                    key={field.name}
                                                    {...common}
                                                    rows={field.rows}
                                                    placeholder={
                                                        field.placeholder
                                                    }
                                                />
                                            );
                                        case 'select':
                                            return (
                                                <SelectField
                                                    key={field.name}
                                                    {...common}
                                                    options={field.options}
                                                    placeholder={
                                                        field.placeholder
                                                    }
                                                />
                                            );
                                    }
                                })}
                            </fieldset>
                        </li>
                    );
                })}
            </ol>

            <div ref={addButtonRef} className="flex flex-col items-start gap-1">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!canAdd}
                    onClick={add}
                >
                    <Plus aria-hidden="true" />
                    <span>{labels.add}</span>
                </Button>
                <p className="text-muted-foreground text-xs">
                    {t('repeater.limit', {
                        count: items.length,
                        max: maxItems,
                    })}
                </p>
            </div>

            <p aria-live="polite" className="sr-only">
                {announcement}
            </p>
        </fieldset>
    );
}
