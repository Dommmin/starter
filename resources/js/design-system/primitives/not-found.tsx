import { FileQuestion } from 'lucide-react';
import type { ReactNode } from 'react';
import { EmptyState } from './empty-state';

export type NotFoundProps = {
    title: string;
    description?: string;
    /** Typically a <Button> linking back to a safe page. */
    action?: ReactNode;
};

export function NotFound({ title, description, action }: NotFoundProps) {
    return (
        <EmptyState
            icon={FileQuestion}
            title={title}
            description={description}
            action={action}
        />
    );
}
