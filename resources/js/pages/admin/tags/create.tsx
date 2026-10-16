import { Head, usePage } from '@inertiajs/react';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { create, index } from '@/routes/admin/tags';
import { TagEditor } from './form';

export default function AdminTagsCreate() {
    const editor = usePage<App.Data.Admin.Tags.TagEditorData>().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('admin.tags.createTitle')} />
            <TagEditor editor={editor} />
        </>
    );
}

AdminTagsCreate.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.tags.title', href: index() },
        { title: 'admin.tags.createTitle', href: create() },
    ],
};
