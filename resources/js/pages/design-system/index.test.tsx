import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import PublicDesignSystemShowcase from './index';
import { publicShowcaseFamilies } from './sections';

/**
 * The page test checks the frame (public chrome, sections, table of
 * contents) with lightweight stand-ins for the demos; behaviour tests render
 * one real family each (`renderFamily`), so no test mounts every demo.
 */
vi.mock('./sections', async (importOriginal) => {
    const actual = await importOriginal<typeof import('./sections')>();

    return {
        publicShowcaseFamilies: actual.publicShowcaseFamilies.map((family) => ({
            ...family,
            Component: () => <p>{`${family.id} demo`}</p>,
        })),
    };
});

const { publicShowcaseFamilies: realFamilies } =
    await vi.importActual<typeof import('./sections')>('./sections');

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            auth: { user: null },
            seo: {
                siteName: 'Demo',
                canonical: 'http://localhost/_design-system',
                defaultImage: null,
                defaultTitle: 'Demo',
                defaultDescription: null,
            },
        },
    }),
    router: { on: () => () => {} },
    Head: ({ children }: { children?: ReactNode }) => <>{children}</>,
    Link: ({
        href,
        children,
        prefetch: _prefetch,
        ...rest
    }: {
        href: string | { url: string };
        children: ReactNode;
        prefetch?: boolean;
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
            area: 'public',
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

async function renderFamily(id: string): Promise<HTMLElement> {
    const family = realFamilies.find((candidate) => candidate.id === id);

    if (!family) {
        throw new Error(`Unknown showcase family ${id}`);
    }

    return render(<family.Component />);
}

function buttonWithText(
    container: ParentNode,
    text: string,
): HTMLButtonElement | undefined {
    return Array.from(
        container.querySelectorAll<HTMLButtonElement>('button'),
    ).find((button) => button.textContent === text && !button.disabled);
}

afterEach(async () => {
    vi.useRealTimers();
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('PublicDesignSystemShowcase', () => {
    it('registers the WEB families in registry order with unique anchors', () => {
        const ids = publicShowcaseFamilies.map((family) => family.id);

        expect(ids).toEqual([
            'web-01',
            'web-02',
            'web-04',
            'web-seo',
            'web-05',
        ]);
    });

    it('renders the families as labelled sections inside the public frame', async () => {
        const container = await render(<PublicDesignSystemShowcase />);

        expect(
            container.querySelector('header a[href="#main-content"]'),
        ).not.toBeNull();
        expect(container.querySelectorAll('main#main-content h1')).toHaveLength(
            1,
        );
        expect(container.querySelector('footer')).not.toBeNull();

        for (const family of publicShowcaseFamilies) {
            const section = container.querySelector(`section#${family.id}`);
            const headingId = section?.getAttribute('aria-labelledby');

            expect(
                headingId
                    ? section?.querySelector(`h2#${headingId}`)?.textContent
                    : null,
            ).toBe(family.titleKey);
            expect(
                container.querySelector(`a[href="#${family.id}"]`),
            ).not.toBeNull();
            expect(section?.textContent).toContain(`${family.id} demo`);
        }
    });

    it('marks the page as not indexable', async () => {
        await render(<PublicDesignSystemShowcase />);

        expect(
            document
                .querySelector('meta[name="robots"]')
                ?.getAttribute('content'),
        ).toBe('noindex,nofollow');
    });

    it.each(realFamilies.map((family) => family.id))(
        '%s does not move focus on load',
        async (id) => {
            await renderFamily(id);

            expect(document.activeElement).toBe(document.body);
        },
    );

    it('validates the live contact demo locally and focuses the first invalid field', async () => {
        const section = await renderFamily('web-05');
        const form = section.querySelector('form');
        const submit = form?.querySelector<HTMLButtonElement>(
            'button[type="submit"]',
        );

        await act(async () => submit?.click());

        const nameInput =
            form?.querySelector<HTMLInputElement>('input[name="name"]');
        expect(document.activeElement).toBe(nameInput);
        expect(nameInput?.getAttribute('aria-invalid')).toBe('true');
        expect(form?.querySelectorAll('[role="alert"] li')).toHaveLength(3);
    });

    it('shows the static field errors on request and focuses the first one', async () => {
        const section = await renderFamily('web-05');

        await act(async () =>
            buttonWithText(
                section,
                'admin.designSystem.web.contact.showErrors',
            )?.click(),
        );

        const focused = document.activeElement as HTMLInputElement | null;
        expect(focused?.name).toBe('name');
        expect(
            focused?.closest('form')?.querySelectorAll('[aria-invalid="true"]'),
        ).toHaveLength(3);
    });

    it('switches the home sections demo to its empty states', async () => {
        const section = await renderFamily('web-02');

        expect(section.textContent).not.toContain('home.latestArticlesEmpty');

        await act(async () =>
            buttonWithText(
                section,
                'admin.designSystem.web.landing.showEmpty',
            )?.click(),
        );

        expect(section.textContent).toContain('home.latestArticlesEmpty');
        expect(section.textContent).toContain('home.faqEmpty');
    });

    it('opens the mobile navigation demo with a submenu and a group heading', async () => {
        const section = await renderFamily('web-01');
        const trigger = section.querySelector<HTMLButtonElement>(
            'button[aria-label="a11y.openMenu"]',
        );

        await act(async () => trigger?.click());

        const dialog = document.querySelector('[role="dialog"]');
        expect(dialog?.querySelector('a[href="#web-02"]')).not.toBeNull();
        expect(
            dialog?.querySelector('a[target="_blank"]')?.getAttribute('rel'),
        ).toBe('noopener noreferrer');
        expect(dialog?.textContent).toContain(
            'admin.designSystem.web.nav.resources',
        );
    });
});
