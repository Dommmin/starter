import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import DesignSystemShowcase from './index';
import { showcaseFamilies } from './sections';

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: {} }),
    router: { on: () => () => {} },
    Head: ({ children }: { children?: ReactNode }) => <>{children}</>,
    Link: ({
        href,
        children,
        ...rest
    }: {
        href: string | { url: string };
        children: ReactNode;
        [key: string]: unknown;
    }) => (
        <a href={typeof href === 'string' ? href : href.url} {...rest}>
            {children}
        </a>
    ),
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
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('DesignSystemShowcase', () => {
    it('registers at least the ADM-01 family with unique anchors', () => {
        const ids = showcaseFamilies.map((family) => family.id);

        expect(ids).toContain('adm-01');
        expect(new Set(ids).size).toBe(ids.length);
    });

    it('renders every registered family as a labelled section with an anchor', async () => {
        const container = await render(<DesignSystemShowcase />);

        for (const family of showcaseFamilies) {
            const section = container.querySelector(`section#${family.id}`);
            expect(section).not.toBeNull();

            const headingId = section?.getAttribute('aria-labelledby');
            const heading = headingId
                ? container.querySelector(`h2#${headingId}`)
                : null;
            expect(heading?.textContent).toBe(family.titleKey);
            expect(section?.contains(heading ?? null)).toBe(true);

            const tocLink = container.querySelector(`a[href="#${family.id}"]`);
            expect(tocLink?.textContent).toBe(family.titleKey);
        }
    });

    it('lists the states that do not apply to a component', async () => {
        const container = await render(<DesignSystemShowcase />);
        const section = container.querySelector('section#adm-01');

        expect(section?.textContent).toContain(
            'admin.designSystem.notApplicable',
        );
        expect(section?.querySelectorAll('h3').length).toBeGreaterThanOrEqual(
            13,
        );
    });

    it('dismisses and restores the dismissible alert demo', async () => {
        const container = await render(<DesignSystemShowcase />);
        const dismiss = container.querySelector<HTMLButtonElement>(
            'button[aria-label="admin.designSystem.foundations.dismiss"]',
        );

        await act(async () => dismiss?.click());

        const restore = Array.from(container.querySelectorAll('button')).find(
            (button) =>
                button.textContent ===
                'admin.designSystem.foundations.restoreAlert',
        );
        expect(restore).toBeDefined();

        await act(async () => restore?.click());

        expect(
            container.querySelector(
                'button[aria-label="admin.designSystem.foundations.dismiss"]',
            ),
        ).not.toBeNull();
    });

    it('registers the table, record view and action families', () => {
        const ids = showcaseFamilies.map((family) => family.id);

        expect(ids).toEqual(
            expect.arrayContaining(['adm-09', 'adm-11', 'adm-12']),
        );
    });

    it('clears the filters of the no-results table demo', async () => {
        const container = await render(<DesignSystemShowcase />);
        const section = container.querySelector('section#adm-09');
        const noResults = () =>
            section?.textContent?.split(
                'admin.designSystem.tables.noResultsTitle',
            ).length ?? 0;
        const before = noResults();
        const clear = Array.from(
            section?.querySelectorAll('button') ?? [],
        ).find(
            (button) =>
                button.textContent === 'admin.designSystem.tables.clearFilters',
        );

        expect(before).toBeGreaterThan(1);

        await act(async () => clear?.click());

        expect(noResults()).toBeLessThan(before);
    });

    it('registers the form families ADM-03, ADM-04 and ADM-08', () => {
        const ids = showcaseFamilies.map((family) => family.id);

        expect(ids).toEqual(
            expect.arrayContaining(['adm-03', 'adm-04', 'adm-08']),
        );
    });

    it('does not move focus into any static error demo on load', async () => {
        await render(<DesignSystemShowcase />);

        expect(document.activeElement).toBe(document.body);
    });

    it('shows field errors and focuses the first invalid field when the demo form is sent empty', async () => {
        const container = await render(<DesignSystemShowcase />);
        const section = container.querySelector('section#adm-08');
        const submit = Array.from(
            section?.querySelectorAll<HTMLButtonElement>(
                'button[type="submit"]',
            ) ?? [],
        ).find(
            (button) =>
                button.textContent === 'admin.designSystem.forms.submit' &&
                !button.disabled,
        );

        await act(async () => submit?.click());

        const form = submit?.closest('form');
        const nameInput =
            form?.querySelector<HTMLInputElement>('input[name="name"]');
        expect(document.activeElement).toBe(nameInput);
        expect(nameInput?.getAttribute('aria-invalid')).toBe('true');

        const errorId = nameInput
            ?.getAttribute('aria-describedby')
            ?.split(' ')
            .at(-1);
        expect(
            errorId ? document.getElementById(errorId)?.textContent : null,
        ).toBe('admin.designSystem.forms.nameRequired');
        expect(
            form?.querySelectorAll('[role="alert"] li').length,
        ).toBeGreaterThanOrEqual(5);
    });
});
