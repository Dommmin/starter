import { AlertTriangle, Info } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Container } from './container';
import { Link } from './link';

type AnnouncementTone = App.Enums.AnnouncementTone;

export type AnnouncementBarProps = {
    /** Plain text, rendered escaped. */
    message: string;
    tone?: AnnouncementTone;
    /** Relative path (Inertia visit) or http(s) URL (opens in a new tab). */
    link?: { href: string; label: string };
    /** Translated name of the region landmark, e.g. "Announcement". */
    regionLabel: string;
    className?: never;
    style?: never;
};

const toneMap: Record<AnnouncementTone, { container: string; icon: string }> = {
    info: {
        container: 'bg-status-info-subtle border-status-info/30',
        icon: 'text-status-info',
    },
    warning: {
        container: 'bg-status-warning-subtle border-status-warning/30',
        icon: 'text-status-warning',
    },
};

const iconMap: Record<AnnouncementTone, typeof Info> = {
    info: Info,
    warning: AlertTriangle,
};

function isAbsolute(href: string): boolean {
    return /^https?:\/\//i.test(href);
}

/**
 * Full-width site announcement above the public header (e.g. "Closed
 * 24–26.12"). A named region landmark in the document flow: rendered by
 * SSR from the shared `site.announcement` prop, so it never shifts the
 * layout after hydration. Not a live region and not dismissible.
 */
export function AnnouncementBar({
    message,
    tone = 'info',
    link,
    regionLabel,
}: AnnouncementBarProps) {
    const IconComponent = iconMap[tone];

    return (
        <section
            aria-label={regionLabel}
            className={cn(
                'text-foreground border-b text-sm',
                toneMap[tone].container,
            )}
        >
            <Container>
                <p className="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 py-2 text-center">
                    <span className="inline-flex items-center gap-2">
                        <IconComponent
                            className={cn(
                                'size-4 shrink-0',
                                toneMap[tone].icon,
                            )}
                            aria-hidden="true"
                        />
                        <span>{message}</span>
                    </span>
                    {link && (
                        <Link href={link.href} external={isAbsolute(link.href)}>
                            {link.label}
                        </Link>
                    )}
                </p>
            </Container>
        </section>
    );
}
