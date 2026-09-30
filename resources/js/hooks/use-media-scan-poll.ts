import { usePoll } from '@inertiajs/react';
import { useEffect } from 'react';

const SCAN_POLL_INTERVAL_MS = 2000;

/**
 * Reloads the given props while any asset is still in quarantine. The virus
 * scan runs on the queue after upload, so the page otherwise keeps showing
 * the quarantine status it was rendered with.
 */
export function useMediaScanPoll(isScanPending: boolean, only: string[]) {
    const { start, stop } = usePoll(
        SCAN_POLL_INTERVAL_MS,
        { only },
        { autoStart: false },
    );

    useEffect(() => {
        if (isScanPending) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [isScanPending, start, stop]);
}
