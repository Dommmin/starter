import { useEffect, useState } from 'react';
import { Button } from './button';
import { Dialog } from './dialog';
import { Link } from './link';

const STORAGE_KEY = 'announcement-popup-dismissed';
const DELAY_MS = 1500;

export type AnnouncementPopupProps = {
    /** Plain text, rendered escaped. */
    message: string;
    link?: { href: string; label: string };
    /** Translated title of the dialog. */
    title: string;
    closeLabel: string;
    /** Holds the popup back, e.g. while the cookie banner awaits an answer. */
    paused?: boolean;
    className?: never;
    style?: never;
};

/** Browsers may block storage; the popup then shows once per page load. */
function readDismissed(): string | null {
    try {
        return window.localStorage.getItem(STORAGE_KEY);
    } catch {
        return null;
    }
}

function writeDismissed(value: string): void {
    try {
        window.localStorage.setItem(STORAGE_KEY, value);
    } catch {
        // Nothing to do: the choice just is not remembered.
    }
}

/**
 * The site announcement as a dialog: opens shortly after the page loads,
 * closes with Escape or the close button and stays closed for this text and
 * link. A changed announcement is shown again.
 */
export function AnnouncementPopup({
    message,
    link,
    title,
    closeLabel,
    paused = false,
}: AnnouncementPopupProps) {
    const [isOpen, setIsOpen] = useState(false);
    const version = `${message}|${link?.href ?? ''}`;

    useEffect(() => {
        if (paused || readDismissed() === version) {
            return;
        }

        const timer = window.setTimeout(() => setIsOpen(true), DELAY_MS);

        return () => window.clearTimeout(timer);
    }, [paused, version]);

    const close = () => {
        writeDismissed(version);
        setIsOpen(false);
    };

    return (
        <Dialog
            open={isOpen}
            onOpenChange={(open) => {
                if (!open) {
                    close();
                }
            }}
            title={title}
            description={message}
            closeLabel={closeLabel}
            actions={
                <Button variant="outline" onClick={close}>
                    {closeLabel}
                </Button>
            }
        >
            {link && (
                <Link
                    href={link.href}
                    external={/^https?:\/\//i.test(link.href)}
                    tone="primary"
                >
                    {link.label}
                </Link>
            )}
        </Dialog>
    );
}
