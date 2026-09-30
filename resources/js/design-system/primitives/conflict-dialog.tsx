import * as DialogPrimitive from '@radix-ui/react-dialog';
import { cn } from '@/lib/utils';
import { Button } from './button';
import { useReturnFocus } from './return-focus';

export type ConflictDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    /** Discards local changes and reloads the server's version. */
    reloadLabel: string;
    onReload: () => void;
    /** Overwrites the server's version with the local changes. */
    overwriteLabel: string;
    onOverwrite: () => void;
    isPending?: boolean;
};

export function ConflictDialog({
    open,
    onOpenChange,
    title,
    description,
    reloadLabel,
    onReload,
    overwriteLabel,
    onOverwrite,
    isPending = false,
}: ConflictDialogProps) {
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
                        'bg-background border-border-subtle fixed top-1/2 left-1/2 z-50 max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-lg border p-6 shadow-lg',
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
                        <Button
                            variant="outline"
                            disabled={isPending}
                            onClick={onReload}
                        >
                            {reloadLabel}
                        </Button>
                        <Button
                            variant="primary"
                            isPending={isPending}
                            onClick={onOverwrite}
                        >
                            {overwriteLabel}
                        </Button>
                    </div>
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}
