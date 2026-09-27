import type { Page, PageProps, SharedPageProps } from '@inertiajs/core';
import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ContactForm } from '@/components/contact-form';
import { I18nProvider } from '@/i18n';

type Fields = {
    name: string;
    email: string;
    message: string;
    website: string;
    form_token: string;
};

type SubmitOptions = {
    onSuccess?: () => void;
    onHttpException?: (response: { status: number }) => boolean | void;
    onNetworkError?: () => boolean | void;
};

const submitMock =
    vi.fn<(route: { url: string }, options: SubmitOptions) => void>();
let formErrors: Partial<Record<keyof Fields, string>> = {};

vi.mock('@inertiajs/react', async () => {
    const { useState } = await import('react');

    return {
        router: { on: () => () => {} },
        useForm: (initial: Fields) => {
            const [data, setDataState] = useState(initial);

            return {
                data,
                errors: formErrors,
                processing: false,
                setData: (
                    keyOrUpdater: keyof Fields | ((data: Fields) => Fields),
                    value?: string,
                ) =>
                    setDataState((current) =>
                        typeof keyOrUpdater === 'function'
                            ? keyOrUpdater(current)
                            : { ...current, [keyOrUpdater]: value },
                    ),
                reset: () => setDataState(initial),
                submit: (route: { url: string }, options: SubmitOptions) =>
                    submitMock(route, options),
            };
        },
    };
});

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

const mountedRoots: Root[] = [];

function initialPage(locale: string): Page<PageProps & SharedPageProps> {
    return {
        component: 'welcome',
        url: '/',
        version: null,
        props: {
            i18n: {
                area: 'public',
                locale,
                defaultLocale: 'en',
                fallback: 'en',
                dir: 'ltr',
                availableLocales: [],
                messages: {
                    contact: {
                        title: 'Contact us',
                        submit: 'Send message',
                        tooManyAttempts: 'Too many messages.',
                        genericError: 'Could not send.',
                        successTitle: 'Message received',
                        errorSummaryTitle: 'Fix these',
                        fields: {
                            name: 'Name',
                            email: 'Email',
                            message: 'Message',
                        },
                    },
                },
            },
        },
    } as unknown as Page<PageProps & SharedPageProps>;
}

async function render(node: ReactNode, locale = 'en'): Promise<HTMLElement> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(
            <I18nProvider initialPage={initialPage(locale)}>
                {node}
            </I18nProvider>,
        );
    });

    return container;
}

async function submitForm(container: HTMLElement) {
    await act(async () => {
        container
            .querySelector('form')
            ?.dispatchEvent(
                new Event('submit', { bubbles: true, cancelable: true }),
            );
    });
}

function lastOptions(): SubmitOptions {
    return submitMock.mock.calls.at(-1)?.[1] ?? {};
}

beforeEach(() => {
    formErrors = {};
    submitMock.mockReset();
});

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

describe('ContactForm', () => {
    it('posts to the contact route of the current locale with the form token', async () => {
        const container = await render(
            <ContactForm contactForm={{ token: 'signed-token' }} />,
            'de',
        );

        await submitForm(container);

        expect(submitMock).toHaveBeenCalledTimes(1);
        expect(submitMock.mock.calls[0][0].url).toBe('/de/contact-messages');
        expect(
            container
                .querySelector('input[name="website"]')
                ?.closest('[aria-hidden="true"]'),
        ).not.toBeNull();
    });

    it('uses the unprefixed route in the default locale', async () => {
        const container = await render(
            <ContactForm contactForm={{ token: 'signed-token' }} />,
        );

        await submitForm(container);

        expect(submitMock.mock.calls[0][0].url).toBe('/contact-messages');
    });

    it('shows a rate limit alert instead of the error modal on 429', async () => {
        const container = await render(
            <ContactForm contactForm={{ token: 'signed-token' }} />,
        );
        await submitForm(container);

        let handled: boolean | void = undefined;
        await act(async () => {
            handled = lastOptions().onHttpException?.({ status: 429 });
        });

        expect(handled).toBe(false);
        expect(
            container.querySelector('[role="alert"]')?.textContent,
        ).toContain('Too many messages.');
    });

    it('replaces the form with a success message after submission', async () => {
        const container = await render(
            <ContactForm contactForm={{ token: 'signed-token' }} />,
        );
        await submitForm(container);

        await act(async () => {
            lastOptions().onSuccess?.();
        });

        expect(container.querySelector('form')).toBeNull();
        expect(container.textContent).toContain('Message received');
    });

    it('maps backend validation errors to fields and the error summary', async () => {
        formErrors = { email: 'The email must be valid.' };
        const container = await render(
            <ContactForm contactForm={{ token: 'signed-token' }} />,
        );

        const summary = container.querySelector('[role="alert"]');
        expect(summary?.textContent).toContain('The email must be valid.');
        expect(document.activeElement).toBe(
            container.querySelector('input[name="email"]'),
        );
    });

    it('shows an expired form token as a form-level error', async () => {
        formErrors = { form_token: 'This form has expired.' };
        const container = await render(
            <ContactForm contactForm={{ token: 'signed-token' }} />,
        );

        expect(container.textContent).toContain('This form has expired.');
    });
});
