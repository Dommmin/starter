import { Head, usePage } from '@inertiajs/react';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import {
    create as articlesCreate,
    index as articlesIndex,
} from '@/routes/admin/articles';
import { ArticleForm } from './article-form';

export default function AdminArticlesCreate() {
    const editor = usePage<App.Data.Admin.Articles.ArticleEditorData>().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('admin.articles.createTitle')} />
            <ArticleForm editor={editor} />
        </>
    );
}

AdminArticlesCreate.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.articles.title', href: articlesIndex() },
        { title: 'admin.articles.createTitle', href: articlesCreate() },
    ],
};
