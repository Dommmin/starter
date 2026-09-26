import * as TabsPrimitive from '@radix-ui/react-tabs';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type TabItem = {
    value: string;
    label: string;
    content: ReactNode;
    disabled?: boolean;
};

export type TabsProps = {
    items: TabItem[];
    value?: string;
    defaultValue?: string;
    onValueChange?: (value: string) => void;
    /** Translated accessible label for the tab list. */
    ariaLabel: string;
    className?: never;
    style?: never;
};

export function Tabs({
    items,
    value,
    defaultValue,
    onValueChange,
    ariaLabel,
}: TabsProps) {
    return (
        <TabsPrimitive.Root
            value={value}
            defaultValue={defaultValue ?? items[0]?.value}
            onValueChange={onValueChange}
            className="flex flex-col gap-4"
        >
            <TabsPrimitive.List
                aria-label={ariaLabel}
                className="border-border-subtle flex gap-1 overflow-x-auto border-b"
            >
                {items.map((item) => (
                    <TabsPrimitive.Trigger
                        key={item.value}
                        value={item.value}
                        disabled={item.disabled}
                        className={cn(
                            'text-muted-foreground data-[state=active]:text-foreground data-[state=active]:border-primary -mb-px shrink-0 border-b-2 border-transparent px-3 py-2 text-sm font-medium whitespace-nowrap',
                            'focus-visible:ring-ring rounded-t-sm focus-visible:ring-2 focus-visible:outline-none',
                            'disabled:pointer-events-none disabled:opacity-50',
                        )}
                    >
                        {item.label}
                    </TabsPrimitive.Trigger>
                ))}
            </TabsPrimitive.List>
            {items.map((item) => (
                <TabsPrimitive.Content
                    key={item.value}
                    value={item.value}
                    className="focus-visible:ring-ring rounded-md focus-visible:ring-2 focus-visible:outline-none"
                >
                    {item.content}
                </TabsPrimitive.Content>
            ))}
        </TabsPrimitive.Root>
    );
}
