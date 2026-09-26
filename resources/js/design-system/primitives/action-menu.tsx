import * as DropdownMenuPrimitive from '@radix-ui/react-dropdown-menu';
import { MoreVertical, type LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import { IconButton } from './icon-button';

export type ActionMenuItem = {
    id: string;
    label: string;
    icon?: LucideIcon;
    onSelect: () => void;
    tone?: 'default' | 'destructive';
    disabled?: boolean;
};

export type ActionMenuProps = {
    /** Translated accessible label for the trigger, e.g. "Row actions". */
    triggerLabel: string;
    items: ActionMenuItem[];
    align?: 'start' | 'end';
    disabled?: boolean;
};

export function ActionMenu({
    triggerLabel,
    items,
    align = 'end',
    disabled = false,
}: ActionMenuProps) {
    return (
        <DropdownMenuPrimitive.Root>
            <DropdownMenuPrimitive.Trigger asChild disabled={disabled}>
                <IconButton
                    icon={MoreVertical}
                    ariaLabel={triggerLabel}
                    variant="ghost"
                    size="sm"
                    disabled={disabled}
                />
            </DropdownMenuPrimitive.Trigger>
            <DropdownMenuPrimitive.Portal>
                <DropdownMenuPrimitive.Content
                    align={align}
                    sideOffset={4}
                    className={cn(
                        'bg-popover text-popover-foreground border-border-subtle z-50 min-w-40 overflow-hidden rounded-md border p-1 shadow-md',
                        'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
                    )}
                >
                    {items.map((item) => {
                        const ItemIcon = item.icon;

                        return (
                            <DropdownMenuPrimitive.Item
                                key={item.id}
                                disabled={item.disabled}
                                onSelect={item.onSelect}
                                className={cn(
                                    'focus:bg-surface-subtle relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none select-none',
                                    'data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
                                    item.tone === 'destructive' &&
                                        'text-destructive focus:text-destructive',
                                )}
                            >
                                {ItemIcon && (
                                    <ItemIcon
                                        className="size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                )}
                                {item.label}
                            </DropdownMenuPrimitive.Item>
                        );
                    })}
                </DropdownMenuPrimitive.Content>
            </DropdownMenuPrimitive.Portal>
        </DropdownMenuPrimitive.Root>
    );
}
