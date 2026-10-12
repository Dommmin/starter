import { Head, usePage } from '@inertiajs/react';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { DashboardOverview, type SystemWarnings } from './dashboard-overview';

type AdminPageProps = {
    overview: App.Data.Admin.Dashboard.DashboardOverviewData;
    systemWarnings: SystemWarnings;
};

export default function AdminIndex() {
    const { overview, systemWarnings } = usePage<AdminPageProps>().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('admin.dashboard')} />
            <DashboardOverview
                overview={overview}
                systemWarnings={systemWarnings}
            />
        </>
    );
}

AdminIndex.layout = {
    breadcrumbs: [
        {
            title: 'admin.dashboard',
            href: adminIndex(),
        },
    ],
};
