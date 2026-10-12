import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import PasskeyItem from '@/components/passkey-item';
import { I18nProvider } from '@/i18n';
import Security from './security';

vi.mock('@/components/manage-passkeys', () => ({ default: () => null }));
vi.mock('@/components/manage-two-factor', () => ({ default: () => null }));

const serverErrors: Record<string, string> = {};

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: {} }),
    router: { on: () => () => {} },
    useForm: <Data extends Record<string, unknown>>(data: Data) => ({
        data,
        errors: serverErrors,
        processing: false,
        setData: () => {},
        reset: () => {},
        submit: (
            _target: unknown,
            options: { onError?: (errors: Record<string, string>) => void },
        ) => options.onError?.(serverErrors),
    }),
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

afterEach(async () => {
    Object.keys(serverErrors).forEach((key) => delete serverErrors[key]);
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('Security', () => {
    it('shows each password error at its field and focuses the first invalid one', async () => {
        Object.assign(serverErrors, {
            current_password: 'Wrong password.',
            password: 'Too short.',
        });
        const container = await render(
            <Security passwordRules="minlength: 12;" />,
        );

        await act(async () => {
            container
                .querySelector('form')
                ?.dispatchEvent(
                    new Event('submit', { bubbles: true, cancelable: true }),
                );
        });

        const current = container.querySelector<HTMLInputElement>(
            'input[name="current_password"]',
        );
        expect(current?.getAttribute('aria-invalid')).toBe('true');
        expect(container.textContent).toContain('Wrong password.');
        expect(container.textContent).toContain('Too short.');
        expect(document.activeElement).toBe(current);
        expect(
            container
                .querySelector('input[name="password_confirmation"]')
                ?.getAttribute('aria-invalid'),
        ).not.toBe('true');
    });
});

describe('PasskeyItem', () => {
    const passkey = {
        id: 7,
        name: 'Work laptop',
        authenticator: 'Touch ID',
        created_at_diff: '1 day ago',
        last_used_at_diff: null,
    };

    it('asks for confirmation before removing a passkey', async () => {
        const onDelete = vi.fn();
        const container = await render(
            <PasskeyItem passkey={passkey} onDelete={onDelete} />,
        );

        await act(async () => {
            container
                .querySelector<HTMLButtonElement>(
                    'button[aria-label="auth.passkey.remove"]',
                )
                ?.click();
        });

        expect(onDelete).not.toHaveBeenCalled();

        const confirm = Array.from(
            document.body.querySelectorAll('button'),
        ).find((button) => button.textContent === 'auth.passkey.removeSubmit');
        await act(async () => {
            confirm?.click();
        });

        expect(onDelete).toHaveBeenCalledWith(7, expect.any(Function));
    });
});
