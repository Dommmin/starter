import * as DialogPrimitive from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from './button';

type ConfirmDialogTone = 'default' | 'destructive';

export type ConfirmDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    confirmLabel: string;
    cancelLabel: string;
    /** Translated accessible label for the corner close control. */
    closeLabel: string;
    onConfirm: () => void;
    tone?: ConfirmDialogTone;
    isPending?: boolean;
};

export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel,
    cancelLabel,
    closeLabel,
    onConfirm,
    tone = 'default',
    isPending = false,
}: ConfirmDialogProps) {
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
                        'bg-background border-border-subtle fixed top-1/2 left-1/2 z-50 w-full max-w-md -translate-x-1/2 -translate-y-1/2 rounded-lg border p-6 shadow-lg',
                        'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=open]:fade-in-0 data-[state=closed]:fade-out-0 data-[state=open]:zoom-in-95 data-[state=closed]:zoom-out-95',
                    )}
                >
                    <DialogPrimitive.Title className="text-foreground text-lg font-semibold">
                        {title}
                    </DialogPrimitive.Title>
                    {description && (
                        <DialogPrimitive.Description className="text-muted-foreground mt-2 text-sm">
                            {description}
                        </DialogPrimitive.Description>
                    )}
                    <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <DialogPrimitive.Close asChild>
                            <Button variant="outline" disabled={isPending}>
                                {cancelLabel}
                            </Button>
                        </DialogPrimitive.Close>
                        <Button
                            variant={
                                tone === 'destructive'
                                    ? 'destructive'
                                    : 'primary'
                            }
                            isPending={isPending}
                            onClick={onConfirm}
                        >
                            {confirmLabel}
                        </Button>
                    </div>
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
