import { lazy, Suspense, useCallback, useEffect, useId, useState } from 'react';
import { useTranslation } from '@/i18n';
import { Button } from './button';
import { Link } from './link';

type ConsentCategory = App.Enums.ConsentCategory;
type ConsentData = App.Data.Consent.ConsentData;

const CookieSettingsDialog = lazy(() =>
    import('./cookie-settings-dialog').then((module) => ({
        default: module.CookieSettingsDialog,
    })),
);

const PLAUSIBLE_SCRIPT_ID = 'plausible-analytics';
const GA4_SCRIPT_ID = 'ga4-analytics';
const GTM_SCRIPT_ID = 'gtm-analytics';
const META_PIXEL_SCRIPT_ID = 'meta-pixel-script';
const CHAT_WIDGET_SCRIPT_ID = 'live-chat-widget';

declare global {
    interface Window {
        dataLayer?: unknown[];
        gtag?: (...args: unknown[]) => void;
        fbq?: ((...args: unknown[]) => void) & {
            callMethod?: (...args: unknown[]) => void;
            queue?: unknown[];
            loaded?: boolean;
            version?: string;
            push?: (...args: unknown[]) => void;
        };
        _fbq?: unknown;
        $crisp?: unknown[];
        CRISP_WEBSITE_ID?: string;
        Tawk_API?: Record<string, unknown>;
        Tawk_LoadStart?: Date;
    }
}

/**
 * Store the choice in the format `CookieConsent` (PHP) reads: version,
 * offered and granted categories, so a category offered later asks again.
 */
function writeConsentCookie(
    consent: ConsentData,
    granted: ConsentCategory[],
): void {
    const value = encodeURIComponent(
        JSON.stringify({
            v: consent.version,
            o: consent.categories,
            g: granted,
        }),
    );
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';

    document.cookie = `${consent.cookieName}=${value}; Max-Age=${consent.lifetimeDays * 86400}; Path=/; SameSite=Lax${secure}`;
}

function loadPlausible(plausible: App.Data.Consent.PlausibleData): void {
    if (document.getElementById(PLAUSIBLE_SCRIPT_ID)) {
        return;
    }

    const script = document.createElement('script');
    script.id = PLAUSIBLE_SCRIPT_ID;
    script.defer = true;
    script.dataset.domain = plausible.domain;
    script.src = plausible.scriptUrl;
    document.head.append(script);
}

function loadGa4(measurementId: string): void {
    if (document.getElementById(GA4_SCRIPT_ID)) {
        return;
    }

    window.dataLayer = window.dataLayer ?? [];
    window.gtag = function gtag() {
        // gtag.js runs only `arguments` objects; a plain array is ignored.
        // eslint-disable-next-line prefer-rest-params
        window.dataLayer?.push(arguments);
    };
    window.gtag('js', new Date());
    window.gtag('config', measurementId);

    const script = document.createElement('script');
    script.id = GA4_SCRIPT_ID;
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`;
    document.head.append(script);
}

function loadGtm(containerId: string): void {
    if (document.getElementById(GTM_SCRIPT_ID)) {
        return;
    }

    window.dataLayer = window.dataLayer ?? [];
    window.dataLayer.push({
        'gtm.start': Date.now(),
        event: 'gtm.js',
    });

    const script = document.createElement('script');
    script.id = GTM_SCRIPT_ID;
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(containerId)}`;
    document.head.append(script);
}

function loadMetaPixel(pixelId: string): void {
    if (document.getElementById(META_PIXEL_SCRIPT_ID)) {
        return;
    }

    const fbq = function (...args: unknown[]) {
        if (fbq.callMethod) {
            fbq.callMethod(...args);
        } else {
            fbq.queue?.push(args);
        }
    } as NonNullable<Window['fbq']>;
    fbq.queue = [];
    fbq.loaded = true;
    fbq.version = '2.0';
    fbq.push = fbq;
    window.fbq = fbq;
    window._fbq = fbq;

    fbq('init', pixelId);
    fbq('track', 'PageView');

    const script = document.createElement('script');
    script.id = META_PIXEL_SCRIPT_ID;
    script.async = true;
    script.src = 'https://connect.facebook.net/en_US/fbevents.js';
    document.head.append(script);
}

function loadChatWidget(provider: string, widgetId: string): void {
    if (document.getElementById(CHAT_WIDGET_SCRIPT_ID)) {
        return;
    }

    if (provider === 'crisp') {
        window.$crisp = window.$crisp ?? [];
        window.CRISP_WEBSITE_ID = widgetId;
        const script = document.createElement('script');
        script.id = CHAT_WIDGET_SCRIPT_ID;
        script.async = true;
        script.src = 'https://client.crisp.chat/l.js';
        document.head.append(script);
        return;
    }

    if (provider === 'tawk') {
        window.Tawk_API = window.Tawk_API ?? {};
        window.Tawk_LoadStart = new Date();
        const script = document.createElement('script');
        script.id = CHAT_WIDGET_SCRIPT_ID;
        script.async = true;
        script.src = `https://embed.tawk.to/${widgetId}`;
        document.head.append(script);
    }
}

export type CookieConsentState = {
    /** False when the site offers no optional category: no banner, no settings. */
    isActive: boolean;
    /** Allowed categories; null until the visitor decides. */
    granted: ConsentCategory[] | null;
    settingsOpen: boolean;
    openSettings: () => void;
    setSettingsOpen: (open: boolean) => void;
    save: (granted: ConsentCategory[]) => void;
};

/**
 * Consent state of public pages from the shared `consent` prop: stores a new
 * choice in the browser cookie, loads the allowed scripts (Plausible after
 * the analytics consent) and reloads the page when a granted category is
 * withdrawn, so its script stops.
 */
