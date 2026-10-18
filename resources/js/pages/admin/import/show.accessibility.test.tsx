import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import AdminImportShow from './show';

let props: Record<string, unknown> = {};

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props }),
    useForm: () => ({ errors: {}, processing: false, post: vi.fn() }),
    router: { post: vi.fn(), on: () => () => {} },
    Head: ({ children }: { children?: ReactNode }) => <>{children}</>,
    Link: ({ children }: { children: ReactNode }) => <a>{children}</a>,
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

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

const screen = {
    module: 'faqs',
    columns: ['question', 'answer', 'locale'],
    requiredColumns: ['question', 'answer'],
    maxRows: 1000,
    maxKilobytes: 2048,
};

const mountedRoots: Root[] = [];

async function render(): Promise<HTMLElement> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(
            <I18nProvider initialPage={initialPage}>
                <AdminImportShow />
            </I18nProvider>,
        );
    });

    return container;
}

function submitButtonText(container: HTMLElement): string[] {
    return Array.from(container.querySelectorAll('button')).map(
        (button) => button.textContent ?? '',
    );
}

afterEach(() => {
    for (const root of mountedRoots.splice(0)) {
        act(() => root.unmount());
    }
    document.body.innerHTML = '';
});

describe('AdminImportShow', () => {
    it('lists the columns and offers only the upload before a check', async () => {
        props = { ...screen, preview: null };

        const container = await render();

        expect(container.textContent).toContain('question');
        expect(container.querySelector('input[type="file"]')).not.toBeNull();
        expect(submitButtonText(container)).not.toContain(
            'admin.import.submit',
        );
    });

    it('shows row errors and no import button for an invalid file', async () => {
        props = {
            ...screen,
            preview: {
                fileName: 'faq.csv',
                fileErrors: [],
                rows: [
                    {
                        line: 3,
                        label: 'Missing answer?',
                        errors: ['The answer field is required.'],
                    },
                ],
                total: 2,
                invalid: 1,
                token: null,
            },
        };

        const container = await render();

        expect(container.textContent).toContain(
            'The answer field is required.',
        );
        expect(submitButtonText(container)).not.toContain(
            'admin.import.submit',
        );
    });

    it('offers the import for a clean file', async () => {
        props = {
            ...screen,
            preview: {
                fileName: 'faq.csv',
                fileErrors: [],
                rows: [{ line: 2, label: 'Valid?', errors: [] }],
                total: 1,
                invalid: 0,
                token: 'a'.repeat(40),
            },
        };

        const container = await render();

        expect(submitButtonText(container)).toContain('admin.import.submit');
    });
});
