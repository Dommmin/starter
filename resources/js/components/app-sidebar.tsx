import { Link, usePage } from '@inertiajs/react';
import {
    CircleHelp,
    FileText,
    History,
    LayoutGrid,
    Settings,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useTranslation } from '@/i18n';
import { index as adminIndex } from '@/routes/admin';
import { index as auditIndex } from '@/routes/admin/audit';
import { index as faqsIndex } from '@/routes/admin/faqs';
import { index as pagesIndex } from '@/routes/admin/pages';
import { index as usersIndex } from '@/routes/admin/users';
import { edit as editProfile } from '@/routes/profile';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { t } = useTranslation();
    const { auth } = usePage().props;

    const mainNavItems: NavItem[] = [
        {
            title: t('admin.dashboard'),
            href: adminIndex(),
            icon: LayoutGrid,
        },
        {
            title: t('admin.pages.navLabel'),
            href: pagesIndex(),
            icon: FileText,
        },
        {
            title: t('admin.faqs.navLabel'),
            href: faqsIndex(),
            icon: CircleHelp,
        },
        ...(auth.can.manageUsers
            ? [
                  {
                      title: t('admin.users.title'),
                      href: usersIndex(),
                      icon: Users,
                  },
              ]
            : []),
        ...(auth.can.viewAudit
            ? [
                  {
                      title: t('admin.audit.navLabel'),
                      href: auditIndex(),
                      icon: History,
                  },
              ]
            : []),
        {
            title: t('admin.nav.settings'),
            href: editProfile(),
            icon: Settings,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={adminIndex()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
