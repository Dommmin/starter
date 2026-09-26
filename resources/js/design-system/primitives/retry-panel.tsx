import { RotateCw } from 'lucide-react';
import { Alert } from './alert';
import { Button } from './button';
import { Stack } from './stack';

export type RetryPanelProps = {
    title: string;
    description?: string;
    retryLabel: string;
    onRetry: () => void;
    isPending?: boolean;
};

export function RetryPanel({
    title,
    description,
    retryLabel,
    onRetry,
    isPending = false,
}: RetryPanelProps) {
    return (
        <Stack gap="tight" align="start">
            <Alert tone="danger" title={title} description={description} />
            <Button variant="outline" onClick={onRetry} isPending={isPending}>
                <RotateCw className="size-4" aria-hidden="true" />
                {retryLabel}
            </Button>
        </Stack>
    );
}
