import { Link as InertiaLink } from '@inertiajs/react';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import { Menu, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { IconButton } from './icon-button';

export type MobileNavItem = {
    id: string;
    label: string;
    href: string;
};

export type MobileNavProps = {
    /** Visible panel heading, e.g. "Menu". */
    title: string;
    items: MobileNavItem[];
    /** Translated accessible label for the trigger control. */
    openLabel: string;
    /** Translated accessible label for the close control. */
    closeLabel: string;
    /** Rendered at the bottom of the panel, e.g. login/register actions. */
    footer?: ReactNode;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
};

export function MobileNav({
    title,
    items,
    openLabel,
    closeLabel,
    footer,
    open,
    onOpenChange,
}: MobileNavProps) {
    return (
        <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
            <DialogPrimitive.Trigger asChild>
                <IconButton icon={Menu} ariaLabel={openLabel} variant="ghost" />
            </DialogPrimitive.Trigger>
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay
                    className={cn(
                        'bg-overlay fixed inset-0 z-50',
                        'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=open]:fade-in-0 data-[state=closed]:fade-out-0',
                    )}
                />
                <DialogPrimitive.Content
                    className={cn(
                        'bg-background fixed inset-y-0 right-0 z-50 flex w-full max-w-xs flex-col gap-6 p-6 shadow-lg',
                        'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=open]:slide-in-from-right data-[state=closed]:slide-out-to-right',
                    )}
                >
                    <div className="flex items-center justify-between">
                        <DialogPrimitive.Title className="text-foreground text-base font-semibold">
                            {title}
                        </DialogPrimitive.Title>
                        <DialogPrimitive.Close asChild>
                            <IconButton
                                icon={X}
                                ariaLabel={closeLabel}
                                variant="ghost"
                            />
                        </DialogPrimitive.Close>
                    </div>
                    <nav
                        aria-label={title}
                        className="flex flex-1 flex-col gap-1"
                    >
                        {items.map((item) => (
                            <InertiaLink
                                key={item.id}
                                href={item.href}
                                className="text-foreground hover:bg-surface-subtle focus-visible:ring-ring rounded-md px-3 py-2.5 text-sm font-medium focus-visible:ring-2 focus-visible:outline-none"
                            >
                                {item.label}
                            </InertiaLink>
                        ))}
                    </nav>
                    {footer && (
                        <div className="border-border-subtle flex flex-col gap-2 border-t pt-4">
                            {footer}
                        </div>
                    )}
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}
