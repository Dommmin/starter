import { Head, usePage } from '@inertiajs/react';
import { Alert, Stack } from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { index as pagesIndex } from '@/routes/admin/pages';
import { edit, index, update } from '@/routes/admin/pages/sections';
import { SectionForm } from '../../home-sections/section-form';

type Props = App.Data.Admin.Pages.PageSectionEditorData;

export default function AdminPageSectionsEdit() {
    const { pageId, editor, sharedUses } = usePage<Props>().props;
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
                {sharedUses !== null && (
                    <Alert
                        title={t('admin.pages.blocks.sharedTitle')}
                        description={t('admin.pages.blocks.sharedDescription', {
                            count: sharedUses,
                        })}
                    />
                )}
                <SectionForm
                    key={editor.section.id}
                    editor={editor}
                    updateAction={update({
                        page: pageId,
                        pageSection: editor.section.id,
                    })}
                    editUrl={edit.url({
                        page: pageId,
                        pageSection: editor.section.id,
                    })}
                    cancelHref={index(pageId, {
                        query: { locale: editor.section.locale },
                    })}
                />
            </Stack>
        </>
    );
}

AdminPageSectionsEdit.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.pages.title', href: pagesIndex() },
    ],
};
