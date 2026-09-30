import { Alert } from './alert';

export type OfflineBannerProps = {
    /** Translated message, e.g. "You are offline. Some actions are unavailable." */
    message: string;
};

/**
 * Stays in the document flow (no sticky/z-index layer): the admin topbar and
 * the public header are already sticky, and a second sticky layer at
 * `top-0` covered them. Render it at the top of the page content; the
 * `Alert` `role="status"` announces it without moving focus.
 */
export function OfflineBanner({ message }: OfflineBannerProps) {
    return <Alert tone="neutral" title={message} />;
}
