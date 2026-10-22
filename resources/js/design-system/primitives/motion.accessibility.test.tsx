import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { I18nProvider } from '@/i18n';
import { BackToTop } from './back-to-top';
import { isBentoWide } from './grid';
import { NumberedList } from './numbered-list';
import { Hero } from './hero';
import { ProjectTile } from './project-tile';
import { Reveal } from './reveal';
import { StatList } from './stat-list';
import { Surface } from './surface';

vi.mock('@inertiajs/react', () => ({
    Link: ({ children }: { children: ReactNode }) => <a>{children}</a>,
    router: { on: () => () => {} },
}));

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

const initialPage = {
    props: {
        i18n: {
            locale: 'en',
            fallback: 'en',
            dir: 'ltr',
            messages: { a11y: { backToTop: 'Back to top' } },
        },
    },
} as unknown as Page<PageProps & SharedPageProps>;

const mountedRoots: Root[] = [];

async function render(node: ReactNode): Promise<HTMLElement> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(
            <I18nProvider initialPage={initialPage}>{node}</I18nProvider>,
        );
    });

    return container;
}

type ObserverCallback = (entries: { isIntersecting: boolean }[]) => void;
let observerCallbacks: ObserverCallback[] = [];

function installObserver() {
    observerCallbacks = [];
    vi.stubGlobal(
        'IntersectionObserver',
        class {
            constructor(callback: ObserverCallback) {
                observerCallbacks.push(callback);
            }
            observe() {}
            disconnect() {}
        },
    );
}

function setReducedMotion(reduce: boolean) {
    vi.stubGlobal('matchMedia', (query: string) => ({
        matches: reduce && query.includes('reduce'),
    }));
}

function placeBelowFold(top: number) {
    vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockReturnValue({
        top,
    } as DOMRect);
}

beforeEach(() => {
    setReducedMotion(false);
});

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    vi.useRealTimers();
});

describe('Reveal', () => {
    it('keeps content visible without IntersectionObserver (server HTML, no JS)', async () => {
        vi.stubGlobal('IntersectionObserver', undefined);
        const container = await render(
            <Reveal id="portfolio">
                <p>Projects</p>
            </Reveal>,
        );

        const block = container.querySelector('#portfolio');
        expect(block?.getAttribute('data-reveal')).toBe('static');
        expect(block?.className).not.toContain('opacity-0');
        expect(block?.textContent).toBe('Projects');
    });

    it('hides a block below the fold until it is reached, then settles', async () => {
        vi.useFakeTimers();
        installObserver();
        placeBelowFold(window.innerHeight + 200);
        const container = await render(
            <Reveal id="team">
                <p>People</p>
            </Reveal>,
        );
        const block = container.querySelector('#team');

        expect(block?.getAttribute('data-reveal')).toBe('hidden');
        expect(block?.className).toContain('opacity-0');

        await act(async () => {
            observerCallbacks[0]([{ isIntersecting: true }]);
        });
        expect(block?.getAttribute('data-reveal')).toBe('shown');
        expect(block?.className).not.toContain('opacity-0');

        await act(async () => {
            vi.advanceTimersByTime(1200);
        });
        expect(block?.getAttribute('data-reveal')).toBe('static');
        expect(block?.className).toBe('');
    });

    it('never animates a block on the first screen or with reduced motion', async () => {
        installObserver();
        placeBelowFold(10);
        const onScreen = await render(<Reveal id="first">First</Reveal>);
        expect(
            onScreen.querySelector('#first')?.getAttribute('data-reveal'),
        ).toBe('static');

        setReducedMotion(true);
        placeBelowFold(window.innerHeight + 200);
        const reduced = await render(<Reveal id="calm">Calm</Reveal>);
        expect(
            reduced.querySelector('#calm')?.getAttribute('data-reveal'),
        ).toBe('static');
        expect(observerCallbacks).toHaveLength(0);
    });
});

