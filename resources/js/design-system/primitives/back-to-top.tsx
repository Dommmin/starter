import { ArrowUp } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from '@/i18n';
import { cn } from '@/lib/utils';
import { IconButton } from './icon-button';

export type BackToTopProps = {
    /** Element focused after scrolling up; the page's main landmark by default. */
    targetId?: string;
    className?: never;
    style?: never;
};

/** Scroll distance, in viewport heights, after which the button appears. */
const THRESHOLD = 1.5;

/**
 * Height of the viewport bottom covered by a bar marked `data-bottom-bar`
 * (the cookie banner), so the button can rise above it.
 */
function bottomBarOverlap(): number {
    const bar = document.querySelector<HTMLElement>('[data-bottom-bar]');

    if (!bar) {
        return 0;
    }

    return Math.max(0, window.innerHeight - bar.getBoundingClientRect().top);
}

/**
 * Floating "back to top" button in the bottom right corner. It appears
 * after the visitor scrolled well down the page, scrolls back up (smoothly
 * unless reduced motion is preferred) and moves focus to the start of the
 * content so keyboard and screen reader users continue from the top. While
 * hidden it is inert, so it is never reached with Tab. It rises above a
 * bottom bar (`data-bottom-bar`, e.g. the cookie banner) instead of hiding
 * under it.
 */
export function BackToTop({ targetId = 'main-content' }: BackToTopProps) {
    const { t } = useTranslation();
    const [isVisible, setIsVisible] = useState(false);
    const [barOverlap, setBarOverlap] = useState(0);

    useEffect(() => {
        let frame = 0;

        const update = () => {
            frame = 0;
            setIsVisible(window.scrollY > window.innerHeight * THRESHOLD);
            setBarOverlap(bottomBarOverlap());
        };
        const onScroll = () => {
            if (frame === 0) {
                frame = window.requestAnimationFrame(update);
            }
        };

        update();
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);
        // The page height changes when the bar appears, resizes or closes.
        const observer =
            typeof ResizeObserver === 'undefined'
                ? null
                : new ResizeObserver(onScroll);
        observer?.observe(document.body);

        return () => {
            window.removeEventListener('scroll', onScroll);
            window.removeEventListener('resize', onScroll);
            observer?.disconnect();
            window.cancelAnimationFrame(frame);
        };
    }, []);

    function scrollToTop() {
        const reducedMotion = window.matchMedia?.(
            '(prefers-reduced-motion: reduce)',
        ).matches;
        window.scrollTo({
            top: 0,
            behavior: reducedMotion ? 'auto' : 'smooth',
        });

        const target = document.getElementById(targetId);
        if (target) {
            if (!target.hasAttribute('tabindex')) {
                target.setAttribute('tabindex', '-1');
            }
            target.focus({ preventScroll: true });
        }
    }

    return (
        <div
            inert={!isVisible}
            style={
                barOverlap > 0
                    ? {
                          bottom: `calc(max(1rem, env(safe-area-inset-bottom)) + ${barOverlap}px)`,
                      }
                    : undefined
            }
            className={cn(
                'fixed right-4 bottom-[max(1rem,env(safe-area-inset-bottom))] z-40 rounded-(--control-radius) shadow-lg sm:right-6',
                'motion-safe:transition-[opacity,translate] motion-safe:duration-300',
                isVisible
                    ? 'translate-y-0 opacity-100'
                    : 'pointer-events-none translate-y-2 opacity-0',
            )}
        >
            <IconButton
                icon={ArrowUp}
                variant="primary"
                ariaLabel={t('a11y.backToTop')}
                onClick={scrollToTop}
            />
        </div>
    );
}
