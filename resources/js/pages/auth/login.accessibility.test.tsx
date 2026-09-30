import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import Login from './login';

vi.mock('@/components/passkey-verify', () => ({ default: () => null }));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    setLayoutProps: () => {},
    usePage: () => ({ props: {} }),
    router: { on: () => () => {} },
    Form: ({
        children,
    }: {
        children: (state: {
            processing: boolean;
            errors: Record<string, string>;
            clearErrors: () => void;
        }) => ReactNode;
    }) => (
        <form>
            {children({ processing: false, errors: {}, clearErrors: () => {} })}
        </form>
    ),
    Link: ({
        href,
        children,
    }: {
        href: string | { url: string };
        children: ReactNode;
    }) => <a href={typeof href === 'string' ? href : href.url}>{children}</a>,
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

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

/** Label text or `aria-label` — the names that win over a placeholder. */
function accessibleName(input: HTMLInputElement | null): string | undefined {
    return (
        input?.getAttribute('aria-label') ??
        Array.from(input?.labels ?? [])
            .map((label) => label.textContent)
            .join(' ')
    );
}

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('Login', () => {
    it('names the e-mail and password fields by their visible labels', async () => {
        const container = await render(<Login canResetPassword />);

        expect(
            accessibleName(container.querySelector('input[name="email"]')),
        ).toBe('auth.login.email');
        expect(
            accessibleName(container.querySelector('input[name="password"]')),
        ).toBe('auth.login.password');
    });
});
