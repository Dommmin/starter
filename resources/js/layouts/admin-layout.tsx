import { usePage } from '@inertiajs/react';
import {
    CircleHelp,
    Newspaper,
    Palette,
    FileText,
    History,
    Images,
    LayoutGrid,
    LayoutTemplate,
    ListTree,
    Mail,
    Settings,
    SlidersHorizontal,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { AdminShell } from '@/design-system/primitives';
import type {
    AdminBreadcrumb,
    AdminNavGroup,
    AdminNavItem,
} from '@/design-system/primitives';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useTranslation } from '@/i18n';
import { logout } from '@/routes';
import { index as adminIndex } from '@/routes/admin';
import { index as articlesIndex } from '@/routes/admin/articles';
import { index as auditIndex } from '@/routes/admin/audit';
import { index as contactIndex } from '@/routes/admin/contact';
import { index as faqsIndex } from '@/routes/admin/faqs';
import { index as homeSectionsIndex } from '@/routes/admin/home-sections';
import { index as mediaIndex } from '@/routes/admin/media';
import { index as navigationIndex } from '@/routes/admin/navigation';
import { index as pagesIndex } from '@/routes/admin/pages';
import { edit as siteSettingsEdit } from '@/routes/admin/site-settings';
import { index as usersIndex } from '@/routes/admin/users';
import { edit as editProfile } from '@/routes/profile';
import type { BreadcrumbItem } from '@/types';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

export default function AdminLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: ReactNode;
}) {
    const { t } = useTranslation();
    const { auth, site, designSystemUrl } = usePage().props;
    const { currentUrl, isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    const section = (
        id: string,
        label: string,
        href: AdminNavItem['href'],
        icon: AdminNavItem['icon'],
    ): AdminNavItem => ({
        id,
        label,
        href,
        icon,
        isCurrent: isCurrentOrParentUrl(href),
    });

    const navGroups: AdminNavGroup[] = [
        {
            id: 'dashboard',
            items: [
                {
                    id: 'dashboard',
                    label: t('admin.dashboard'),
                    href: adminIndex(),
                    icon: LayoutGrid,
                    isCurrent: isCurrentUrl(adminIndex()),
                },
            ],
        },
        {
            id: 'content',
            label: t('admin.nav.content'),
            items: [
                section(
                    'pages',
                    t('admin.pages.navLabel'),
                    pagesIndex(),
                    FileText,
                ),
                section(
                    'articles',
                    t('admin.articles.navLabel'),
                    articlesIndex(),
                    Newspaper,
                ),
                section(
                    'media',
                    t('admin.media.navLabel'),
                    mediaIndex(),
                    Images,
                ),
                section(
                    'faqs',
                    t('admin.faqs.navLabel'),
                    faqsIndex(),
                    CircleHelp,
                ),
                section(
                    'navigation',
                    t('admin.navigation.navLabel'),
                    navigationIndex(),
                    ListTree,
                ),
                section(
                    'home-sections',
                    t('admin.homeSections.navLabel'),
                    homeSectionsIndex(),
                    LayoutTemplate,
                ),
            ],
        },
        {
            id: 'communication',
            label: t('admin.nav.communication'),
            items: [
                section(
                    'contact',
                    t('admin.contact.navLabel'),
                    contactIndex(),
                    Mail,
                ),
            ],
        },
        {
            id: 'system',
            label: t('admin.nav.system'),
            items: [
                ...(auth.can.manageUsers
                    ? [
                          section(
                              'users',
                              t('admin.users.title'),
                              usersIndex(),
                              Users,
                          ),
                      ]
                    : []),
                ...(auth.can.viewAudit
                    ? [
                          section(
                              'audit',
                              t('admin.audit.navLabel'),
                              auditIndex(),
                              History,
                          ),
                      ]
                    : []),
                ...(auth.can.manageSiteSettings
                    ? [
                          section(
                              'site-settings',
                              t('admin.nav.siteSettings'),
                              siteSettingsEdit(),
                              SlidersHorizontal,
                          ),
                      ]
                    : []),
            ],
        },
        {
            id: 'developer',
            label: t('admin.nav.developer'),
            items: designSystemUrl
                ? [
                      section(
                          'design-system',
                          t('admin.designSystem.navLabel'),
                          designSystemUrl,
                          Palette,
                      ),
                  ]
                : [],
        },
    ].filter((group) => group.items.length > 0);

    const footerItems: AdminNavItem[] = [
        {
            id: 'settings',
            label: t('admin.nav.settings'),
            href: editProfile(),
            icon: Settings,
            isCurrent: currentUrl.startsWith('/settings'),
        },
    ];

    const shellBreadcrumbs: AdminBreadcrumb[] = breadcrumbs.map(
        (item, index) => ({
            label: item.title.includes('.') ? t(item.title) : item.title,
            href: index < breadcrumbs.length - 1 ? item.href : undefined,
        }),
    );

    const roleLabel =
        auth.user.role === 'admin'
            ? t('admin.shell.roleAdmin')
            : auth.user.role === 'editor'
              ? t('admin.shell.roleEditor')
              : undefined;

    return (
        <AdminShell
            brandName={site?.name ?? appName}
            brandSubtitle={t('admin.nav.subtitle')}
            homeHref={adminIndex()}
            navGroups={navGroups}
            footerItems={footerItems}
            breadcrumbs={shellBreadcrumbs}
            user={{
                name: auth.user.name,
                email: auth.user.email,
                roleLabel,
            }}
            profileHref={editProfile()}
            logoutHref={logout()}
        >
            {children}
        </AdminShell>
    );
}
