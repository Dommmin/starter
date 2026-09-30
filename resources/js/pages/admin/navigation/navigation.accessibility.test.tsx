import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import { MenuItemForm } from './form';
import AdminNavigationIndex from './index';

type IndexProps = App.Data.Admin.Navigation.MenuIndexData;
type EditorProps = App.Data.Admin.Navigation.MenuItemEditorData;
type TreeItem = App.Data.Admin.Navigation.MenuTreeItemData;

const putMock = vi.fn();

let pageProps: IndexProps;

vi.mock('@inertiajs/react', async () => {
    const actual =
        await vi.importActual<typeof import('@inertiajs/react')>(
            '@inertiajs/react',
        );

    return {
        ...actual,
        usePage: () => ({ props: pageProps }),
        router: {
            put: (...args: unknown[]) => putMock(...args),
            visit: vi.fn(),
            delete: vi.fn(),
            on: () => () => {},
        },
        Head: ({ children }: { children?: ReactNode }) => <>{children}</>,
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
    };
});

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

const mountedRoots: Root[] = [];

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

const locales: App.Data.Content.ContentLocalesData = {
    available: [
        { code: 'en', name: 'English', native: 'English', dir: 'ltr' },
        { code: 'pl', name: 'Polish', native: 'Polski', dir: 'ltr' },
    ],
    default: 'en',
};

function treeItem(overrides: Partial<TreeItem> & { id: number }): TreeItem {
    return {
        type: 'external',
        label: `Item ${overrides.id}`,
        anchor: null,
        url: 'https://example.com',
        openInNewTab: false,
        targetMissing: false,
        draftTarget: false,
        children: [],
        ...overrides,
    };
}

async function render(node: ReactNode): Promise<HTMLElement> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(
            <I18nProvider initialPage={initialPage}>{node}</I18nProvider>,
        );
    });

    return container;
}

