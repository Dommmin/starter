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

    useEffect(() => {
        if (plausible && granted?.includes('analytics')) {
            loadPlausible(plausible);
        }
    }, [plausible, granted]);

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
