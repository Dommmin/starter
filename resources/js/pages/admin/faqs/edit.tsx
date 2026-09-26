import { Head, usePage } from '@inertiajs/react';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { index } from '@/routes/admin/faqs';
import { FaqForm } from './form';

export default function AdminFaqsEdit() {
    const editor = usePage<App.Data.Admin.Faqs.FaqEditorData>().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('admin.faqs.editTitle')} />
            <FaqForm key={editor.faq.id} editor={editor} />
        </>
    );
}

AdminFaqsEdit.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.faqs.title', href: index() },
    ],
};
