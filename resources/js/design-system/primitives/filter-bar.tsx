import type { ReactNode } from 'react';

export type FilterBarProps = {
    children: ReactNode;
    /** Rendered at the end of the bar, typically a "Clear filters" Button. */
    actions?: ReactNode;
    className?: never;
    style?: never;
};

export function FilterBar({ children, actions }: FilterBarProps) {
    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
            <div className="flex flex-1 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                {children}
            </div>
            {actions && (
                <div className="flex shrink-0 items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}
