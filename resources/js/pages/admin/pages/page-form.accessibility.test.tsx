import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import AdminPagesCreate from './create';
import AdminPagesEdit from './edit';

type Errors = Record<string, string>;

const submitMock = vi.fn();
const visitMock = vi.fn();
let nextErrors: Errors | null = null;
let editorProps: App.Data.Admin.Pages.PageEditorData;

vi.mock('@inertiajs/react', async () => {
    const React = await import('react');

    function useForm<Data extends object>(initial: Data) {
        const [data, setDataState] = React.useState(initial);
        const [errors, setErrors] = React.useState<Errors>({});
        const transformRef = React.useRef<(value: Data) => unknown>(
            (value) => value,
        );

        return {
            data,
            errors,
            processing: false,
            isDirty: false,
            setData: (key: keyof Data, value: unknown) =>
                setDataState((current) => ({ ...current, [key]: value })),
            transform: (callback: (value: Data) => unknown) => {
                transformRef.current = callback;
            },
            setDefaults: () => {},
            submit: (
                route: { url: string; method: string },
                options: {
                    onError?: (errors: Errors) => void;
                    onSuccess?: () => void;
                },
            ) => {
                submitMock(route, transformRef.current(data));

                if (nextErrors) {
                    setErrors(nextErrors);
                    options.onError?.(nextErrors);
                } else {
                    options.onSuccess?.();
                }
            },
        };
    }

    return {
        useForm,
        usePage: () => ({ props: editorProps }),
        router: {
            visit: (...args: unknown[]) => visitMock(...args),
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

const locales: App.Data.Content.ContentLocalesData = {
    available: [
        { code: 'en', name: 'English', native: 'English', dir: 'ltr' },
        { code: 'de', name: 'German', native: 'Deutsch', dir: 'ltr' },
    ],
    default: 'en',
};

function blankTranslation(): App.Data.Admin.Pages.PageTranslationFormData {
    return {
        title: '',
        slug: '',
        metaDescription: null,
        body: null,
        status: 'draft',
        publishedAt: null,
    };
}

function makeEditor(
    can: Partial<App.Data.Admin.Pages.PageAbilitiesData> = {},
    page: Partial<App.Data.Admin.Pages.PageFormData> = {},
    previewUrls: Record<string, string> = {},
): App.Data.Admin.Pages.PageEditorData {
    return {
        page: {
            id: null,
            updatedAt: null,
            translations: { en: blankTranslation(), de: blankTranslation() },
            ...page,
        },
        locales,
        can: { create: true, publish: true, delete: false, ...can },
        previewUrls,
    };
}

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

const mountedRoots: Root[] = [];

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

function input(container: HTMLElement, name: string): HTMLInputElement {
    return container.querySelector<HTMLInputElement>(
        `[role="tabpanel"] input[name="${name}"]`,
    )!;
}

async function type(element: HTMLInputElement, value: string) {
    await act(async () => {
        Object.getOwnPropertyDescriptor(
            window.HTMLInputElement.prototype,
            'value',
        )!.set!.call(element, value);
        element.dispatchEvent(new Event('input', { bubbles: true }));
    });
}

async function submit(container: HTMLElement) {
    const button = container.querySelector<HTMLButtonElement>(
        '[role="tabpanel"] button[type="submit"]',
    )!;

    await act(async () => {
        button.click();
    });
}

/** Locale code of the selected tab (Radix trigger ids end with `-trigger-{value}`). */
function activeTab(container: HTMLElement): string | undefined {
    return container
        .querySelector('[role="tab"][aria-selected="true"]')
        ?.id.split('-trigger-')[1];
}

beforeEach(() => {
    nextErrors = null;
});

afterEach(async () => {
    submitMock.mockClear();
    visitMock.mockClear();
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('PageForm', () => {
    it('opens the preview of the saved active translation in a new tab', async () => {
        editorProps = makeEditor(
            {},
            {
                id: 5,
                updatedAt: '2026-09-01T10:00:00+00:00',
                translations: {
                    en: {
                        ...blankTranslation(),
                        title: 'About',
                        slug: 'about',
                    },
                    de: blankTranslation(),
                },
            },
            { en: 'http://localhost/admin/pages/5/preview/en?signature=x' },
        );
        const container = await render(<AdminPagesEdit />);

        const link =
            container.querySelector<HTMLAnchorElement>('a[target="_blank"]');
        expect(link?.getAttribute('href')).toBe(
            'http://localhost/admin/pages/5/preview/en?signature=x',
        );
        expect(link?.getAttribute('rel')).toBe('noopener noreferrer');
        expect(link?.textContent).toContain('admin.pages.preview');
        expect(link?.textContent).toContain('a11y.opensInNewTab');
        expect(container.textContent).toContain('admin.pages.previewHint');
    });

    it('disables the preview of an unsaved language version with an explanation', async () => {
        editorProps = makeEditor(
            {},
            {
                id: 5,
                updatedAt: '2026-09-01T10:00:00+00:00',
                translations: {
                    en: blankTranslation(),
                    de: blankTranslation(),
                },
            },
        );
        const container = await render(<AdminPagesEdit />);

        expect(container.querySelector('a[target="_blank"]')).toBeNull();
        const button = [...container.querySelectorAll('button')].find(
            (element) => element.textContent === 'admin.pages.preview',
        );
        expect(button?.disabled).toBe(true);
        expect(container.textContent).toContain(
            'admin.pages.previewUnavailable',
        );
    });

    it('offers no preview link on the create screen', async () => {
        editorProps = makeEditor();
        const container = await render(<AdminPagesCreate />);

        expect(container.querySelector('a[target="_blank"]')).toBeNull();
        expect(container.textContent).not.toContain('admin.pages.preview');
    });

    it('derives the slug from the title until the slug is edited by hand', async () => {
        editorProps = makeEditor();
        const container = await render(<AdminPagesCreate />);

        await type(input(container, 'title'), 'Zażółć gęślą jaźń – Straße!');
        expect(input(container, 'slug').value).toBe(
            'zazolc-gesla-jazn-strasse',
        );

        await type(input(container, 'slug'), 'custom-address');
        await type(input(container, 'title'), 'Another title');
        expect(input(container, 'slug').value).toBe('custom-address');
    });

    it('does not overwrite the slug of an existing translation', async () => {
        editorProps = makeEditor(
            {},
            {
                id: 5,
                updatedAt: '2026-09-01T10:00:00+00:00',
                translations: {
                    en: {
                        ...blankTranslation(),
                        title: 'About',
                        slug: 'about',
                    },
                    de: blankTranslation(),
                },
            },
        );
        const container = await render(<AdminPagesEdit />);

        await type(input(container, 'title'), 'About the company');
        expect(input(container, 'slug').value).toBe('about');
    });

    it('submits all locales with snake_case keys and the loaded version', async () => {
        editorProps = makeEditor(
            {},
            {
                id: 5,
                updatedAt: '2026-09-01T10:00:00+00:00',
                translations: {
                    en: {
                        ...blankTranslation(),
                        title: 'About',
                        slug: 'about',
                        metaDescription: 'Who we are',
                    },
                    de: blankTranslation(),
                },
            },
        );
        const container = await render(<AdminPagesEdit />);

        await submit(container);

        expect(submitMock).toHaveBeenCalledTimes(1);
        const [route, payload] = submitMock.mock.calls[0];
        expect(route).toEqual({ url: '/admin/pages/5', method: 'put' });
        expect(payload).toEqual({
            updated_at: '2026-09-01T10:00:00+00:00',
            translations: {
                en: {
                    title: 'About',
                    slug: 'about',
                    meta_description: 'Who we are',
                    body: null,
                    status: 'draft',
                },
                de: {
                    title: '',
                    slug: '',
                    meta_description: '',
                    body: null,
                    status: 'draft',
                },
            },
        });
    });

    it('switches to the language tab with the first error and lists it', async () => {
        editorProps = makeEditor();
        nextErrors = { 'translations.de.slug': 'The slug is taken.' };
        const container = await render(<AdminPagesCreate />);

        expect(activeTab(container)).toBe('en');

        await submit(container);

        expect(activeTab(container)).toBe('de');
        const summary = container.querySelector('[role="tabpanel"] a');
        expect(summary?.textContent).toBe(
            'admin.pages.fields.slug: The slug is taken.',
        );
        const slug = input(container, 'slug');
        expect(slug.getAttribute('aria-invalid')).toBe('true');
        expect(document.activeElement).toBe(slug);
    });

    it('blocks the published status without the publish ability', async () => {
        editorProps = makeEditor({ publish: false });
        const container = await render(<AdminPagesCreate />);

        const published = container.querySelector<HTMLOptionElement>(
            '[role="tabpanel"] select[name="status"] option[value="published"]',
        );
        expect(published?.disabled).toBe(true);
    });

    it('offers to reload the latest version after an edit conflict', async () => {
        editorProps = makeEditor(
            {},
            {
                id: 5,
                updatedAt: '2026-09-01T10:00:00+00:00',
                translations: {
                    en: {
                        ...blankTranslation(),
                        title: 'About',
                        slug: 'about',
                    },
                    de: blankTranslation(),
                },
            },
        );
        nextErrors = { conflict: 'Changed by someone else.' };
        const container = await render(<AdminPagesEdit />);

        await submit(container);

        const dialog = document.body.querySelector('[role="dialog"]');
        expect(dialog?.textContent).toContain('admin.pages.conflictTitle');

        const reload = Array.from(
            dialog?.querySelectorAll('button') ?? [],
        ).find((button) => button.textContent === 'admin.pages.conflictReload');

        await act(async () => {
            reload?.click();
        });

        expect(visitMock).toHaveBeenCalledWith('/admin/pages/5/edit', {
            preserveState: false,
        });
    });
});
