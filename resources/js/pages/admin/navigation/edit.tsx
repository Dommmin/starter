import { Head, usePage } from '@inertiajs/react';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { index } from '@/routes/admin/navigation';
import { MenuItemForm } from './form';

export default function AdminNavigationEdit() {
    const editor =
        usePage<App.Data.Admin.Navigation.MenuItemEditorData>().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('admin.navigation.editTitle')} />
            <MenuItemForm key={editor.item.id} editor={editor} />
        </>
    );
}

AdminNavigationEdit.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.navigation.title', href: index() },
    ],
};
