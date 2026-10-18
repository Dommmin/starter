import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    Alert,
    Badge,
    Button,
    DataTable,
    FileDropzone,
    FormActions,
    Heading,
    Inline,
    PageHeader,
    Stack,
    Surface,
    Text,
} from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { index as faqsIndex } from '@/routes/admin/faqs';
import { preview, store } from '@/routes/admin/import';
import { index as tagsIndex } from '@/routes/admin/tags';

type ScreenProps = App.Data.Admin.Import.ImportScreenData;
type Row = App.Data.Admin.Import.ImportRowData;

const listUrls = { faqs: faqsIndex, tags: tagsIndex } as const;

/**
 * CSV import of a module: describe the expected columns, check an uploaded
 * file row by row, then import exactly the checked rows.
 */
export default function AdminImportShow() {
    const {
        module,
        columns,
        requiredColumns,
        maxRows,
        maxKilobytes,
        preview: result,
    } = usePage<ScreenProps>().props;
    const { t } = useTranslation();
    const [isChecking, setIsChecking] = useState(false);
    const commit = useForm({ token: result?.token ?? '' });
    const title = t(`admin.import.titles.${module}`);

    function check(files: File[]) {
        const file = files[0];

        if (!file) {
            return;
        }

        setIsChecking(true);
        router.post(
            preview.url(module),
            { file },
            { forceFormData: true, onFinish: () => setIsChecking(false) },
        );
    }

    function importRows() {
        commit.post(store.url(module));
    }

    return (
        <>
            <Head title={title} />

            <Stack gap="relaxed">
                <PageHeader
                    title={title}
                    description={t('admin.import.description', {
                        max: maxRows,
                    })}
                    actions={
                        <Button variant="outline" href={listUrls[module]()}>
                            {t('admin.import.back')}
                        </Button>
                    }
                />

                <Surface padding="default" border>
                    <Stack gap="tight">
                        <Heading level={2} variant="group">
                            {t('admin.import.columnsTitle')}
                        </Heading>
                        <Text tone="muted">
                            {t('admin.import.columnsHelp')}
                        </Text>
                        <Inline gap="tight">
                            {columns.map((column) => (
                                <Badge
                                    key={column}
                                    tone={
                                        requiredColumns.includes(column)
                                            ? 'primary'
                                            : 'outline'
                                    }
                                >
                                    {column}
                                </Badge>
                            ))}
                        </Inline>
                        <Text variant="caption" tone="muted">
                            {t(`admin.import.formats.${module}`)}
                        </Text>
                    </Stack>
                </Surface>

                <FileDropzone
                    label={t('admin.import.fileLabel')}
                    hint={t('admin.import.fileHint', { size: maxKilobytes })}
                    chooseLabel={t('admin.import.choose')}
                    accept=".csv,text/csv"
                    disabled={isChecking || commit.processing}
                    onFilesSelected={check}
                />

                {commit.errors.token && (
                    <Alert tone="danger" title={commit.errors.token} />
                )}

                {result && (
                    <Stack gap="default">
                        <Heading level={2} variant="group">
                            {t('admin.import.resultTitle', {
                                file: result.fileName,
                            })}
                        </Heading>

                        {result.fileErrors.length > 0 ? (
                            <Alert
                                tone="danger"
                                title={t('admin.import.fileInvalid')}
                                description={result.fileErrors.join(' ')}
                            />
                        ) : result.invalid > 0 ? (
                            <Alert
                                tone="danger"
                                title={t('admin.import.rowsInvalid', {
                                    invalid: result.invalid,
                                    total: result.total,
                                })}
                                description={t('admin.import.rowsInvalidHelp')}
                            />
                        ) : (
                            <Alert
                                tone="success"
                                title={t('admin.import.ready', {
                                    total: result.total,
                                })}
                            />
                        )}

                        {result.rows.length > 0 && (
                            <DataTable<Row>
                                caption={t('admin.import.tableCaption')}
                                rows={result.rows}
                                rowKey={(row) => row.line}
                                emptyState={null}
                                columns={[
                                    {
                                        key: 'line',
                                        priority: 'secondary',
                                        header: t('admin.import.columnLine'),
                                        render: (row) => row.line,
                                    },
                                    {
                                        key: 'label',
                                        priority: 'primary',
                                        header: t('admin.import.columnRecord'),
                                        render: (row) =>
                                            row.label === '' ? '—' : row.label,
                                    },
                                    {
                                        key: 'status',
                                        priority: 'status',
                                        header: t('admin.import.columnStatus'),
                                        render: (row) =>
                                            row.errors.length === 0 ? (
                                                <Badge tone="success">
                                                    {t('admin.import.rowValid')}
                                                </Badge>
                                            ) : (
                                                <Text tone="danger">
                                                    {row.errors.join(' ')}
                                                </Text>
                                            ),
                                    },
                                ]}
                            />
                        )}

                        {result.token !== null && (
                            <FormActions>
                                <Button
                                    onClick={importRows}
                                    isPending={commit.processing}
                                >
                                    {t('admin.import.submit', {
                                        total: result.total,
                                    })}
                                </Button>
                            </FormActions>
                        )}
                    </Stack>
                )}
            </Stack>
        </>
    );
}

AdminImportShow.layout = {
    breadcrumbs: [{ title: 'admin.dashboard', href: adminIndex() }],
};
