import { ShieldAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import { EmptyState } from './empty-state';

export type PermissionDeniedProps = {
    title: string;
    description?: string;
    /** Typically a <Button> linking back to a safe page. */
    action?: ReactNode;
};

export function PermissionDenied({
    title,
    description,
    action,
}: PermissionDeniedProps) {
    return (
        <EmptyState
            icon={ShieldAlert}
            title={title}
            description={description}
            action={action}
        />
    );
}
