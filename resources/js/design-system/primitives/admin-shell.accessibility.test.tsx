import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { FileText, LayoutGrid, Settings } from 'lucide-react';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import { AdminShell, type AdminShellProps } from './admin-shell';

const visit = vi.fn();

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: {} }),
    router: {
        on: () => () => {},
        visit: (...args: unknown[]) => visit(...args),
        flushAll: () => {},
        patch: () => {},
    },
    Link: ({
        href,
        children,
        prefetch: _prefetch,
        as: _as,
        ...props
    }: {
        href: string | { url: string };
        children: ReactNode;
        prefetch?: boolean;
        as?: string;
        [key: string]: unknown;
    }) => {
        const targetHref = typeof href === 'string' ? href : href.url;
        return (
            <a href={targetHref} {...props}>
                {children}
            </a>
        );
    },
}));

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

const shellProps: Omit<AdminShellProps, 'children'> = {
    brandName: 'Starter',
    brandSubtitle: 'Administration',
    homeHref: '/admin',
    navGroups: [
        {
            id: 'dashboard',
            items: [
                {
                    id: 'dashboard',
                    label: 'Dashboard',
                    href: '/admin',
                    icon: LayoutGrid,
                    isCurrent: false,
                },
            ],
        },
        {
            id: 'content',
            label: 'Content',
            items: [
                {
                    id: 'pages',
                    label: 'Pages',
                    href: '/admin/pages',
                    icon: FileText,
                    isCurrent: true,
                },
            ],
        },
    ],
    footerItems: [
        {
            id: 'settings',
            label: 'Platform settings',
            href: '/settings/profile',
            icon: Settings,
            isCurrent: false,
        },
    ],
    breadcrumbs: [{ label: 'Dashboard', href: '/admin' }, { label: 'Pages' }],
    user: { name: 'Anna Kowalczyk', email: 'anna@example.test' },
    profileHref: '/settings/profile',
    logoutHref: '/logout',
};

async function render(): Promise<HTMLElement> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(
            <I18nProvider initialPage={initialPage}>
                <AdminShell {...shellProps}>
                    <h1>Pages</h1>
                </AdminShell>
            </I18nProvider>,
        );
    });

    return container;
}

async function pressKey(
    target: EventTarget,
    key: string,
    init: KeyboardEventInit = {},
): Promise<void> {
    await act(async () => {
        target.dispatchEvent(
            new KeyboardEvent('keydown', { key, bubbles: true, ...init }),
        );
    });
}

async function type(input: HTMLInputElement, value: string): Promise<void> {
    await act(async () => {
        Object.getOwnPropertyDescriptor(
            HTMLInputElement.prototype,
            'value',
        )!.set!.call(input, value);
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });
}

function palette(): HTMLElement | null {
    return document.body.querySelector('[role="dialog"]');
}

function accessibleName(dialog: HTMLElement | null): string | null {
    const labelId = dialog?.getAttribute('aria-labelledby');

    return labelId
        ? (document.getElementById(labelId)?.textContent ?? null)
        : null;
}

beforeEach(() => {
    visit.mockReset();
});

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('AdminShell', () => {
    it('exposes landmarks, a skip link and marks the current section', async () => {
        const container = await render();

        expect(
            container.querySelector('a[href="#admin-main"]')?.textContent,
        ).toBe('a11y.skipToContent');
        expect(container.querySelector('main#admin-main')).not.toBeNull();
        expect(
            container.querySelector(
                'aside[aria-label="admin.shell.sidebarLabel"] nav[aria-label="admin.shell.modulesLabel"]',
            ),
        ).not.toBeNull();

        const current = container.querySelectorAll('[aria-current="page"]');
        const currentLink = container.querySelector(
            'a[aria-current="page"][href="/admin/pages"]',
        );
        expect(currentLink?.textContent).toBe('Pages');
        // Only the nav link and the last breadcrumb are current.
        expect(current).toHaveLength(2);

        const group = container.querySelector('[role="group"]');
        const labelId = group?.getAttribute('aria-labelledby');
        expect(labelId && document.getElementById(labelId)?.textContent).toBe(
            'Content',
        );
    });

    it('renders breadcrumbs with the last crumb as the current page', async () => {
        const container = await render();
        const crumbs = container.querySelector(
            'nav[aria-label="admin.shell.breadcrumbLabel"] ol',
        );

        expect(crumbs?.querySelector('a[href="/admin"]')?.textContent).toBe(
            'Dashboard',
        );
        expect(
            crumbs?.querySelector('[aria-current="page"]')?.textContent,
        ).toBe('Pages');
    });

    it('opens the command palette with Ctrl+K and closes it with Escape', async () => {
        await render();
        expect(palette()).toBeNull();

        await pressKey(document, 'k', { ctrlKey: true });

        const dialog = palette();
        expect(accessibleName(dialog)).toBe('admin.command.title');
        const input = dialog?.querySelector<HTMLInputElement>(
            'input[role="combobox"]',
        );
        expect(document.activeElement).toBe(input);

        await pressKey(input as HTMLInputElement, 'Escape');
        expect(palette()).toBeNull();
    });

    it('filters commands, moves the active option and visits it on Enter', async () => {
        await render();
        await pressKey(document, 'k', { metaKey: true });

        const input = palette()?.querySelector<HTMLInputElement>(
            'input[role="combobox"]',
        ) as HTMLInputElement;
        const options = () =>
            Array.from(
                palette()?.querySelectorAll('[role="option"]') ?? [],
            ).map((option) => option.textContent);

        expect(options()).toEqual(['Dashboard', 'Pages', 'Platform settings']);

        await pressKey(input, 'ArrowDown');
        const active = palette()?.querySelector('[aria-selected="true"]');
        expect(active?.textContent).toBe('Pages');
        expect(input.getAttribute('aria-activedescendant')).toBe(active?.id);

        await type(input, 'sett');
        expect(options()).toEqual(['Platform settings']);

        await pressKey(input, 'Enter');
        expect(visit).toHaveBeenCalledWith('/settings/profile');
        expect(palette()).toBeNull();
    });

    it('shows an empty state instead of an empty listbox', async () => {
        await render();
        await pressKey(document, 'k', { ctrlKey: true });

        const input = palette()?.querySelector<HTMLInputElement>(
            'input[role="combobox"]',
        ) as HTMLInputElement;
        await type(input, 'zzz');

        expect(palette()?.querySelectorAll('[role="option"]')).toHaveLength(0);
        expect(input.hasAttribute('aria-activedescendant')).toBe(false);
        expect(palette()?.textContent).toContain('admin.command.empty');

        await pressKey(input, 'Enter');
        expect(visit).not.toHaveBeenCalled();
    });

    it('opens the mobile navigation drawer as a labelled modal', async () => {
        const container = await render();
        const trigger = container.querySelector<HTMLButtonElement>(
            'button[aria-label="a11y.openMenu"]',
        );

        await act(async () => {
            trigger?.click();
        });

        const drawer = palette();
        expect(trigger?.getAttribute('aria-expanded')).toBe('true');
        expect(accessibleName(drawer)).toBe('admin.shell.sidebarLabel');
        // Radix makes the drawer modal by hiding the rest of the page.
        expect(container.getAttribute('aria-hidden')).toBe('true');
        expect(
            drawer?.querySelector('button[aria-label="a11y.closeMenu"]'),
        ).not.toBeNull();
        expect(drawer?.querySelector('a[href="/admin/pages"]')).not.toBeNull();
    });
});
