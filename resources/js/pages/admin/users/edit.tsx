import { Head, usePage } from '@inertiajs/react';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { index as usersIndex } from '@/routes/admin/users';
import { UserForm } from './user-form';

export default function AdminUsersEdit() {
    const editor = usePage<App.Data.Admin.Users.UserEditorData>().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('admin.users.editTitle')} />
            <UserForm key={editor.user.id} editor={editor} />
        </>
    );
}

AdminUsersEdit.layout = {
    breadcrumbs: [
        { title: 'admin.dashboard', href: adminIndex() },
        { title: 'admin.users.title', href: usersIndex() },
    ],
};
