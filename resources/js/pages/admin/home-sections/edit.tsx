import { Head, usePage } from '@inertiajs/react';
import { RecordHistory } from '@/components/admin/record-history';
import { Stack } from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { index } from '@/routes/admin/home-sections';
import { SectionForm } from './section-form';

export default function AdminHomeSectionsEdit() {
    const editor =
        usePage<App.Data.Admin.HomeSections.HomeSectionEditorData>().props;
    const { t } = useTranslation();

    return (
        <>
            <Head
                title={t('admin.homeSections.editTitle', {
                    section: t(
                        `admin.homeSections.types.${editor.section.type}`,
                    ),
                })}
            />
            <Stack gap="relaxed">
                <SectionForm key={editor.section.id} editor={editor} />
                <RecordHistory />
            </Stack>
        </>
    );
}

AdminHomeSectionsEdit.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.homeSections.title', href: index() },
    ],
};
