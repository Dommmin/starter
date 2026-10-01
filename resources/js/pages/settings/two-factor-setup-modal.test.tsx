import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { I18nProvider } from '@/i18n';

vi.mock('@inertiajs/react', () => ({
    router: { on: () => () => {} },
    Form: ({
        children,
    }: {
        children: (state: { processing: boolean; errors: object }) => ReactNode;
    }) => <form>{children({ processing: false, errors: {} })}</form>,
}));

vi.mock('@/routes/two-factor', () => ({
    confirm: {
        form: () => ({
            action: '/user/confirmed-two-factor-authentication',
            method: 'post',
        }),
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

type ModalProps = Parameters<typeof TwoFactorSetupModal>[0];

function props(overrides: Partial<ModalProps> = {}): ModalProps {
    return {
        isOpen: true,
        onClose: vi.fn(),
        requiresConfirmation: true,
        twoFactorEnabled: false,
        qrCodeSvg: '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
        manualSetupKey: 'JBSWY3DPEHPK3PXP',
        clearSetupData: vi.fn(),
        fetchSetupData: vi.fn(async () => {}),
        errors: [],
        ...overrides,
    };
}

async function render(node: ReactNode) {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(
            <I18nProvider initialPage={initialPage}>{node}</I18nProvider>,
        );
    });
}

function button(label: string) {
    return Array.from(
        document.querySelectorAll<HTMLButtonElement>('[role="dialog"] button'),
    ).find((node) => node.textContent === label);
}

async function click(node: HTMLElement | undefined) {
    await act(async () => {
        node?.click();
    });
}

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
    vi.unstubAllGlobals();
});

describe('TwoFactorSetupModal', () => {
    it('shows the QR code and a read-only setup key that can be copied', async () => {
        const writeText = vi.fn(async () => {});
        vi.stubGlobal('navigator', { clipboard: { writeText } });
        await render(<TwoFactorSetupModal {...props()} />);

        const dialog = document.querySelector('[role="dialog"]');
        expect(dialog?.querySelector('img')?.getAttribute('alt')).toBe(
            'settings.twoFactor.qrCode',
        );
        const key = dialog?.querySelector<HTMLInputElement>(
            'input[name="setup-key"]',
        );
        expect(key?.value).toBe('JBSWY3DPEHPK3PXP');
        expect(key?.readOnly).toBe(true);

        await click(button('settings.twoFactor.copySetupKey'));

        expect(writeText).toHaveBeenCalledWith('JBSWY3DPEHPK3PXP');
        expect(button('settings.twoFactor.setupKeyCopied')).toBeDefined();
    });

    it('moves to the code step and back', async () => {
        await render(<TwoFactorSetupModal {...props()} />);

        await click(button('settings.twoFactor.continue'));

        expect(document.querySelector('input[name="code"]')).not.toBeNull();
        expect(document.querySelector('[role="dialog"] img')).toBeNull();

        await click(button('settings.twoFactor.back'));

        expect(document.querySelector('[role="dialog"] img')).not.toBeNull();
    });

    it('closes after the QR step when no confirmation is required', async () => {
        const modal = props({ requiresConfirmation: false });
        await render(<TwoFactorSetupModal {...modal} />);

        await click(button('settings.twoFactor.continue'));

        expect(modal.clearSetupData).toHaveBeenCalled();
        expect(modal.onClose).toHaveBeenCalled();
    });

    it('loads missing setup data and shows a labelled spinner meanwhile', async () => {
        const modal = props({ qrCodeSvg: null, manualSetupKey: null });
        await render(<TwoFactorSetupModal {...modal} />);

        expect(modal.fetchSetupData).toHaveBeenCalledTimes(1);
        expect(
            Array.from(document.querySelectorAll('[role="status"]')).map(
                (node) => node.textContent,
            ),
        ).toEqual([
            'settings.twoFactor.loadingSetup',
            'settings.twoFactor.loadingSetup',
        ]);
    });

    it('replaces the setup with an error and no continue action', async () => {
        await render(
            <TwoFactorSetupModal
                {...props({ errors: ['Fetch failed', 'Fetch failed'] })}
            />,
        );

        const dialog = document.querySelector('[role="dialog"]');
        expect(dialog?.textContent).toContain('settings.twoFactor.errorTitle');
        expect(dialog?.textContent).toContain('Fetch failed');
        expect(dialog?.querySelector('img')).toBeNull();
        expect(button('settings.twoFactor.continue')).toBeUndefined();
    });
});