afterEach(async () => {
    putMock.mockClear();
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('AdminNavigationIndex', () => {
    it('lists the tree with target states and edit links', async () => {
        pageProps = {
            location: 'header',
            locale: 'en',
            locales,
            can: { create: true, reorder: true, delete: true },
            items: [
                treeItem({
                    id: 1,
                    type: 'group',
                    label: 'Company',
                    url: null,
                    children: [
                        treeItem({
                            id: 2,
                            type: 'page',
                            label: 'Draft page',
                            url: null,
                            draftTarget: true,
                        }),
                    ],
                }),
                treeItem({
                    id: 3,
                    type: 'page',
                    label: null,
                    url: null,
                    targetMissing: true,
                }),
            ],
        };

        const container = await render(<AdminNavigationIndex />);
        const rows = container.querySelectorAll('tbody tr');

        expect(container.querySelector('table caption')?.textContent).toBe(
            'admin.navigation.tableCaption',
        );
        expect(rows).toHaveLength(3);
        expect(rows[1].textContent).toContain('admin.navigation.childMarker');
        expect(rows[1].textContent).toContain('admin.navigation.draftTarget');
        expect(rows[2].textContent).toContain('admin.navigation.targetMissing');
        expect(
            container.querySelector('a[href="/admin/navigation/2/edit"]'),
        ).not.toBeNull();
        expect(
            container.querySelector(
                'a[href="/admin/navigation/create?location=header&locale=en"]',
            ),
        ).not.toBeNull();
    });

    it('saves a new first-level order', async () => {
        pageProps = {
            location: 'footer',
            locale: 'pl',
            locales,
            can: { create: true, reorder: true, delete: true },
            items: [treeItem({ id: 5 }), treeItem({ id: 6 })],
        };

        const container = await render(<AdminNavigationIndex />);
        const moveDown = container.querySelector<HTMLButtonElement>(
            'ol button[aria-label="orderable.moveDown"]',
        );

        await act(async () => {
            moveDown?.click();
        });

        expect(putMock).toHaveBeenCalledTimes(1);
        expect(putMock.mock.calls[0][0]).toBe('/admin/navigation/order');
        expect(putMock.mock.calls[0][1]).toEqual({
            location: 'footer',
            locale: 'pl',
            parentId: null,
            ids: [6, 5],
        });
    });

    it('shows the empty state and hides ordering without items', async () => {
        pageProps = {
            location: 'header',
            locale: 'en',
            locales,
            can: { create: false, reorder: true, delete: true },
            items: [],
        };

        const container = await render(<AdminNavigationIndex />);

        expect(container.textContent).toContain('admin.navigation.emptyTitle');
        expect(container.querySelector('ol')).toBeNull();
        expect(container.textContent).not.toContain('admin.navigation.create');
    });
});

function editor(item: Partial<EditorProps['item']> = {}): EditorProps {
    return {
        item: {
            id: 9,
            updatedAt: '2026-09-01T10:00:00+00:00',
            location: 'header',
            locale: 'en',
            parentId: null,
            type: 'page',
            pageId: null,
            articleId: null,
            anchor: null,
            url: null,
            label: null,
            openInNewTab: false,
            targetMissing: false,
            draftTarget: false,
            hasChildren: false,
            ...item,
        },
        locales,
        pages: [{ id: 4, title: 'About', draft: true }],
        articles: [],
        parents: [],
        homeAnchors: [
            { anchor: 'hero', sectionType: 'hero', enabled: true },
            { anchor: 'faq', sectionType: 'faq', enabled: false },
        ],
        can: { create: true, reorder: true, delete: true },
    };
}

const fieldNames = (container: HTMLElement) =>
    Array.from(container.querySelectorAll('[name]')).map((element) =>
        element.getAttribute('name'),
    );

describe('MenuItemForm', () => {
    it('shows the fields of the item type', async () => {
        const page = await render(<MenuItemForm editor={editor()} />);
        expect(fieldNames(page)).toContain('label');
        expect(fieldNames(page)).not.toContain('url');
        expect(fieldNames(page)).not.toContain('open_in_new_tab');
        expect(page.textContent).toContain('admin.navigation.fields.labelHint');

        const external = await render(
            <MenuItemForm
                editor={editor({
                    type: 'external',
                    url: 'https://example.com',
                    label: 'Docs',
                })}
            />,
        );
        expect(fieldNames(external)).toContain('url');
        expect(fieldNames(external)).toContain('open_in_new_tab');
        expect(external.textContent).toContain(
            'admin.navigation.fields.labelRequiredHint',
        );

        const anchor = await render(
            <MenuItemForm
                editor={editor({
                    type: 'anchor',
                    anchor: 'features',
                    label: 'Features',
                })}
            />,
        );
        expect(fieldNames(anchor)).toContain('anchor');
        expect(fieldNames(anchor)).not.toContain('url');

        const group = await render(
            <MenuItemForm
                editor={editor({ type: 'group', label: 'Company' })}
            />,
        );
        expect(fieldNames(group)).not.toContain('parent_id');
    });

    it('offers home page sections for an anchor without a page and free text on a page', async () => {
        const home = await render(
            <MenuItemForm
                editor={editor({
                    type: 'anchor',
                    anchor: 'hero',
                    label: 'Top',
                })}
            />,
        );
        const homeAnchor = home.querySelector('[name="anchor"]');
        expect(homeAnchor?.tagName).toBe('SELECT');
        expect(
            Array.from(home.querySelectorAll('select[name="anchor"] option'))
                .map((option) => option.getAttribute('value'))
                .filter(Boolean),
        ).toEqual(['hero', 'faq']);
        expect(home.textContent).toContain(
            'admin.navigation.fields.homeAnchor',
        );

        const onPage = await render(
            <MenuItemForm
                editor={editor({
                    type: 'anchor',
                    pageId: 4,
                    anchor: 'team',
                    label: 'Team',
                })}
            />,
        );
        expect(onPage.querySelector('[name="anchor"]')?.tagName).toBe('INPUT');
        expect(onPage.textContent).toContain(
            'admin.navigation.fields.anchorHint',
        );
    });

    it('marks a deleted target', async () => {
        const container = await render(
            <MenuItemForm editor={editor({ targetMissing: true })} />,
        );

        expect(container.textContent).toContain(
            'admin.navigation.targetMissing',
        );
    });
});
