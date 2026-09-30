import * as DropdownMenuPrimitive from '@radix-ui/react-dropdown-menu';
import { MoreVertical, type LucideIcon } from 'lucide-react';
import { useRef } from 'react';
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

/**
 * Row action menu. The selected action runs only after the menu has closed
 * and returned focus to its trigger, so a dialog opened by the action (e.g.
 * a delete confirmation) restores focus to that trigger when it closes.
 */
export function ActionMenu({
    triggerLabel,
    items,
    align = 'end',
    disabled = false,
}: ActionMenuProps) {
    const pendingAction = useRef<(() => void) | null>(null);

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
                    onCloseAutoFocus={() => {
                        const action = pendingAction.current;
                        pendingAction.current = null;
                        action?.();
                    }}
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
                                onSelect={() => {
                                    pendingAction.current = item.onSelect;
                                }}
                                className={cn(
                                    'focus:bg-surface-subtle focus-visible:ring-ring relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none select-none focus-visible:ring-2 focus-visible:ring-inset',
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
