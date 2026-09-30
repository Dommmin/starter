import * as SeparatorPrimitive from '@radix-ui/react-separator';
import { cn } from '@/lib/utils';

export type SeparatorProps = {
    orientation?: 'horizontal' | 'vertical';
};

/**
 * A vertical separator stretches to the full height of its row (`Inline`,
 * whatever its `align`) through `self-stretch`; `h-full` collapsed to 0 px
 * without an explicitly sized parent.
 */
export function Separator({ orientation = 'horizontal' }: SeparatorProps) {
    return (
        <SeparatorPrimitive.Root
            decorative
            orientation={orientation}
            className={cn(
                'bg-border shrink-0',
                orientation === 'horizontal'
                    ? 'h-px w-full'
                    : 'w-px self-stretch',
            )}
        />
    );
}
