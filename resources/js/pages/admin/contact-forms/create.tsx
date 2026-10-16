import { Head, usePage } from '@inertiajs/react';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { create, index } from '@/routes/admin/contact-forms';
import { ContactFormEditor } from './form';

export default function AdminContactFormsCreate() {
    const editor =
        usePage<App.Data.Admin.ContactForms.ContactFormEditorData>().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('admin.contactForms.createTitle')} />
            <ContactFormEditor editor={editor} />
        </>
    );
}

AdminContactFormsCreate.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.contactForms.title', href: index() },
        { title: 'admin.contactForms.createTitle', href: create() },
    ],
};
