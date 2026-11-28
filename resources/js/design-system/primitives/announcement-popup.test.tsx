import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { AnnouncementPopup } from './announcement-popup';

let root: Root;
let container: HTMLDivElement;

function render(message: string, paused = false) {
    act(() => {
        root.render(
            <AnnouncementPopup
                message={message}
                title="News"
                closeLabel="Close"
                paused={paused}
            />,
        );
    });
}

function wait() {
    act(() => {
        vi.advanceTimersByTime(2000);
    });
}

const dialog = () => document.body.querySelector('[role="dialog"]');

describe('AnnouncementPopup', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        window.localStorage.clear();
        container = document.body.appendChild(document.createElement('div'));
        root = createRoot(container);
    });

    afterEach(() => {
        act(() => root.unmount());
        container.remove();
        vi.useRealTimers();
    });

    it('waits while paused and opens afterwards', () => {
        render('Closed on Friday', true);
        wait();
        expect(dialog()).toBeNull();

        render('Closed on Friday');
        wait();
        expect(dialog()?.textContent).toContain('Closed on Friday');
    });

    it('stays closed for a dismissed text and returns for a new one', () => {
        render('Closed on Friday');
        wait();
        act(() => {
            const close = [...document.body.querySelectorAll('button')].find(
                (button) => button.textContent === 'Close',
            );
            close?.click();
        });

        act(() => root.unmount());
        root = createRoot(container);
        render('Closed on Friday');
        wait();
        expect(dialog()).toBeNull();

        render('Open again');
        wait();
        expect(dialog()?.textContent).toContain('Open again');
    });
});
