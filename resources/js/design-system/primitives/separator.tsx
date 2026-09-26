import * as SeparatorPrimitive from '@radix-ui/react-separator';
import { cn } from '@/lib/utils';

export type SeparatorProps = {
    orientation?: 'horizontal' | 'vertical';
};

export function Separator({ orientation = 'horizontal' }: SeparatorProps) {
    return (
        <SeparatorPrimitive.Root
            decorative
            orientation={orientation}
            className={cn(
                'bg-border shrink-0',
                orientation === 'horizontal' ? 'h-px w-full' : 'h-full w-px',
            )}
        />
    );
}
