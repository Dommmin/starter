import { Head, usePage } from '@inertiajs/react';
import { RecordHistory } from '@/components/admin/record-history';
import { Stack } from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { index as pagesIndex } from '@/routes/admin/pages';
import { PageForm } from './page-form';

export default function AdminPagesEdit() {
    const editor = usePage<App.Data.Admin.Pages.PageEditorData>().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('admin.pages.editTitle')} />
            <Stack gap="relaxed">
                <PageForm key={editor.page.id} editor={editor} />
                <RecordHistory />
            </Stack>
        </>
    );
}

AdminPagesEdit.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.pages.title', href: pagesIndex() },
    ],
};
