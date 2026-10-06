import type { ReactNode } from 'react';

export type DescriptionListItem = {
    id: string;
    label: string;
    value: ReactNode;
};

export type DescriptionListProps = {
    items: DescriptionListItem[];
    /** Translated placeholder shown when an item's value is null/undefined/empty. */
    emptyValuePlaceholder?: string;
    className?: never;
    style?: never;
};

export function DescriptionList({
    items,
    emptyValuePlaceholder = '—',
}: DescriptionListProps) {
    /**
     * Label and value sit side by side only when the list itself is at least
     * 28rem wide (container query), not when the viewport is: a list in a
     * narrow card or split column keeps the stacked layout instead of
     * squeezing the value to a few characters per line.
     */
    return (
        <dl className="@container flex w-full flex-col">
            {items.map((item) => {
                const isEmpty =
                    item.value === null ||
                    item.value === undefined ||
                    item.value === '';

                return (
                    <div
                        key={item.id}
                        className="border-border-subtle grid grid-cols-1 gap-x-6 gap-y-1 border-b py-3 last:border-b-0 @md:grid-cols-[minmax(0,200px)_1fr]"
                    >
                        <dt className="text-muted-foreground text-sm font-medium">
                            {item.label}
                        </dt>
                        <dd className="text-foreground min-w-0 text-sm wrap-anywhere">
                            {isEmpty ? (
                                <span className="text-muted-foreground">
                                    {emptyValuePlaceholder}
                                </span>
                            ) : (
                                item.value
                            )}
                        </dd>
                    </div>
                );
            })}
        </dl>
    );
}
