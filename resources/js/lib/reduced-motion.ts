/**
 * Whether animations should be skipped: the operating system asks for
 * reduced motion, or the visitor turned it on in the accessibility
 * preferences (`data-motion="reduce"` on <html>).
 */
export function prefersReducedMotion(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return (
        document.documentElement.dataset.motion === 'reduce' ||
        window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true
    );
}
