import { ItemList, Link, Stack, Text } from '@/design-system/primitives';

export type RelatedRecordsLabels = {
    empty: string;
    /** Shown when more records exist than are listed, e.g. "Showing 20 of 54." */
    shown: (shown: number, total: number) => string;
    showAll: string;
};

export type RelatedRecordsProps = {
    group: App.Data.Listing.RelatedRecordsData;
    labels: RelatedRecordsLabels;
};

/**
 * Tab content of a hasMany relation on a generated edit screen: the newest
 * related records as links to their editors and the full list.
 */
export function RelatedRecords({ group, labels }: RelatedRecordsProps) {
    return (
        <Stack gap="default">
            {group.items.length === 0 ? (
                <Text tone="muted">{labels.empty}</Text>
            ) : (
                <ItemList
                    items={group.items.map((item) => ({
                        id: String(item.id),
                        content: (
                            <Link href={item.url} tone="default">
                                {item.label === '' ? `#${item.id}` : item.label}
                            </Link>
                        ),
                    }))}
                />
            )}
            {group.total > group.items.length && (
                <Text variant="caption" tone="muted">
                    {labels.shown(group.items.length, group.total)}
                </Text>
            )}
            <Link href={group.listUrl} tone="muted">
                {labels.showAll}
            </Link>
        </Stack>
    );
}
