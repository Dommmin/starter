import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { I18nProvider } from '@/i18n';
import { CookieConsent, useCookieConsent } from './cookie-consent';
import { Footer } from './footer';

vi.mock('@inertiajs/react', () => ({
    router: {
        on: () => () => {},
    },
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
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

const mountedRoots: Root[] = [];

const initialPage = {
    props: {
        i18n: {
            area: 'public',
            locale: 'en',
            defaultLocale: 'en',
            messages: {
                cookies: {
                    title: 'We respect your privacy',
                    description: 'Necessary cookies keep the site working.',
                    policy: 'Learn more',
                    acceptAll: 'Accept all',
                    rejectAll: 'Reject',
                    settings: 'Settings',
                    dialogTitle: 'Cookie settings',
                    dialogDescription: 'Choose which categories you allow.',
                    save: 'Save choice',
                    close: 'Close',
                    footerLink: 'Cookie settings',
                    categories: {
                        necessary: { label: 'Necessary', description: 'On.' },
                        analytics: {
                            label: 'Analytics',
                            description: 'Stats.',
                        },
                    },
                },
            },
            fallback: 'en',
            dir: 'ltr',
            availableLocales: [],
        },
    },
} as unknown as Page<PageProps & SharedPageProps>;

function consentData(
    overrides: Partial<App.Data.Consent.ConsentData> = {},
): App.Data.Consent.ConsentData {
    return {
        version: 3,
        cookieName: 'cookie_consent',
        lifetimeDays: 365,
        categories: ['analytics'],
        granted: null,
        policyUrl: '/privacy-policy',
        plausible: {
            domain: 'example.com',
            scriptUrl: 'https://plausible.io/js/script.js',
        },
        ...overrides,
    };
}

function Harness({ consent }: { consent: App.Data.Consent.ConsentData }) {
    const state = useCookieConsent(consent);

    return (
        <>
            <CookieConsent consent={consent} state={state} />
            <Footer
                copyright="© 2026 Acme"
                cookieSettings={
                    state.isActive
                        ? {
                              label: 'Cookie settings',
                              onOpen: state.openSettings,
                          }
                        : undefined
                }
            />
        </>
    );
}

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

async function settle() {
    // The settings dialog is loaded lazily; Radix moves focus in timeouts.
    await act(async () => {
        await new Promise((resolve) => setTimeout(resolve, 50));
    });
}

async function waitForDialog(): Promise<Element> {
    for (let attempt = 0; attempt < 40; attempt++) {
        const dialog = document.querySelector('[role="dialog"]');
        if (dialog) {
            await settle();
            return dialog;
        }
        await settle();
    }

    throw new Error('The settings dialog did not open');
}

function button(label: string): HTMLButtonElement {
    const match = Array.from(
        document.querySelectorAll<HTMLButtonElement>('button'),
    ).find((element) => element.textContent === label);

    if (!match) {
        throw new Error(`No button "${label}"`);
    }

    return match;
}

async function click(label: string) {
    await act(async () => {
        button(label).click();
    });
    await settle();
}

function storedChoice(): unknown {
    const raw = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith('cookie_consent='));

    return raw
        ? JSON.parse(decodeURIComponent(raw.split('=').slice(1).join('=')))
        : null;
}

beforeEach(() => {
    document.cookie = 'cookie_consent=; Max-Age=0; Path=/';
});

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
    document.getElementById('plausible-analytics')?.remove();
    vi.restoreAllMocks();
});

describe('CookieConsent', () => {
    it('shows a labelled banner with equal accept and reject choices and the policy link', async () => {
        await render(<Harness consent={consentData()} />);

        const region = document.querySelector('section[aria-labelledby]');
        const titleId = region?.getAttribute('aria-labelledby') ?? '';
        expect(document.getElementById(titleId)?.textContent).toBe(
            'We respect your privacy',
        );
        expect(
            Array.from(region?.querySelectorAll('button') ?? []).map(
                (element) => element.textContent,
            ),
        ).toEqual(['Settings', 'Reject', 'Accept all']);
        expect(button('Reject').className).toBe(button('Accept all').className);
        // Floating controls (back to top) keep clear of the banner by this marker.
        expect(region?.hasAttribute('data-bottom-bar')).toBe(true);
        expect(region?.querySelector('a')?.getAttribute('href')).toBe(
            '/privacy-policy',
        );
    });

    it('stores an acceptance and only then loads Plausible', async () => {
        await render(<Harness consent={consentData()} />);
        expect(document.getElementById('plausible-analytics')).toBeNull();

        await click('Accept all');

        expect(storedChoice()).toEqual({
            v: 3,
            o: ['analytics'],
            g: ['analytics'],
        });
        expect(document.querySelector('section[aria-labelledby]')).toBeNull();
        const script = document.getElementById(
            'plausible-analytics',
        ) as HTMLScriptElement | null;
        expect(script?.dataset.domain).toBe('example.com');
        expect(script?.src).toBe('https://plausible.io/js/script.js');
    });

    it('keeps the page reachable through layout alone, without styling the document', async () => {
        await render(<Harness consent={consentData()} />);

        expect(document.body.getAttribute('style')).toBeNull();
        expect(document.documentElement.getAttribute('style')).toBeNull();

        await click('Reject');

        expect(document.body.getAttribute('style')).toBeNull();
    });

    it('stores a rejection without loading any script', async () => {
        await render(<Harness consent={consentData()} />);

        await click('Reject');

        expect(storedChoice()).toEqual({ v: 3, o: ['analytics'], g: [] });
        expect(document.getElementById('plausible-analytics')).toBeNull();
    });

    it('offers a per-category choice with necessary cookies locked on and nothing pre-selected', async () => {
        await render(<Harness consent={consentData()} />);

        await click('Settings');

        const dialog = await waitForDialog();
        const switches = Array.from(
            dialog?.querySelectorAll<HTMLButtonElement>('[role="switch"]') ??
                [],
        );
        expect(
            switches.map((element) => element.getAttribute('aria-checked')),
        ).toEqual(['true', 'false']);
        expect(switches[0]?.disabled).toBe(true);

        await act(async () => {
            switches[1]?.click();
        });
        await click('Save choice');

        expect(storedChoice()).toEqual({
            v: 3,
            o: ['analytics'],
            g: ['analytics'],
        });
        expect(document.querySelector('[role="dialog"]')).toBeNull();
    });

    it('reopens the choice from the footer and reloads when a consent is withdrawn', async () => {
        const reload = vi.fn();
        vi.spyOn(window, 'location', 'get').mockReturnValue({
            protocol: 'http:',
            reload,
        } as unknown as Location);
        await render(
            <Harness consent={consentData({ granted: ['analytics'] })} />,
        );
        expect(document.querySelector('section[aria-labelledby]')).toBeNull();

        await click('Cookie settings');
        await waitForDialog();
        const analytics = document.querySelectorAll<HTMLButtonElement>(
            '[role="dialog"] [role="switch"]',
        )[1];
        expect(analytics?.getAttribute('aria-checked')).toBe('true');
        await act(async () => {
            analytics?.click();
        });
        await click('Save choice');

        expect(storedChoice()).toEqual({ v: 3, o: ['analytics'], g: [] });
        expect(reload).toHaveBeenCalledOnce();
    });

    it('renders no banner and no footer action when no optional category is offered', async () => {
        await render(<Harness consent={consentData({ categories: [] })} />);

        expect(document.querySelector('section[aria-labelledby]')).toBeNull();
        expect(
            Array.from(document.querySelectorAll('button')).map(
                (element) => element.textContent,
            ),
        ).toEqual([]);
    });
});
