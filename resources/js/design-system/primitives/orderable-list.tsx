import { ArrowDown, ArrowUp } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from '@/i18n';
import { IconButton } from './icon-button';

export type OrderableListItem = {
    id: string | number;
    label: string;
    /** Secondary line, e.g. a type or status. */
    meta?: string;
};

export type OrderableMoveDirection = 'up' | 'down';

export type OrderableListProps = {
    items: OrderableListItem[];
    /** Accessible name of the list, e.g. "Home page sections". */
    label: string;
    /**
     * Called with the full new order of ids and the move that produced it.
     * The list is controlled: render the reordered `items` to apply it.
     */
    onReorder: (
        ids: OrderableListItem['id'][],
        move: {
            id: OrderableListItem['id'];
            direction: OrderableMoveDirection;
        },
    ) => void;
    disabled?: boolean;
    className?: never;
    style?: never;
};

type PendingFocus = {
    id: OrderableListItem['id'];
    direction: OrderableMoveDirection;
};

/**
 * Keyboard- and screen-reader-friendly reordering with move up/down buttons
 * (no drag and drop). Each move is announced in a polite live region; the
 * buttons at the ends of the list are disabled and focus follows the moved
 * item.
 */
export function OrderableList({
    items,
    label,
    onReorder,
    disabled = false,
}: OrderableListProps) {
    const { t } = useTranslation();
    const listRef = useRef<HTMLOListElement>(null);
    const pendingFocus = useRef<PendingFocus | null>(null);
    const [announcement, setAnnouncement] = useState('');

    useEffect(() => {
        const pending = pendingFocus.current;
        if (!pending || !listRef.current) {
            return;
        }
        pendingFocus.current = null;

        const index = items.findIndex((item) => item.id === pending.id);
        const row = listRef.current.children.item(index);
        const [upButton, downButton] = Array.from(
            row?.querySelectorAll('button') ?? [],
        );
        const preferred = pending.direction === 'up' ? upButton : downButton;
        const fallback = pending.direction === 'up' ? downButton : upButton;
        (preferred && !preferred.disabled ? preferred : fallback)?.focus();
    }, [items]);

    function move(index: number, direction: OrderableMoveDirection) {
        const target = direction === 'up' ? index - 1 : index + 1;
        if (target < 0 || target >= items.length) {
            return;
        }

        const ids = items.map((item) => item.id);
        const [movedId] = ids.splice(index, 1);
        ids.splice(target, 0, movedId);

        pendingFocus.current = { id: movedId, direction };
        setAnnouncement(
            t('orderable.moved', {
                label: items[index].label,
                position: target + 1,
                total: items.length,
            }),
        );
        onReorder(ids, { id: movedId, direction });
    }

    return (
        <div>
            <ol
                ref={listRef}
                aria-label={label}
                className="border-border-subtle divide-border-subtle divide-y rounded-lg border"
            >
                {items.map((item, index) => (
                    <li
                        key={item.id}
                        className="flex items-center justify-between gap-3 px-4 py-2"
                    >
                        <div className="flex min-w-0 flex-col">
                            <span className="text-foreground truncate text-sm font-medium">
                                {item.label}
                            </span>
                            {item.meta && (
                                <span className="text-muted-foreground truncate text-xs">
                                    {item.meta}
                                </span>
                            )}
                        </div>
                        <div className="flex shrink-0 items-center gap-1">
                            <IconButton
                                icon={ArrowUp}
                                size="sm"
                                ariaLabel={t('orderable.moveUp', {
                                    label: item.label,
                                })}
                                disabled={disabled || index === 0}
                                onClick={() => move(index, 'up')}
                            />
                            <IconButton
                                icon={ArrowDown}
                                size="sm"
                                ariaLabel={t('orderable.moveDown', {
                                    label: item.label,
                                })}
                                disabled={
                                    disabled || index === items.length - 1
                                }
                                onClick={() => move(index, 'down')}
                            />
                        </div>
                    </li>
                ))}
            </ol>
            <p aria-live="polite" className="sr-only">
                {announcement}
            </p>
        </div>
    );
}
