import { Head, usePage } from '@inertiajs/react';
import { ContentRevisions } from '@/components/admin/content-revisions';
import { RecordHistory } from '@/components/admin/record-history';
import { Stack } from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { index as articlesIndex } from '@/routes/admin/articles';
import { restore } from '@/routes/admin/articles/revisions';
import { ArticleForm } from './article-form';

export default function AdminArticlesEdit() {
    const editor = usePage<App.Data.Admin.Articles.ArticleEditorData>().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('admin.articles.editTitle')} />
            <Stack gap="relaxed">
                <ArticleForm key={editor.article.id} editor={editor} />
                <ContentRevisions
                    restoreUrl={(revision) =>
                        restore.url({
                            article: Number(editor.article.id),
                            revision,
                        })
                    }
                />
                <RecordHistory />
            </Stack>
        </>
    );
}

AdminArticlesEdit.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.articles.title', href: articlesIndex() },
    ],
};
