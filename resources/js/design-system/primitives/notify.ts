import { toast } from 'sonner';

export type NotifyTone = 'success' | 'danger' | 'neutral';

export type NotifyOptions = {
    tone: NotifyTone;
    message: string;
    description?: string;
};

/**
 * Shows a transient toast in the app's `Toaster` (announced through its
 * `aria-live="polite"` region). The only public way to raise a toast —
 * screens never import `sonner` directly. A toast never replaces a message
 * next to the field it concerns.
 */
export function notify({ tone, message, description }: NotifyOptions): void {
    const options = description === undefined ? undefined : { description };

    if (tone === 'success') {
        toast.success(message, options);

        return;
    }

    if (tone === 'danger') {
        toast.error(message, options);

        return;
    }

    toast(message, options);
}
