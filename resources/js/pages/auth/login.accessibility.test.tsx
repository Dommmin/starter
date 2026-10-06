import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import Login from './login';

vi.mock('@/components/passkey-verify', () => ({ default: () => null }));

/** Validation errors the mocked server returns after a submit. */
const serverErrors: Record<string, string> = {};

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
    useForm: <Data extends Record<string, unknown>>(data: Data) => ({
        data,
        errors: serverErrors,
        processing: false,
        setData: () => {},
        reset: () => {},
        submit: (
            _method: string,
            _url: string,
            options: { onError?: (errors: Record<string, string>) => void },
        ) => options.onError?.(serverErrors),
    }),
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
            .map(visibleLabelText)
            .join(' ')
    );
}

/** Label text as exposed to assistive tech: `aria-hidden` parts (the required asterisk) are not part of the name. */
function visibleLabelText(label: HTMLLabelElement): string {
    const clone = label.cloneNode(true) as HTMLElement;
    clone
        .querySelectorAll('[aria-hidden="true"]')
        .forEach((node) => node.remove());

    return clone.textContent ?? '';
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
        expect(
            container.querySelector<HTMLInputElement>('input[name="email"]')
                ?.required,
        ).toBe(true);
        expect(
            container.querySelector<HTMLInputElement>('input[name="password"]')
                ?.required,
        ).toBe(true);
    });

    it('moves focus to the first invalid field after a rejected submit', async () => {
        Object.assign(serverErrors, { password: 'Required.', email: 'Bad.' });
        const container = await render(<Login canResetPassword />);

        await act(async () => {
            container
                .querySelector('form')
                ?.dispatchEvent(
                    new Event('submit', { bubbles: true, cancelable: true }),
                );
        });

        expect(document.activeElement?.id).toBe('email');
        expect(
            container
                .querySelector('input[name="email"]')
                ?.getAttribute('aria-invalid'),
        ).toBe('true');

        for (const key of Object.keys(serverErrors)) {
            delete serverErrors[key];
        }
    });

    it('lets keyboard users reveal the password with a translated control', async () => {
        const container = await render(<Login canResetPassword />);
        const toggle = container.querySelector<HTMLButtonElement>(
            'button[aria-pressed]',
        );

        expect(toggle?.getAttribute('aria-label')).toBe(
            'auth.passwordField.show',
        );
        expect(toggle?.tabIndex).toBe(0);
        expect(
            container.querySelector(
                '[tabindex]:not([tabindex="0"]):not([tabindex="-1"])',
            ),
        ).toBeNull();
    });

    it('renders its own h1 so the heading is part of the server-rendered HTML', async () => {
        const container = await render(<Login canResetPassword />);

        expect(container.querySelector('h1')?.textContent).toBe(
            'auth.login.heading',
        );
    });
});
