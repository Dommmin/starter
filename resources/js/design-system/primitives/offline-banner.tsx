import { Alert } from './alert';

export type OfflineBannerProps = {
    /** Translated message, e.g. "You are offline. Some actions are unavailable." */
    message: string;
};

export function OfflineBanner({ message }: OfflineBannerProps) {
    return (
        <div className="sticky top-0 z-40">
            <Alert tone="neutral" title={message} />
        </div>
    );
}
