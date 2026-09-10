import { Head } from '@inertiajs/react';
import { index as adminIndex } from '@/routes/admin';

export default function AdminIndex() {
    return (
        <>
            <Head title="Panel administracyjny" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <section className="border-sidebar-border/70 rounded-xl border p-6">
                    <h1 className="text-xl font-semibold">
                        Panel administracyjny
                    </h1>
                    <p className="text-muted-foreground mt-2">
                        Moduły administracyjne pojawią się tutaj wraz z ich
                        wdrożeniem.
                    </p>
                </section>
            </div>
        </>
    );
}

AdminIndex.layout = {
    breadcrumbs: [
        {
            title: 'Panel administracyjny',
            href: adminIndex(),
        },
    ],
};
