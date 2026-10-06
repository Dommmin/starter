import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import AdminArticlesCreate from './create';
import AdminArticlesEdit from './edit';

type Errors = Record<string, string>;

const submitMock = vi.fn();
const visitMock = vi.fn();
let nextErrors: Errors | null = null;
let editorProps: App.Data.Admin.Articles.ArticleEditorData;

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

function blankTranslation(): App.Data.Admin.Articles.ArticleTranslationFormData {
    return {
        title: '',
        slug: '',
        excerpt: null,
        metaDescription: null,
        body: null,
        status: 'draft',
        publishedOn: null,
    };
}

function makeEditor(
    can: Partial<App.Data.Admin.Articles.ArticleAbilitiesData> = {},
    article: Partial<App.Data.Admin.Articles.ArticleFormData> = {},
): App.Data.Admin.Articles.ArticleEditorData {
    return {
        article: {
            id: null,
            updatedAt: null,
            coverMediaId: null,
            translations: { en: blankTranslation(), de: blankTranslation() },
            ...article,
        },
        locales,
        can: { create: true, publish: true, delete: false, ...can },
        previewUrls: {},
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

describe('ArticleForm', () => {
    it('submits excerpt, publication day and a null cover for a new article', async () => {
        editorProps = makeEditor();
        const container = await render(<AdminArticlesCreate />);

        await type(input(container, 'title'), 'Company news');
        await type(input(container, 'published_on'), '2026-10-01');
        const excerpt = container.querySelector<HTMLTextAreaElement>(
            '[role="tabpanel"] textarea[name="excerpt"]',
        )!;
        await act(async () => {
            Object.getOwnPropertyDescriptor(
                window.HTMLTextAreaElement.prototype,
                'value',
            )!.set!.call(excerpt, 'Monthly summary');
            excerpt.dispatchEvent(new Event('input', { bubbles: true }));
        });
        await submit(container);

        const [route, payload] = submitMock.mock.lastCall!;
        expect(route.url).toBe('/admin/articles');
        expect(payload).toMatchObject({
            cover_media_id: null,
            translations: {
                en: {
                    title: 'Company news',
                    slug: 'company-news',
                    excerpt: 'Monthly summary',
                    published_on: '2026-10-01',
                    body: null,
                },
            },
        });
        expect(payload).not.toHaveProperty('updated_at');
    });

    it('keeps the shared cover and sends the version on update', async () => {
        editorProps = makeEditor(
            {},
            {
                id: 9,
                updatedAt: '2026-09-01T10:00:00+00:00',
                coverMediaId: 12,
                translations: {
                    en: {
                        ...blankTranslation(),
                        title: 'Spring',
                        slug: 'spring',
                    },
                    de: blankTranslation(),
                },
            },
        );
        const container = await render(<AdminArticlesEdit />);

        const preview = container.querySelector<HTMLImageElement>(
            '[role="tabpanel"] img',
        );
        expect(preview?.getAttribute('src')).toContain(
            '/admin/media/12/preview',
        );
        expect(input(container, 'cover_media_id').value).toBe('12');

        await submit(container);

        const [route, payload] = submitMock.mock.lastCall!;
        expect(route.url).toBe('/admin/articles/9');
        expect(payload).toMatchObject({
            cover_media_id: 12,
            updated_at: '2026-09-01T10:00:00+00:00',
        });
    });

    it('shows a cover error inline instead of the general summary', async () => {
        editorProps = makeEditor();
        nextErrors = { cover_media_id: 'Choose a clean image.' };
        const container = await render(<AdminArticlesCreate />);

        await type(input(container, 'title'), 'Company news');
        await submit(container);

        const group = container.querySelector<HTMLElement>(
            '[role="tabpanel"] [role="group"]',
        )!;
        const describedBy = group.getAttribute('aria-describedby')!;
        expect(
            describedBy
                .split(' ')
                .map((id) => document.getElementById(id)?.textContent),
        ).toContain('Choose a clean image.');
        expect(
            Array.from(container.querySelectorAll('[role="alert"]')).filter(
                (alert) =>
                    alert.textContent === 'Choose a clean image.' &&
                    !group.contains(alert),
            ),
        ).toHaveLength(0);
    });
});
