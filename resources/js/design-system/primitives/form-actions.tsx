import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type FormActionsAlign = 'start' | 'end' | 'between';

export type FormActionsProps = {
    children: ReactNode;
    align?: FormActionsAlign;
    className?: never;
    style?: never;
};

const alignMap: Record<FormActionsAlign, string> = {
    start: 'sm:justify-start',
    end: 'sm:justify-end',
    between: 'sm:justify-between',
};

export function FormActions({ children, align = 'end' }: FormActionsProps) {
    return (
        <div
            className={cn(
                'border-border-subtle flex flex-col-reverse gap-2 border-t pt-4 sm:flex-row sm:items-center',
                alignMap[align],
            )}
        >
            {children}
        </div>
    );
}
