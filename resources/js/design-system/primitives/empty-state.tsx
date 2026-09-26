import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Icon } from './icon';
import { Stack } from './stack';
import { Text } from './text';

export type EmptyStateProps = {
    /** Translated headline passed from caller via useTranslation. */
    title: string;
    description?: string;
    icon?: LucideIcon;
    /** Typically a <Button>. */
    action?: ReactNode;
};

export function EmptyState({
    title,
    description,
    icon,
    action,
}: EmptyStateProps) {
    return (
        <div className="py-12 sm:py-16">
            <Stack gap="tight" align="center" justify="center">
                {icon && <Icon icon={icon} size="lg" tone="muted" />}
                <Text variant="label" tone="default" align="center" as="p">
                    {title}
                </Text>
                {description && (
                    <Text variant="caption" tone="muted" align="center" as="p">
                        {description}
                    </Text>
                )}
                {action}
            </Stack>
        </div>
    );
}
