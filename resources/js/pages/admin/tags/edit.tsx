import { Head, usePage } from '@inertiajs/react';
import { RecordHistory } from '@/components/admin/record-history';
import { Stack } from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { index } from '@/routes/admin/tags';
import { TagEditor } from './form';

export default function AdminTagsEdit() {
    const editor = usePage<App.Data.Admin.Tags.TagEditorData>().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('admin.tags.editTitle')} />
            <Stack gap="relaxed">
                <TagEditor key={editor.tag.id} editor={editor} />
                <RecordHistory />
            </Stack>
        </>
    );
}

AdminTagsEdit.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.tags.title', href: index() },
    ],
};
