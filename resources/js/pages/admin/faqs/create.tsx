import { Head, usePage } from '@inertiajs/react';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { create, index } from '@/routes/admin/faqs';
import { FaqForm } from './form';

export default function AdminFaqsCreate() {
    const editor = usePage<App.Data.Admin.Faqs.FaqEditorData>().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('admin.faqs.createTitle')} />
            <FaqForm editor={editor} />
        </>
    );
}

AdminFaqsCreate.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.faqs.title', href: index() },
        { title: 'admin.faqs.createTitle', href: create() },
    ],
};
