import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { notify, type NotifyTone } from '@/design-system/primitives';
import type { FlashToast } from '@/types/ui';

/** Backend flash types without a DS tone (info, warning) show as neutral. */
const toneByFlashType: Record<FlashToast['type'], NotifyTone> = {
    success: 'success',
    error: 'danger',
    info: 'neutral',
    warning: 'neutral',
};

export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!data) {
                return;
            }

            notify({ tone: toneByFlashType[data.type], message: data.message });
        });
    }, []);
}
