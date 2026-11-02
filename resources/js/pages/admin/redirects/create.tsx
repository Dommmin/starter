import { Head, usePage } from '@inertiajs/react';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { create, index } from '@/routes/admin/redirects';
import { RedirectForm } from './form';

export default function AdminRedirectsCreate() {
    const editor = usePage<App.Data.Admin.Redirects.RedirectEditorData>().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('admin.redirects.createTitle')} />
            <RedirectForm editor={editor} />
        </>
    );
}

AdminRedirectsCreate.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.redirects.title', href: index() },
        { title: 'admin.redirects.createTitle', href: create() },
    ],
};
