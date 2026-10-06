import * as DialogPrimitive from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import type { ReactElement, ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { IconButton } from './icon-button';
import type { NavItem } from './nav-item';
import { isNavLink, NavItemLink } from './nav-item-link';

export type MobileNavPanelProps = {
    trigger: ReactElement;
    title: string;
    items: NavItem[];
    closeLabel: string;
    footer?: ReactNode;
    utilities?: ReactNode;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

const itemClasses =
    'text-foreground hover:bg-surface-subtle focus-visible:ring-ring block rounded-md px-3 py-2.5 text-sm font-medium focus-visible:ring-2 focus-visible:outline-none';
const childClasses =
    'text-muted-foreground hover:text-foreground hover:bg-surface-subtle focus-visible:ring-ring block rounded-md py-2.5 pr-3 pl-6 text-sm focus-visible:ring-2 focus-visible:outline-none';

type MobileNavLinkProps = {
    item: NavItem;
    classes: string;
    onNavigate: () => void;
};

function MobileNavLink({ item, classes, onNavigate }: MobileNavLinkProps) {
    if (!isNavLink(item)) {
        return null;
    }

    return (
        <NavItemLink item={item} classes={classes} onNavigate={onNavigate} />
    );
}

/**
 * Radix dialog of MobileNav, loaded on the first opening (see
 * `lazy-popup.ts`), so public pages do not ship it with their first render.
 */
export default function MobileNavPanel({
    trigger,
    title,
    items,
    closeLabel,
    footer,
    utilities,
    open,
    onOpenChange,
}: MobileNavPanelProps) {
    // Closing on activation matters for in-page anchors, where no Inertia
    // visit replaces the page (and with it the open dialog).
    const close = () => onOpenChange(false);

    return (
        <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
            <DialogPrimitive.Trigger asChild>{trigger}</DialogPrimitive.Trigger>
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay
                    className={cn(
                        'bg-overlay fixed inset-0 z-50',
                        'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=open]:fade-in-0 data-[state=closed]:fade-out-0',
                    )}
                />
                <DialogPrimitive.Content
                    className={cn(
                        'bg-background fixed inset-y-0 right-0 z-50 flex w-full max-w-xs flex-col gap-6 overflow-y-auto p-6 shadow-lg',
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
                    {utilities && (
                        <div className="border-border-subtle flex flex-col gap-1 border-b pb-4 sm:hidden">
                            {utilities}
                        </div>
                    )}
                    {items.length > 0 && (
                        <nav aria-label={title} className="flex-1">
                            <ul className="flex flex-col gap-1">
                                {items.map((item) => {
                                    const children = item.children ?? [];

                                    return (
                                        <li key={item.id}>
                                            {isNavLink(item) ? (
                                                <MobileNavLink
                                                    item={item}
                                                    classes={itemClasses}
                                                    onNavigate={close}
                                                />
                                            ) : (
                                                <p className="text-muted-foreground px-3 pt-3 pb-1 text-xs font-semibold tracking-wide uppercase">
                                                    {item.label}
                                                </p>
                                            )}
                                            {children.length > 0 && (
                                                <ul className="flex flex-col gap-1">
                                                    {children.map((child) => (
                                                        <li key={child.id}>
                                                            <MobileNavLink
                                                                item={child}
                                                                classes={
                                                                    childClasses
                                                                }
                                                                onNavigate={
                                                                    close
                                                                }
                                                            />
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                        </nav>
                    )}
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
