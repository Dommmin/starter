import { Head, usePage } from '@inertiajs/react';
import { RecordHistory } from '@/components/admin/record-history';
import { Stack } from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { index } from '@/routes/admin/contact-forms';
import { ContactFormEditor } from './form';

export default function AdminContactFormsEdit() {
    const editor =
        usePage<App.Data.Admin.ContactForms.ContactFormEditorData>().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('admin.contactForms.editTitle')} />
            <Stack gap="relaxed">
                <ContactFormEditor key={editor.form.id} editor={editor} />
                <RecordHistory />
            </Stack>
        </>
    );
}

AdminContactFormsEdit.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.contactForms.title', href: index() },
    ],
};
