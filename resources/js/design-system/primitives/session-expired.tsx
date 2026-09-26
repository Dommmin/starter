import { Clock } from 'lucide-react';
import type { ReactNode } from 'react';
import { EmptyState } from './empty-state';

export type SessionExpiredProps = {
    title: string;
    description?: string;
    /** Typically a <Button> linking to the login page. */
    action?: ReactNode;
};

export function SessionExpired({
    title,
    description,
    action,
}: SessionExpiredProps) {
    return (
        <EmptyState
            icon={Clock}
            title={title}
            description={description}
            action={action}
        />
    );
}
