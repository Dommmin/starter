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
    return (
        <dl className="flex flex-col">
            {items.map((item) => {
                const isEmpty =
                    item.value === null ||
                    item.value === undefined ||
                    item.value === '';

                return (
                    <div
                        key={item.id}
                        className="border-border-subtle grid grid-cols-1 gap-x-6 gap-y-1 border-b py-3 last:border-b-0 sm:grid-cols-[minmax(0,200px)_1fr]"
                    >
                        <dt className="text-muted-foreground text-sm font-medium">
                            {item.label}
                        </dt>
                        <dd className="text-foreground text-sm">
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
