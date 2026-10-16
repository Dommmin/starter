import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import { DashboardOverview, type SystemWarnings } from './dashboard-overview';

vi.mock('@inertiajs/react', () => ({
    router: { visit: vi.fn(), on: () => () => {} },
    usePage: () => ({ props: {} }),
    Link: ({
        href,
        children,
        prefetch: _prefetch,
        ...props
    }: {
        href: string | { url: string };
        children: ReactNode;
        prefetch?: boolean;
        [key: string]: unknown;
    }) => (
        <a href={typeof href === 'string' ? href : href.url} {...props}>
            {children}
        </a>
    ),
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

type Overview = App.Data.Admin.Dashboard.DashboardOverviewData;

const initialPage = {
    props: {
        i18n: {
            area: 'admin',
            locale: 'en',
            defaultLocale: 'en',
            messages: {},
            fallback: 'en',
            dir: 'ltr',
            availableLocales: [],
        },
    },
} as unknown as Page<PageProps & SharedPageProps>;

function makeOverview(overrides: Partial<Overview> = {}): Overview {
    return {
        siteName: 'Bakery Nowak',
        contentLocale: 'pl',
        articles: { published: 4, drafts: 2, scheduled: 1 },
        pages: { published: 3, drafts: 1, scheduled: 0 },
        contact: {
            recent: 5,
            failed: 1,
            recentDays: 7,
            previous: 3,
            chartDays: 3,
            daily: [
                { date: '2026-10-08', delivered: 2, undelivered: 0 },
                { date: '2026-10-09', delivered: 0, undelivered: 1 },
                { date: '2026-10-10', delivered: 4, undelivered: 0 },
            ],
        },
        quarantinedMedia: 2,
        recentActivity: [],
        publishing: {
            recent: 2,
            previous: 1,
            days: 30,
            nextScheduledAt: '2026-10-12T09:00:00+00:00',
        },
        attention: {
            failedMessages: 1,
            quarantinedMedia: 2,
            rejectedMedia: 0,
            scheduledSoon: 0,
            staleDrafts: 0,
            adminsWithoutTwoFactor: 0,
            soonHours: 48,
            staleDays: 14,
        },
        canCreateArticles: true,
        canCreatePages: true,
        ...overrides,
    };
}

const mountedRoots: Root[] = [];

async function render(
    overview: Overview,
    systemWarnings: SystemWarnings = null,
): Promise<HTMLElement> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(
            <I18nProvider initialPage={initialPage}>
                <DashboardOverview
                    overview={overview}
                    systemWarnings={systemWarnings}
                />
            </I18nProvider>,
        );
    });

    return container;
}

function linkNamed(container: HTMLElement, name: string) {
    return container.querySelector<HTMLAnchorElement>(
        `a[aria-label="${name}"]`,
    );
}

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('DashboardOverview', () => {
    it('leads with the site name and links every figure to its filtered list', async () => {
        const container = await render(makeOverview());

        expect(container.querySelector('h1')?.textContent).toBe('Bakery Nowak');
        expect(
            linkNamed(
                container,
                'admin.overview.kpi.messagesLink',
            )?.getAttribute('href'),
        ).toBe('/admin/contact');
        expect(
            linkNamed(
                container,
                'admin.overview.kpi.scheduledLink',
            )?.getAttribute('href'),
        ).toBe('/admin/articles?status=scheduled&locale=pl');
        expect(
            linkNamed(container, 'admin.overview.kpi.mediaLink')?.getAttribute(
                'href',
            ),
        ).toBe('/admin/media');
        expect(
            Array.from(container.querySelectorAll('h2')).map(
                (heading) => heading.textContent,
            ),
        ).toEqual([
            'admin.overview.attention.title',
            'admin.overview.chart.title',
            'admin.overview.pipeline.title',
            'admin.overview.activity.title',
        ]);
    });

    it('lists only the things that need attention, each with a way to handle it', async () => {
        const container = await render(makeOverview());
        const reviews = Array.from(
            container.querySelectorAll<HTMLAnchorElement>(
                'a[aria-label^="admin.overview.attention.review"]',
            ),
        ).map((link) => link.getAttribute('href'));

        expect(reviews).toEqual([
            '/admin/contact?status=failed',
            '/admin/media?status=quarantine',
        ]);
        expect(container.textContent).not.toContain(
            'admin.overview.attention.allGood',
        );

        const calm = await render(
            makeOverview({
                attention: {
                    failedMessages: 0,
                    quarantinedMedia: 0,
                    rejectedMedia: 0,
                    scheduledSoon: 0,
                    staleDrafts: 0,
                    adminsWithoutTwoFactor: null,
                    soonHours: 48,
                    staleDays: 14,
                },
            }),
        );
        expect(calm.textContent).toContain('admin.overview.attention.allGood');
    });

    it('charts the inbox with a table view of every day', async () => {
        const container = await render(makeOverview());
        const table = container.querySelector('table');

        expect(
            container.querySelector('[role="img"]')?.getAttribute('aria-label'),
        ).toContain('admin.overview.chart.summary');
        expect(table?.closest('[hidden]')).not.toBeNull();
        expect(table?.querySelectorAll('tbody tr')).toHaveLength(3);
    });

    it('omits blocks the user may not open', async () => {
        const container = await render(
            makeOverview({
                contact: null,
                quarantinedMedia: null,
                recentActivity: null,
            }),
        );

        const headings = Array.from(container.querySelectorAll('h2')).map(
            (heading) => heading.textContent,
        );
        expect(headings).toEqual([
            'admin.overview.attention.title',
            'admin.overview.pipeline.title',
        ]);
        expect(container.querySelector('a[href="/admin/audit"]')).toBeNull();
        expect(container.querySelector('table')).toBeNull();
    });

    it('collapses repeated activity into one row', async () => {
        const entry = {
            id: 1,
            actorName: 'Ada',
            action: 'site_settings.updated',
            subjectType: 'site_setting',
            subjectId: 1,
            createdAt: '2026-10-10T10:00:00+00:00',
        } as unknown as App.Data.Admin.Audit.AuditLogListItemData;
        const container = await render(
            makeOverview({
                recentActivity: [
                    entry,
                    { ...entry, id: 2 },
                    { ...entry, id: 3 },
                ],
            }),
        );

        const rows = container.querySelectorAll('h2 ~ ul li, section ul li');
        expect(container.textContent).toContain('admin.overview.repeated');
        expect(
            Array.from(rows).filter((row) =>
                row.textContent?.includes('admin.overview.activity.entry'),
            ),
        ).toHaveLength(1);
    });

    it('lists risky platform settings as attention rows for administrators only', async () => {
        const quiet = await render(makeOverview());
        expect(quiet.textContent).not.toContain(
            'admin.overview.attention.twoFactorOptional',
        );

        const warned = await render(makeOverview(), {
            debugOutsideLocal: false,
            twoFactorOptional: true,
        });
        expect(warned.textContent).toContain(
            'admin.overview.attention.twoFactorOptional',
        );
        expect(warned.querySelector('a[href="/admin/system"]')).not.toBeNull();
    });

    it('shows an empty state with a create action when there is no content', async () => {
        const empty = { published: 0, drafts: 0, scheduled: 0 };
        const container = await render(
            makeOverview({ articles: empty, pages: empty }),
        );

        expect(container.textContent).toContain('admin.overview.emptyTitle');
        expect(
            Array.from(
                container.querySelectorAll('a[href="/admin/pages/create"]'),
            ).map((link) => link.textContent),
        ).toContain('admin.overview.emptyAction');
    });
});
