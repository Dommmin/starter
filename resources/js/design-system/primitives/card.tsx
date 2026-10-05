import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type CardPadding = 'default' | 'compact' | 'none';

export type CardProps = {
    children: ReactNode;
    padding?: CardPadding;
    className?: never;
    style?: never;
};

const paddingMap: Record<CardPadding, string> = {
    default: 'gap-6 p-6',
    compact: 'gap-4 p-4',
    none: 'gap-0 p-0',
};

export function Card({ children, padding = 'default' }: CardProps) {
    return (
        <div
            className={cn(
                'bg-card text-card-foreground border-border-subtle flex flex-col rounded-xl border shadow-sm',
                paddingMap[padding],
            )}
        >
            {children}
        </div>
    );
}

export type CardHeaderProps = {
    children: ReactNode;
};

export function CardHeader({ children }: CardHeaderProps) {
    return <div className="flex flex-col gap-1.5">{children}</div>;
}

export type CardTitleProps = {
    children: ReactNode;
    id?: string;
};

export function CardTitle({ children, id }: CardTitleProps) {
    return (
        <h3 id={id} className="leading-none font-semibold">
            {children}
        </h3>
    );
}

export type CardDescriptionProps = {
    children: ReactNode;
};

export function CardDescription({ children }: CardDescriptionProps) {
    return <p className="text-muted-foreground text-sm">{children}</p>;
}

export type CardContentProps = {
    children: ReactNode;
};

export function CardContent({ children }: CardContentProps) {
    return <div>{children}</div>;
}

export type CardFooterProps = {
    children: ReactNode;
};

export function CardFooter({ children }: CardFooterProps) {
    return <div className="flex items-center gap-2">{children}</div>;
}
