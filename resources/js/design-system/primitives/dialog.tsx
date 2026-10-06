import * as DialogPrimitive from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { useReturnFocus } from './return-focus';

export type DialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    /** Translated accessible label for the corner close control. */
    closeLabel: string;
    /**
     * Dialog body. Unlike `FormDialog` it does not wrap a `<form>`, so a step
     * may bring its own (e.g. an Inertia `<Form>` with its buttons).
     */
    children: ReactNode;
    /** Trailing action buttons; stacked on phones, right-aligned from `sm`. */
    actions?: ReactNode;
    /** Blocks closing (Escape, outside click, close button) while true. */
    isPending?: boolean;
    className?: never;
    style?: never;
};

/**
 * General modal dialog: focus trap, Escape and the close control dismiss it,
 * focus returns to the trigger. Use `ConfirmDialog` for a yes/no question and
 * `FormDialog` for a single form; this one is for multi-step or read-only
 * content such as the two-factor setup.
 */
export function Dialog({
    open,
    onOpenChange,
    title,
    description,
    closeLabel,
    children,
    actions,
    isPending = false,
}: DialogProps) {
    const returnFocus = useReturnFocus();
    const preventWhilePending = (event: Event) => {
        if (isPending) {
            event.preventDefault();
        }
    };

    return (
        <DialogPrimitive.Root
            open={open}
            onOpenChange={(nextOpen) => {
                if (!isPending) {
                    onOpenChange(nextOpen);
                }
            }}
        >
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay
                    className={cn(
                        'bg-overlay fixed inset-0 z-50',
                        'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=open]:fade-in-0 data-[state=closed]:fade-out-0',
                    )}
                />
                <DialogPrimitive.Content
                    onOpenAutoFocus={returnFocus.onOpenAutoFocus}
                    onCloseAutoFocus={returnFocus.onCloseAutoFocus}
                    onEscapeKeyDown={preventWhilePending}
                    onPointerDownOutside={preventWhilePending}
                    onInteractOutside={preventWhilePending}
                    className={cn(
                        'bg-background border-border-subtle fixed top-1/2 left-1/2 z-50 flex max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 flex-col gap-4 overflow-y-auto rounded-lg border p-6 shadow-lg',
                        'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=open]:fade-in-0 data-[state=closed]:fade-out-0 data-[state=open]:zoom-in-95 data-[state=closed]:zoom-out-95',
                    )}
                >
                    <div>
                        <DialogPrimitive.Title className="text-foreground pr-8 text-lg font-semibold">
                            {title}
                        </DialogPrimitive.Title>
                        {description && (
                            <DialogPrimitive.Description className="text-muted-foreground mt-2 text-sm">
                                {description}
                            </DialogPrimitive.Description>
                        )}
                    </div>
                    {children}
                    {actions && (
                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            {actions}
                        </div>
                    )}
                    <DialogPrimitive.Close asChild>
                        <button
                            type="button"
                            aria-label={closeLabel}
                            disabled={isPending}
                            className="text-muted-foreground hover:text-foreground focus-visible:ring-ring absolute top-4 right-4 rounded-full p-1 focus-visible:ring-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"
                        >
                            <X className="size-4" aria-hidden="true" />
                        </button>
                    </DialogPrimitive.Close>
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}
