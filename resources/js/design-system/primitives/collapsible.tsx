import * as CollapsiblePrimitive from '@radix-ui/react-collapsible';
import { ChevronDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type CollapsibleProps = {
    trigger: ReactNode;
    children: ReactNode;
    open?: boolean;
    defaultOpen?: boolean;
    onOpenChange?: (open: boolean) => void;
    disabled?: boolean;
    className?: never;
    style?: never;
};

export function Collapsible({
    trigger,
    children,
    open,
    defaultOpen,
    onOpenChange,
    disabled = false,
}: CollapsibleProps) {
    return (
        <CollapsiblePrimitive.Root
            open={open}
            defaultOpen={defaultOpen}
            onOpenChange={onOpenChange}
            disabled={disabled}
            className="border-border-subtle rounded-lg border"
        >
            <CollapsiblePrimitive.Trigger
                className={cn(
                    'focus-visible:ring-ring flex w-full items-center justify-between gap-2 px-4 py-3 text-left text-sm font-medium',
                    'focus-visible:ring-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50',
                    '[&[data-state=open]>svg]:rotate-180',
                )}
            >
                {trigger}
                <ChevronDown
                    className="text-muted-foreground size-4 shrink-0 transition-transform"
                    aria-hidden="true"
                />
            </CollapsiblePrimitive.Trigger>
            <CollapsiblePrimitive.Content
                className={cn(
                    'data-[state=closed]:animate-out data-[state=open]:animate-in data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 overflow-hidden',
                    'text-muted-foreground px-4 pb-4 text-sm',
                )}
            >
                {children}
            </CollapsiblePrimitive.Content>
        </CollapsiblePrimitive.Root>
    );
}
