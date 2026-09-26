import * as TooltipPrimitive from '@radix-ui/react-tooltip';
import type { ReactElement } from 'react';
import { cn } from '@/lib/utils';

export type TooltipProps = {
    /** Translated tooltip text. */
    content: string;
    /** A single focusable element (e.g. IconButton) that triggers the tooltip. */
    children: ReactElement;
    side?: 'top' | 'right' | 'bottom' | 'left';
};

export function Tooltip({ content, children, side = 'top' }: TooltipProps) {
    return (
        <TooltipPrimitive.Provider delayDuration={200}>
            <TooltipPrimitive.Root>
                <TooltipPrimitive.Trigger asChild>
                    {children}
                </TooltipPrimitive.Trigger>
                <TooltipPrimitive.Portal>
                    <TooltipPrimitive.Content
                        side={side}
                        sideOffset={6}
                        className={cn(
                            'bg-surface-inverted text-surface-inverted-foreground z-50 max-w-xs rounded-md px-3 py-1.5 text-xs',
                            'animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95',
                        )}
                    >
                        {content}
                        <TooltipPrimitive.Arrow className="fill-surface-inverted" />
                    </TooltipPrimitive.Content>
                </TooltipPrimitive.Portal>
            </TooltipPrimitive.Root>
        </TooltipPrimitive.Provider>
    );
}
