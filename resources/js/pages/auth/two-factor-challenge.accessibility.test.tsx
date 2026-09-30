import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import TwoFactorChallenge from './two-factor-challenge';

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

/** Visible label text associated through `for`/`id`. */
function labelText(input: HTMLInputElement | null): string {
    return Array.from(input?.labels ?? [])
        .map((label) => label.textContent)
        .join(' ');
}

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('TwoFactorChallenge', () => {
    it('names the authentication code and recovery code fields by visible labels', async () => {
        const container = await render(<TwoFactorChallenge />);

        expect(labelText(container.querySelector('input[name="code"]'))).toBe(
            'auth.twoFactor.code',
        );

        const toggle = Array.from(container.querySelectorAll('button')).find(
            (button) => button.type === 'button',
        );

        await act(async () => {
            toggle?.click();
        });

        expect(container.querySelector('input[name="code"]')).toBeNull();
        expect(
            labelText(container.querySelector('input[name="recovery_code"]')),
        ).toBe('auth.twoFactor.recoveryCode');
    });
});