describe('BackToTop', () => {
    async function scrollTo(y: number) {
        Object.defineProperty(window, 'scrollY', {
            value: y,
            configurable: true,
        });
        await act(async () => {
            window.dispatchEvent(new Event('scroll'));
            await new Promise((resolve) => requestAnimationFrame(resolve));
        });
    }

    it('stays inert near the top and appears after scrolling far down', async () => {
        await scrollTo(0);
        const container = await render(<BackToTop />);
        const wrapper = container.firstElementChild as HTMLElement;
        const button = container.querySelector('button');

        expect(button?.getAttribute('aria-label')).toBe('Back to top');
        expect(wrapper.hasAttribute('inert')).toBe(true);

        await scrollTo(window.innerHeight * 2);
        expect(wrapper.hasAttribute('inert')).toBe(false);
    });

    it('rises above a bottom bar covering the viewport bottom', async () => {
        const bar = document.createElement('section');
        bar.setAttribute('data-bottom-bar', '');
        vi.spyOn(bar, 'getBoundingClientRect').mockReturnValue({
            top: window.innerHeight - 200,
        } as DOMRect);
        document.body.append(bar);
        const container = await render(<BackToTop />);
        const wrapper = container.firstElementChild as HTMLElement;

        expect(wrapper.style.bottom).toContain('200px');

        bar.remove();
        await scrollTo(window.innerHeight * 2);
        expect(wrapper.style.bottom).toBe('');
    });

    it('scrolls up and moves focus to the start of the content', async () => {
        const main = document.createElement('main');
        main.id = 'main-content';
        document.body.append(main);
        const scroll = vi.fn();
        vi.stubGlobal('scrollTo', scroll);
        await scrollTo(window.innerHeight * 2);
        const container = await render(<BackToTop />);

        await act(async () => {
            container.querySelector('button')?.click();
        });

        expect(scroll).toHaveBeenCalledWith({ top: 0, behavior: 'smooth' });
        expect(document.activeElement).toBe(main);
        expect(main.getAttribute('tabindex')).toBe('-1');
    });
});

describe('StatList counter', () => {
    const items = [{ id: 'projects', value: '140+', label: 'Projects' }];
    let frames: FrameRequestCallback[] = [];

    function installFrames() {
        frames = [];
        vi.spyOn(performance, 'now').mockReturnValue(0);
        vi.stubGlobal(
            'requestAnimationFrame',
            (callback: FrameRequestCallback) => {
                frames.push(callback);

                return frames.length;
            },
        );
        vi.stubGlobal('cancelAnimationFrame', () => {});
    }

    async function runFrame(time: number) {
        await act(async () => {
            frames.shift()?.(time);
        });
    }

    it('counts the number up once in view and keeps the final value readable', async () => {
        installObserver();
        installFrames();
        const container = await render(<StatList items={items} animate />);
        const visible = () =>
            container.querySelector('dd > span[aria-hidden="true"]');

        expect(visible()?.textContent).toBe('0+');
        expect(container.querySelector('dd .sr-only')?.textContent).toBe(
            '140+',
        );

        await act(async () => {
            observerCallbacks[0]([{ isIntersecting: true }]);
        });
        await runFrame(700);
        const midway = Number.parseInt(visible()?.textContent ?? '', 10);
        expect(midway).toBeGreaterThan(0);
        expect(midway).toBeLessThan(140);

        await runFrame(1400);
        expect(container.querySelector('dd')?.textContent).toBe('140+');
        expect(container.querySelector('dd .sr-only')).toBeNull();
    });

    it('shows the final values with reduced motion or without the option', async () => {
        installObserver();
        setReducedMotion(true);
        const reduced = await render(<StatList items={items} animate />);
        const plain = await render(<StatList items={items} />);

        expect(reduced.querySelector('dd')?.textContent).toBe('140+');
        expect(plain.querySelector('dd')?.textContent).toBe('140+');
        expect(observerCallbacks).toHaveLength(0);
    });
});