export function useCookieConsent(
    consent: ConsentData | undefined,
): CookieConsentState {
    const [granted, setGranted] = useState<ConsentCategory[] | null>(
        consent?.granted ?? null,
    );
    const [settingsOpen, setSettingsOpen] = useState(false);
    const plausible = consent?.plausible ?? null;
    const integrations = consent?.integrations ?? null;

    useEffect(() => {
        if (granted?.includes('analytics')) {
            if (plausible) {
                loadPlausible(plausible);
            }
            if (integrations?.ga4Id) {
                loadGa4(integrations.ga4Id);
            }
            if (integrations?.gtmId) {
                loadGtm(integrations.gtmId);
            }
        }

        if (granted?.includes('marketing')) {
            if (integrations?.metaPixelId) {
                loadMetaPixel(integrations.metaPixelId);
            }
            // Chat vendors set their own cookies, so the widget needs consent.
            if (integrations?.chatProvider && integrations.chatWidgetId) {
                loadChatWidget(
                    integrations.chatProvider,
                    integrations.chatWidgetId,
                );
            }
        }
    }, [plausible, integrations, granted]);

    const save = useCallback(
        (next: ConsentCategory[]) => {
            if (!consent) {
                return;
            }

            const withdrawn = (granted ?? []).some(
                (category) => !next.includes(category),
            );
            writeConsentCookie(consent, next);
            setGranted(next);
            setSettingsOpen(false);

            if (withdrawn) {
                window.location.reload();
            }
        },
        [consent, granted],
    );

    return {
        isActive: (consent?.categories.length ?? 0) > 0,
        granted,
        settingsOpen,
        openSettings: () => setSettingsOpen(true),
        setSettingsOpen,
        save,
    };
}

export type CookieConsentBannerProps = {
    title: string;
    description: string;
    /** Link to the privacy or cookie policy page. */
    policyLink?: { label: string; href: string };
    acceptLabel: string;
    rejectLabel: string;
    settingsLabel: string;
    onAccept: () => void;
    onReject: () => void;
    onSettings: () => void;
    className?: never;
    style?: never;
};

/**
 * Non-modal consent bar stuck to the bottom of the viewport. Accepting and
 * rejecting carry the same weight (no nudging); the settings open the
 * per-category choice.
 *
 * Render it as a direct child of a full-height flex column (`PublicChrome`)
 * and early in the DOM, right after the header, so keyboard and screen
 * reader users reach it before the page content. `order-last` moves it
 * below the footer in the layout and `sticky` keeps it on screen, so at the
 * end of the page it takes its own space and covers nothing, without
 * measuring it or styling the document.
 */
export function CookieConsentBanner({
    title,
    description,
    policyLink,
    acceptLabel,
    rejectLabel,
    settingsLabel,
    onAccept,
    onReject,
    onSettings,
}: CookieConsentBannerProps) {
    const titleId = useId();
    return (
        <section
            aria-labelledby={titleId}
            data-bottom-bar
            className="sticky bottom-0 z-40 order-last p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:p-4"
        >
            <div className="bg-background border-border-subtle mx-auto flex max-w-5xl flex-col gap-4 rounded-lg border p-4 shadow-lg sm:p-5 lg:flex-row lg:items-center lg:gap-8">
                <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                    <h2
                        id={titleId}
                        className="text-foreground text-base font-semibold"
                    >
                        {title}
                    </h2>
                    <p className="text-muted-foreground text-sm leading-relaxed">
                        {description}
                        {policyLink && (
                            <>
                                {' '}
                                <Link href={policyLink.href} tone="primary">
                                    {policyLink.label}
                                </Link>
                            </>
                        )}
                    </p>
                </div>
                <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:justify-end lg:shrink-0 lg:flex-nowrap">
                    <div className="col-span-2 grid sm:contents">
                        <Button variant="outline" onClick={onSettings}>
                            {settingsLabel}
                        </Button>
                    </div>
                    <Button onClick={onReject}>{rejectLabel}</Button>
                    <Button onClick={onAccept}>{acceptLabel}</Button>
                </div>
            </div>
        </section>
    );
}

export type CookieConsentProps = {
    consent: ConsentData;
    state: CookieConsentState;
};

/**
 * Banner and settings dialog of public pages, translated from the common
 * catalog (`cookies.*`). Renders nothing when no optional category is
 * offered. The dialog code loads on first opening.
 */
export function CookieConsent({ consent, state }: CookieConsentProps) {
    const { t } = useTranslation();

    if (!state.isActive) {
        return null;
    }

    return (
        <>
            {state.granted === null && (
                <CookieConsentBanner
                    title={t('cookies.title')}
                    description={t('cookies.description')}
                    policyLink={
                        consent.policyUrl
                            ? {
                                  label: t('cookies.policy'),
                                  href: consent.policyUrl,
                              }
                            : undefined
                    }
                    acceptLabel={t('cookies.acceptAll')}
                    rejectLabel={t('cookies.rejectAll')}
                    settingsLabel={t('cookies.settings')}
                    onAccept={() => state.save(consent.categories)}
                    onReject={() => state.save([])}
                    onSettings={state.openSettings}
                />
            )}
            {state.settingsOpen && (
                <Suspense fallback={null}>
                    <CookieSettingsDialog
                        open={state.settingsOpen}
                        onOpenChange={state.setSettingsOpen}
                        categories={consent.categories}
                        granted={state.granted ?? []}
                        onSave={state.save}
                    />
                </Suspense>
            )}
        </>
    );
}
