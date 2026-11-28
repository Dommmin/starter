import { Head, usePage } from '@inertiajs/react';
import {
    Badge,
    DataTable,
    EmptyState,
    Link,
    PageHeader,
    Stack,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { index as qualityIndex } from '@/routes/admin/quality';

type Row = App.Data.Admin.Quality.ContentIssueData;

export default function AdminContentQualityIndex() {
    const { issues, total } =
        usePage<App.Data.Admin.Quality.ContentQualityData>().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('admin.quality.title')} />
            <Stack gap="relaxed">
                <PageHeader
                    title={t('admin.quality.title')}
                    description={t('admin.quality.description')}
                />
                {total > issues.length && (
                    <Text variant="caption" tone="muted">
                        {t('admin.quality.truncated', {
                            shown: issues.length,
                            total,
                        })}
                    </Text>
                )}
                <DataTable<Row>
                    caption={t('admin.quality.title')}
                    rows={issues}
                    rowKey={(row) => row.id}
                    emptyState={
                        <EmptyState
                            title={t('admin.quality.emptyTitle')}
                            description={t('admin.quality.emptyDescription')}
                        />
                    }
                    columns={[
                        {
                            key: 'label',
                            priority: 'primary',
                            header: t('admin.quality.columnRecord'),
                            render: (row) => (
                                <Link href={row.editUrl} tone="primary">
                                    {row.label}
                                </Link>
                            ),
                        },
                        {
                            key: 'type',
                            priority: 'status',
                            header: t('admin.quality.columnIssue'),
                            render: (row) => (
                                <Badge
                                    tone={
                                        row.type === 'broken_link'
                                            ? 'danger'
                                            : 'neutral'
                                    }
                                >
                                    {t(`admin.quality.types.${row.type}`)}
                                </Badge>
                            ),
                        },
                        {
                            key: 'detail',
                            priority: 'secondary',
                            header: t('admin.quality.columnDetail'),
                            render: (row) =>
                                [row.locale?.toUpperCase(), row.detail]
                                    .filter(Boolean)
                                    .join(' · ') || '—',
                        },
                    ]}
                />
            </Stack>
        </>
    );
}

AdminContentQualityIndex.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.quality.title', href: qualityIndex() },
    ],
};
