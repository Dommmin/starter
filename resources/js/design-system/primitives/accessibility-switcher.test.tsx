import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import {
    ACCESSIBILITY_STORAGE_KEY,
    initializeAccessibilityPreferences,
    parseAccessibilityPreferences,
} from '@/hooks/use-accessibility-preferences';
import { I18nProvider } from '@/i18n';
import { prefersReducedMotion } from '@/lib/reduced-motion';
import { AccessibilitySwitcher } from './accessibility-switcher';

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: {} }),
    router: { on: () => () => {} },
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
            availableLocales: [
                { code: 'en', name: 'English', native: 'English', dir: 'ltr' },
            ],
        },
    },
} as unknown as Page<PageProps & SharedPageProps>;

const root = document.documentElement;
const mountedRoots: Root[] = [];

async function renderSwitcher(
    variant?: 'icon' | 'labelled',
): Promise<HTMLButtonElement> {
    const container = document.createElement('div');
    document.body.append(container);
    const reactRoot = createRoot(container);
    mountedRoots.push(reactRoot);

    await act(async () => {
        reactRoot.render(
            <I18nProvider initialPage={initialPage}>
                <AccessibilitySwitcher variant={variant} />
            </I18nProvider>,
        );
    });

    return container.querySelector('button') as HTMLButtonElement;
}

async function openDialog(trigger: HTMLButtonElement): Promise<HTMLElement> {
    await act(async () => {
        trigger.click();
    });
    await vi.waitFor(() =>
        expect(document.querySelector('[role="dialog"]')).not.toBeNull(),
    );

    return document.querySelector('[role="dialog"]') as HTMLElement;
}

function switchNamed(dialog: HTMLElement, name: string): HTMLButtonElement {
    const label = Array.from(dialog.querySelectorAll('label')).find(
        (element) => element.textContent === name,
    );
    expect(label).toBeDefined();

    return dialog.querySelector(
        `#${CSS.escape(label?.htmlFor ?? '')}`,
    ) as HTMLButtonElement;
}

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((reactRoot) => reactRoot.unmount());
    });
    document.body.replaceChildren();
    localStorage.clear();
    initializeAccessibilityPreferences();
});

describe('parseAccessibilityPreferences', () => {
    it('keeps known values and drops unknown or malformed ones', () => {
        expect(
            parseAccessibilityPreferences(
                JSON.stringify({
                    textSize: 'huge',
                    highContrast: 'yes',
                    reduceMotion: true,
                }),
            ),
        ).toEqual({
            textSize: 'default',
            highContrast: false,
            reduceMotion: true,
            underlineLinks: false,
        });
        expect(parseAccessibilityPreferences('{not json').textSize).toBe(
            'default',
        );
        expect(parseAccessibilityPreferences('null').highContrast).toBe(false);
    });
});

describe('initializeAccessibilityPreferences', () => {
    it('renders stored choices as attributes on <html>', () => {
        localStorage.setItem(
            ACCESSIBILITY_STORAGE_KEY,
            JSON.stringify({
                textSize: 'larger',
                highContrast: true,
                reduceMotion: true,
                underlineLinks: true,
            }),
        );

        initializeAccessibilityPreferences();

        expect(root.dataset.textSize).toBe('larger');
        expect(root.dataset.contrast).toBe('more');
        expect(root.dataset.motion).toBe('reduce');
        expect(root.dataset.links).toBe('underline');
        expect(prefersReducedMotion()).toBe(true);
    });

    it('renders no attribute for the defaults', () => {
        initializeAccessibilityPreferences();

        expect(root.hasAttribute('data-text-size')).toBe(false);
        expect(root.hasAttribute('data-contrast')).toBe(false);
        expect(root.hasAttribute('data-motion')).toBe(false);
        expect(root.hasAttribute('data-links')).toBe(false);
    });
});

describe('AccessibilitySwitcher', () => {
    it('is an icon button named by aria-label that announces a dialog', async () => {
        const trigger = await renderSwitcher();

        expect(trigger.getAttribute('aria-label')).toBe('accessibility.open');
        expect(trigger.getAttribute('aria-haspopup')).toBe('dialog');
        expect(trigger.textContent).toBe('');
    });

    it('is named by its visible caption in the labelled variant', async () => {
        const trigger = await renderSwitcher('labelled');

        expect(trigger.hasAttribute('aria-label')).toBe(false);
        expect(trigger.textContent).toBe('accessibility.open');
    });

    it('applies a choice at once, remembers it and restores the defaults', async () => {
        const dialog = await openDialog(await renderSwitcher());

        await act(async () => {
            switchNamed(dialog, 'accessibility.contrast.label').click();
        });

        expect(root.dataset.contrast).toBe('more');
        expect(
            JSON.parse(localStorage.getItem(ACCESSIBILITY_STORAGE_KEY) ?? '{}'),
        ).toMatchObject({ highContrast: true });

        const largeText = dialog.querySelector<HTMLButtonElement>(
            '[role="radio"][value="large"]',
        );
        await act(async () => {
            largeText?.click();
        });

        expect(root.dataset.textSize).toBe('large');

        const reset = Array.from(dialog.querySelectorAll('button')).find(
            (button) => button.textContent === 'accessibility.reset',
        );
        await act(async () => {
            reset?.click();
        });

        expect(root.hasAttribute('data-contrast')).toBe(false);
        expect(root.hasAttribute('data-text-size')).toBe(false);
        expect(localStorage.getItem(ACCESSIBILITY_STORAGE_KEY)).toBeNull();
    });
});
