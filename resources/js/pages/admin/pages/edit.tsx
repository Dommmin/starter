import { Head, usePage } from '@inertiajs/react';
import { ContentRevisions } from '@/components/admin/content-revisions';
import { RecordHistory } from '@/components/admin/record-history';
import { SharePreviewLinks } from '@/components/admin/share-preview-links';
import { Stack } from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { index as pagesIndex } from '@/routes/admin/pages';
import { destroy, store } from '@/routes/admin/pages/preview-links';
import { restore } from '@/routes/admin/pages/revisions';
import { PageForm } from './page-form';

export default function AdminPagesEdit() {
    const editor = usePage<App.Data.Admin.Pages.PageEditorData>().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('admin.pages.editTitle')} />
            <Stack gap="relaxed">
                <PageForm key={editor.page.id} editor={editor} />
                <SharePreviewLinks
                    locales={Object.keys(editor.previewUrls)}
                    createUrl={store.url({ page: Number(editor.page.id) })}
                    revokeUrl={(previewLink) =>
                        destroy.url({
                            page: Number(editor.page.id),
                            previewLink,
                        })
                    }
                />
                <ContentRevisions
                    restoreUrl={(revision) =>
                        restore.url({ page: Number(editor.page.id), revision })
                    }
                />
                <RecordHistory />
            </Stack>
        </>
    );
}

AdminPagesEdit.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.pages.title', href: pagesIndex() },
    ],
};
