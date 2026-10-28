import { Head, usePage } from '@inertiajs/react';
import { RecordHistory } from '@/components/admin/record-history';
import { Stack } from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { index } from '@/routes/admin/redirects';
import { RedirectForm } from './form';

export default function AdminRedirectsEdit() {
    const editor = usePage<App.Data.Admin.Redirects.RedirectEditorData>().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('admin.redirects.editTitle')} />
            <Stack gap="relaxed">
                <RedirectForm key={editor.redirect.id} editor={editor} />
                <RecordHistory />
            </Stack>
        </>
    );
}

AdminRedirectsEdit.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.redirects.title', href: index() },
    ],
};
