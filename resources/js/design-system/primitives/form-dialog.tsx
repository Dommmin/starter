import * as DialogPrimitive from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { Button } from './button';
import { useReturnFocus } from './return-focus';

export type FormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    children: ReactNode;
    submitLabel: string;
    cancelLabel: string;
    /** Translated accessible label for the corner close control. */
    closeLabel: string;
    onSubmit: () => void;
    isPending?: boolean;
    /** Server-side error to surface above the actions, e.g. after a failed submit. */
    error?: string;
};

export function FormDialog({
    open,
    onOpenChange,
    title,
    description,
    children,
    submitLabel,
    cancelLabel,
    closeLabel,
    onSubmit,
    isPending = false,
    error,
}: FormDialogProps) {
    const returnFocus = useReturnFocus();
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
                    onEscapeKeyDown={(event) => {
                        if (isPending) {
                            event.preventDefault();
                        }
                    }}
                    onPointerDownOutside={(event) => {
                        if (isPending) {
                            event.preventDefault();
                        }
                    }}
                    onInteractOutside={(event) => {
                        if (isPending) {
                            event.preventDefault();
                        }
                    }}
                    className={cn(
                        'bg-background border-border-subtle fixed top-1/2 left-1/2 z-50 max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-lg border p-6 shadow-lg',
                        'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=open]:fade-in-0 data-[state=closed]:fade-out-0 data-[state=open]:zoom-in-95 data-[state=closed]:zoom-out-95',
                    )}
                >
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            // The dialog is portalled, but React still bubbles
                            // `submit` through the component tree; keep it
                            // from reaching an enclosing form (e.g. ResourceForm).
                            event.stopPropagation();
                            if (isPending) {
                                return;
                            }
                            onSubmit();
                        }}
                        className="flex flex-col gap-4"
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
                        {error && (
                            <p
                                role="alert"
                                className="text-destructive text-sm"
                            >
                                {error}
                            </p>
                        )}
                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <DialogPrimitive.Close asChild>
                                <Button variant="outline" disabled={isPending}>
                                    {cancelLabel}
                                </Button>
                            </DialogPrimitive.Close>
                            <Button
                                type="submit"
                                variant="primary"
                                isPending={isPending}
                            >
                                {submitLabel}
                            </Button>
                        </div>
                    </form>
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
