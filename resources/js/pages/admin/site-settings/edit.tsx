import { Head, usePage } from '@inertiajs/react';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { edit as siteSettingsEdit } from '@/routes/admin/site-settings';
import { SiteSettingsForm } from './site-settings-form';

export default function AdminSiteSettingsEdit() {
    const editor =
        usePage<App.Data.Admin.Settings.SiteSettingsEditorData>().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('admin.siteSettings.title')} />
            <SiteSettingsForm editor={editor} />
        </>
    );
}

AdminSiteSettingsEdit.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.siteSettings.title', href: siteSettingsEdit() },
    ],
};
