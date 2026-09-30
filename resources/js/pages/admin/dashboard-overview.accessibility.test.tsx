import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import { DashboardOverview } from './dashboard-overview';

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
        contact: { recent: 5, failed: 1, recentDays: 7 },
        quarantinedMedia: 2,
        recentActivity: [],
        ...overrides,
    };
}

const mountedRoots: Root[] = [];

async function render(overview: Overview): Promise<HTMLElement> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(
            <I18nProvider initialPage={initialPage}>
                <DashboardOverview overview={overview} />
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
    it('leads with the site name and links every count to its filtered list', async () => {
        const container = await render(makeOverview());

        expect(container.querySelector('h1')?.textContent).toBe('Bakery Nowak');
        expect(
            linkNamed(
                container,
                'admin.overview.articles.drafts',
            )?.getAttribute('href'),
        ).toBe('/admin/articles?status=draft&locale=pl');
        expect(
            linkNamed(
                container,
                'admin.overview.articles.scheduled',
            )?.getAttribute('href'),
        ).toBe('/admin/articles?status=scheduled&locale=pl');
        expect(
            linkNamed(
                container,
                'admin.overview.contact.failedLink',
            )?.getAttribute('href'),
        ).toBe('/admin/contact?status=failed');
        expect(
            linkNamed(
                container,
                'admin.overview.media.quarantineLink',
            )?.getAttribute('href'),
        ).toBe('/admin/media?status=quarantine');
        expect(
            Array.from(container.querySelectorAll('h2')).map(
                (heading) => heading.textContent,
            ),
        ).toEqual([
            'admin.overview.articles.title',
            'admin.overview.pages.title',
            'admin.overview.contact.title',
            'admin.overview.media.title',
            'admin.overview.activity.title',
        ]);
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
            'admin.overview.articles.title',
            'admin.overview.pages.title',
        ]);
        expect(container.querySelector('a[href="/admin/audit"]')).toBeNull();
    });

    it('shows an empty state with a create action when there is no content', async () => {
        const empty = { published: 0, drafts: 0, scheduled: 0 };
        const container = await render(
            makeOverview({ articles: empty, pages: empty }),
        );

        expect(container.textContent).toContain('admin.overview.emptyTitle');
        expect(
            container.querySelector('a[href="/admin/pages/create"]')
                ?.textContent,
        ).toBe('admin.overview.emptyAction');
        expect(
            linkNamed(container, 'admin.overview.articles.drafts'),
        ).toBeNull();
    });
});
