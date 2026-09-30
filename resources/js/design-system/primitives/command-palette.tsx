import type { InertiaLinkProps } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import type { LucideIcon } from 'lucide-react';
import { Search } from 'lucide-react';
import { useId, useMemo, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { useTranslation } from '@/i18n';
import { cn, toUrl } from '@/lib/utils';
import { useReturnFocus } from './return-focus';

export type CommandPaletteItem = {
    id: string;
    label: string;
    /** Visible group heading, e.g. "Navigation". Items are shown in order. */
    group: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
};

export type CommandPaletteProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    items: CommandPaletteItem[];
    className?: never;
    style?: never;
};

function Kbd({ children }: { children: string }) {
    return (
        <kbd className="border-border bg-card text-muted-foreground inline-grid h-[18px] min-w-[18px] place-items-center rounded-[4px] border border-b-2 px-1 font-mono text-[10.5px]">
            {children}
        </kbd>
    );
}

function matches(item: CommandPaletteItem, query: string): boolean {
    return `${item.label} ${item.group}`
        .toLocaleLowerCase()
        .includes(query.toLocaleLowerCase());
}

/**
 * Keyboard-first jump list for the admin panel (⌘K / Ctrl+K). A modal Radix
 * dialog: focus is trapped, Escape closes it and focus returns to the opener.
 * The input is a combobox driving the listbox via aria-activedescendant.
 */
export function CommandPalette({
    open,
    onOpenChange,
    items,
}: CommandPaletteProps) {
    const returnFocus = useReturnFocus();
    const { t } = useTranslation();
    const [query, setQuery] = useState('');
    const [activeIndex, setActiveIndex] = useState(0);
    const listId = useId();
    const optionId = (index: number) => `${listId}-option-${index}`;

    const trimmedQuery = query.trim();
    const visibleItems = useMemo(
        () =>
            trimmedQuery === ''
                ? items
                : items.filter((item) => matches(item, trimmedQuery)),
        [items, trimmedQuery],
    );
    const safeActiveIndex = Math.min(
        activeIndex,
        Math.max(visibleItems.length - 1, 0),
    );

    const handleOpenChange = (nextOpen: boolean) => {
        if (nextOpen) {
            setQuery('');
            setActiveIndex(0);
        }

        onOpenChange(nextOpen);
    };

    const runItem = (item: CommandPaletteItem | undefined) => {
        if (!item) {
            return;
        }

        onOpenChange(false);
        router.visit(toUrl(item.href));
    };

    const moveActive = (index: number) => {
        setActiveIndex(index);
        document
            .getElementById(optionId(index))
            ?.scrollIntoView?.({ block: 'nearest' });
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        const lastIndex = visibleItems.length - 1;

        if (lastIndex < 0) {
            return;
        }

        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                moveActive(Math.min(safeActiveIndex + 1, lastIndex));
                break;
            case 'ArrowUp':
                event.preventDefault();
                moveActive(Math.max(safeActiveIndex - 1, 0));
                break;
            case 'Home':
                event.preventDefault();
                moveActive(0);
                break;
            case 'End':
                event.preventDefault();
                moveActive(lastIndex);
                break;
            case 'Enter':
                event.preventDefault();
                runItem(visibleItems[safeActiveIndex]);
                break;
        }
    };

    return (
        <DialogPrimitive.Root open={open} onOpenChange={handleOpenChange}>
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="bg-overlay data-[state=open]:animate-in data-[state=open]:fade-in-0 fixed inset-0 z-50 motion-reduce:animate-none" />
                <DialogPrimitive.Content
                    onOpenAutoFocus={returnFocus.onOpenAutoFocus}
                    onCloseAutoFocus={returnFocus.onCloseAutoFocus}
                    aria-describedby={undefined}
                    className="border-border bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 fixed top-3 left-1/2 z-50 w-[min(600px,calc(100%-24px))] -translate-x-1/2 overflow-hidden rounded-[calc(var(--radius)+4px)] border shadow-(--admin-shadow-overlay) motion-reduce:animate-none md:top-[88px]"
                >
                    <DialogPrimitive.Title className="sr-only">
                        {t('admin.command.title')}
                    </DialogPrimitive.Title>
                    <div className="border-border flex items-center gap-2.5 border-b px-3.5">
                        <Search
                            className="text-muted-foreground size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <input
                            type="text"
                            role="combobox"
                            aria-expanded="true"
                            aria-controls={listId}
                            aria-autocomplete="list"
                            aria-label={t('admin.command.title')}
                            aria-activedescendant={
                                visibleItems.length > 0
                                    ? optionId(safeActiveIndex)
                                    : undefined
                            }
                            autoComplete="off"
                            spellCheck={false}
                            placeholder={t('admin.command.placeholder')}
                            value={query}
                            onChange={(event) => {
                                setQuery(event.target.value);
                                setActiveIndex(0);
                            }}
                            onKeyDown={handleKeyDown}
                            className="text-foreground placeholder:text-text-subtle h-12 min-w-0 flex-1 bg-transparent text-(length:--admin-text-md) outline-none"
                        />
                        <span className="max-md:hidden">
                            <Kbd>Esc</Kbd>
                        </span>
                    </div>

                    <div
                        id={listId}
                        role="listbox"
                        aria-label={t('admin.command.results')}
                        className="max-h-[340px] overflow-y-auto p-1.5"
                    >
                        {visibleItems.length === 0 ? (
                            <p className="text-muted-foreground p-6 text-center">
                                {t('admin.command.empty', {
                                    query: trimmedQuery,
                                })}
                            </p>
                        ) : (
                            visibleItems.map((item, index) => {
                                const isFirstInGroup =
                                    index === 0 ||
                                    visibleItems[index - 1].group !==
                                        item.group;
                                const isActive = index === safeActiveIndex;
                                const ItemIcon = item.icon;

                                return (
                                    <div key={item.id} role="presentation">
                                        {isFirstInGroup && (
                                            <div
                                                role="presentation"
                                                className="text-text-subtle px-2 pt-2 pb-1 text-xs font-medium"
                                            >
                                                {item.group}
                                            </div>
                                        )}
                                        <div
                                            id={optionId(index)}
                                            role="option"
                                            aria-selected={isActive}
                                            onMouseMove={() => {
                                                if (!isActive) {
                                                    setActiveIndex(index);
                                                }
                                            }}
                                            onClick={() => runItem(item)}
                                            className={cn(
                                                'flex h-[38px] cursor-pointer items-center gap-2.5 rounded-[5px] px-2 max-md:h-11 pointer-coarse:h-11',
                                                isActive &&
                                                    'bg-accent shadow-[inset_2px_0_0_var(--primary)]',
                                            )}
                                        >
                                            <ItemIcon
                                                className={cn(
                                                    'size-4 shrink-0',
                                                    isActive
                                                        ? 'text-brand-text'
                                                        : 'text-muted-foreground',
                                                )}
                                                aria-hidden="true"
                                            />
                                            <span className="truncate">
                                                {item.label}
                                            </span>
                                        </div>
                                    </div>
                                );
                            })
                        )}
                    </div>

                    <div className="border-border bg-surface-subtle text-muted-foreground flex items-center gap-3.5 border-t px-3.5 py-2 text-xs max-md:hidden">
                        <span className="inline-flex items-center gap-1">
                            <Kbd>↑</Kbd>
                            <Kbd>↓</Kbd>
                            {t('admin.command.hintSelect')}
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <Kbd>↵</Kbd>
                            {t('admin.command.hintOpen')}
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <Kbd>Esc</Kbd>
                            {t('admin.command.hintClose')}
                        </span>
                    </div>
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}
