import { Construction } from 'lucide-react';
import type { ReactNode } from 'react';
import { EmptyState } from './empty-state';

export type MaintenanceProps = {
    title: string;
    description?: string;
    /** Typically a <Button> to retry or check status. */
    action?: ReactNode;
};

export function Maintenance({ title, description, action }: MaintenanceProps) {
    return (
        <EmptyState
            icon={Construction}
            title={title}
            description={description}
            action={action}
        />
    );
}
