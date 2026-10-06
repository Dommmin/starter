import type { LucideIcon } from 'lucide-react';
import { Grid } from './grid';
import { Heading } from './heading';
import { Icon } from './icon';
import { Stack } from './stack';
import { Text } from './text';

export type FeatureGridItem = {
    id: string;
    icon?: LucideIcon;
    title: string;
    description: string;
};

type FeatureGridColumns = 'cards' | 'features';

export type FeatureGridProps = {
    title?: string;
    description?: string;
    items: FeatureGridItem[];
    columns?: FeatureGridColumns;
};

export function FeatureGrid({
    title,
    description,
    items,
    columns = 'cards',
}: FeatureGridProps) {
    return (
        <Stack gap="relaxed">
            {(title || description) && (
                <Stack gap="tight" align="center">
                    {title && (
                        <Heading level={2} align="center">
                            {title}
                        </Heading>
                    )}
                    {description && (
                        <Text variant="lead" tone="muted" align="center">
                            {description}
                        </Text>
                    )}
                </Stack>
            )}
            <Grid layout={columns} gap="default">
                {items.map((item) => (
                    <Stack key={item.id} gap="tight">
                        {item.icon && (
                            <span className="bg-brand-glow mb-1 inline-flex size-10 items-center justify-center self-start rounded-lg">
                                <Icon
                                    icon={item.icon}
                                    size="lg"
                                    tone="primary"
                                />
                            </span>
                        )}
                        <Heading level={title ? 3 : 2} variant="group">
                            {item.title}
                        </Heading>
                        <Text variant="body" tone="muted">
                            {item.description}
                        </Text>
                    </Stack>
                ))}
            </Grid>
        </Stack>
    );
}