describe('Surface spotlight', () => {
    it('follows a mouse pointer but ignores touch', async () => {
        const container = await render(
            <Surface as="article" spotlight>
                Project
            </Surface>,
        );
        const tile = container.querySelector('article') as HTMLElement;
        vi.spyOn(tile, 'getBoundingClientRect').mockReturnValue({
            left: 10,
            top: 20,
        } as DOMRect);

        await act(async () => {
            tile.dispatchEvent(
                new PointerEvent('pointermove', {
                    bubbles: true,
                    clientX: 60,
                    clientY: 80,
                    pointerType: 'touch',
                }),
            );
        });
        expect(tile.style.getPropertyValue('--spot-x')).toBe('');

        await act(async () => {
            tile.dispatchEvent(
                new PointerEvent('pointermove', {
                    bubbles: true,
                    clientX: 60,
                    clientY: 80,
                    pointerType: 'mouse',
                }),
            );
        });
        expect(tile.style.getPropertyValue('--spot-x')).toBe('50px');
        expect(tile.style.getPropertyValue('--spot-y')).toBe('60px');
        expect(container.querySelector('[aria-hidden="true"]')).not.toBeNull();
    });
});

describe('Bento portfolio', () => {
    it('pairs a wide and a narrow tile in every row, alternating sides', () => {
        expect([0, 1, 2, 3, 4, 5].map(isBentoWide)).toEqual([
            true,
            false,
            false,
            true,
            true,
            false,
        ]);
    });

    it('renders a project as an article with its outcome', async () => {
        const container = await render(
            <ProjectTile
                wide
                eyebrow="Web application"
                title="Client portal"
                description="Replies 60% faster."
                media={<img src="/portal.jpg" alt="Portal" />}
            />,
        );
        const tile = container.querySelector('article');

        expect(tile?.querySelector('h3')?.textContent).toBe('Client portal');
        expect(tile?.textContent).toContain('Replies 60% faster.');
        expect(tile?.querySelector('img')?.getAttribute('alt')).toBe('Portal');
    });
});

describe('Hero media depth', () => {
    it('tilts the media towards a mouse pointer, never for touch, and resets', async () => {
        const container = await render(
            <Hero
                title="Hello"
                media={<img src="/x.jpg" alt="" />}
                mediaDepth
            />,
        );
        const stage = container
            .querySelector('img')
            ?.closest('[class*="perspective"]') as HTMLElement;
        vi.spyOn(stage, 'getBoundingClientRect').mockReturnValue({
            left: 0,
            top: 0,
            width: 100,
            height: 100,
        } as DOMRect);
        const move = (pointerType: string) =>
            act(async () => {
                stage.dispatchEvent(
                    new PointerEvent('pointermove', {
                        bubbles: true,
                        clientX: 100,
                        clientY: 0,
                        pointerType,
                    }),
                );
            });

        await move('touch');
        expect(stage.style.getPropertyValue('--tilt-x')).toBe('');

        await move('mouse');
        expect(stage.style.getPropertyValue('--tilt-x')).toBe('3deg');
        expect(stage.style.getPropertyValue('--tilt-y')).toBe('3deg');

        await act(async () => {
            stage.dispatchEvent(
                new PointerEvent('pointerout', { bubbles: true }),
            );
            stage.dispatchEvent(
                new PointerEvent('pointerleave', { bubbles: false }),
            );
        });
        expect(stage.style.getPropertyValue('--tilt-x')).toBe('');
    });
});

describe('NumberedList', () => {
    it('is an ordered list with decorative counters and titled items', async () => {
        const container = await render(
            <NumberedList
                items={[
                    { id: 'a', title: 'Websites', description: 'Fast sites.' },
                    { id: 'b', title: 'Shops', description: 'Online sales.' },
                ]}
            />,
        );
        const items = container.querySelectorAll('ol > li');

        expect(items).toHaveLength(2);
        expect(
            items[1].querySelector('[aria-hidden="true"]')?.textContent,
        ).toBe('02');
        expect(items[1].querySelector('h3')?.textContent).toBe('Shops');
        expect(items[1].textContent).toContain('Online sales.');
    });
});
