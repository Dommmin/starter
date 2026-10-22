import { act, useState, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vite-plus/test';
import { ContactSection } from './contact-section';

Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });

const mountedRoots: Root[] = [];

async function render(node: ReactNode): Promise<HTMLElement> {
    const container = document.createElement('div');
    document.body.append(container);
    const root = createRoot(container);
    mountedRoots.push(root);

    await act(async () => {
        root.render(node);
    });

    return container;
}

afterEach(async () => {
    await act(async () => {
        mountedRoots.splice(0).forEach((root) => root.unmount());
    });
    document.body.replaceChildren();
});

// The extra fields are a lazy chunk; load it once up front so a slow
// transform under load never races the polling below.
beforeAll(async () => {
    await import('./contact-extra-fields');
});

describe('ContactSection', () => {
    it('submits the current values when valid', async () => {
        const onSubmit = vi.fn();
        const container = await render(
            <ContactSection
                title="Get in touch"
                labels={{ name: 'Name', email: 'Email', message: 'Message' }}
                errorSummaryTitle="Please fix the following"
                values={{
                    name: 'Ada',
                    email: 'ada@example.com',
                    message: 'Hello',
                }}
                onChange={vi.fn()}
                onSubmit={onSubmit}
                submitLabel="Send"
            />,
        );

        const form = container.querySelector('form');
        await act(async () => {
            form?.dispatchEvent(
                new Event('submit', { bubbles: true, cancelable: true }),
            );
        });

        expect(onSubmit).toHaveBeenCalledTimes(1);
    });

    it('does not resubmit while pending, even via a raw submit event (Enter key path)', async () => {
        const onSubmit = vi.fn();
        const container = await render(
            <ContactSection
                title="Get in touch"
                labels={{ name: 'Name', email: 'Email', message: 'Message' }}
                errorSummaryTitle="Please fix the following"
                values={{
                    name: 'Ada',
                    email: 'ada@example.com',
                    message: 'Hello',
                }}
                onChange={vi.fn()}
                onSubmit={onSubmit}
                submitLabel="Send"
                isPending
            />,
        );

        const form = container.querySelector('form');
        await act(async () => {
            form?.dispatchEvent(
                new Event('submit', { bubbles: true, cancelable: true }),
            );
        });

        expect(onSubmit).not.toHaveBeenCalled();
    });

    it('shows an error summary and moves focus to the first invalid field', async () => {
        const container = await render(
            <ContactSection
                title="Get in touch"
                labels={{ name: 'Name', email: 'Email', message: 'Message' }}
                errorSummaryTitle="Please fix the following"
                values={{ name: 'Ada', email: '', message: '' }}
                onChange={vi.fn()}
                onSubmit={vi.fn()}
                errors={{
                    email: 'Email is required',
                    message: 'Message is required',
                }}
                submitLabel="Send"
            />,
        );

        const summary = container.querySelector('[role="alert"]');
        expect(summary?.textContent).toContain('Please fix the following');
        expect(summary?.textContent).toContain('Email is required');

        const emailInput = container.querySelector('input[type="email"]');
        expect(document.activeElement).toBe(emailInput);
    });

    it('moves focus to a later field when its error summary entry is activated', async () => {
        const container = await render(
            <ContactSection
                title="Get in touch"
                labels={{ name: 'Name', email: 'Email', message: 'Message' }}
                errorSummaryTitle="Please fix the following"
                values={{ name: 'Ada', email: '', message: '' }}
                onChange={vi.fn()}
                onSubmit={vi.fn()}
                errors={{
                    email: 'Email is required',
                    message: 'Message is required',
                }}
                submitLabel="Send"
            />,
        );

        const messageLink = Array.from(container.querySelectorAll('a')).find(
            (link) => link.textContent?.startsWith('Message'),
        );

        await act(async () => {
            messageLink?.dispatchEvent(
                new MouseEvent('click', { bubbles: true, cancelable: true }),
            );
        });

        const textarea = container.querySelector('textarea');
        expect(document.activeElement).toBe(textarea);
    });

    it('renders a success slot instead of the form', async () => {
        const container = await render(
            <ContactSection
                title="Get in touch"
                labels={{ name: 'Name', email: 'Email', message: 'Message' }}
                errorSummaryTitle="Please fix the following"
                values={{ name: '', email: '', message: '' }}
                onChange={vi.fn()}
                onSubmit={vi.fn()}
                submitLabel="Send"
                success={<p>Thanks, we will reply soon.</p>}
            />,
        );

        expect(container.querySelector('form')).toBeNull();
        expect(container.textContent).toContain('Thanks, we will reply soon.');
    });

    it('moves focus to the announced success message after a submission', async () => {
        function SendingForm() {
            const [isSent, setSent] = useState(false);

            return (
                <ContactSection
                    title="Get in touch"
                    labels={{
                        name: 'Name',
                        email: 'Email',
                        message: 'Message',
                    }}
                    errorSummaryTitle="Please fix the following"
                    values={{
                        name: 'Ada',
                        email: 'ada@example.com',
                        message: 'Hello there',
                    }}
                    onChange={vi.fn()}
                    onSubmit={() => setSent(true)}
                    submitLabel="Send"
                    success={
                        isSent ? <p>Thanks, we will reply soon.</p> : undefined
                    }
                />
            );
        }

        const container = await render(<SendingForm />);
        const submit = container.querySelector<HTMLButtonElement>(
            'button[type="submit"]',
        );
        submit?.focus();

        await act(async () => submit?.click());

        const message = container.querySelector('[role="status"]');
        expect(message?.textContent).toBe('Thanks, we will reply soon.');
        expect(message?.getAttribute('tabindex')).toBe('-1');
        expect(document.activeElement).toBe(message);
    });

    it('does not take focus for a success message present on first render', async () => {
        await render(
            <ContactSection
                title="Get in touch"
                labels={{ name: 'Name', email: 'Email', message: 'Message' }}
                errorSummaryTitle="Please fix the following"
                values={{ name: '', email: '', message: '' }}
                onChange={vi.fn()}
                onSubmit={vi.fn()}
                submitLabel="Send"
                success={<p>Thanks, we will reply soon.</p>}
            />,
        );

        expect(document.activeElement).toBe(document.body);
    });

    it('renders a form-level error as an alert', async () => {
        const container = await render(
            <ContactSection
                title="Get in touch"
                labels={{ name: 'Name', email: 'Email', message: 'Message' }}
                errorSummaryTitle="Please fix the following"
                values={{
                    name: 'Ada',
                    email: 'ada@example.com',
                    message: 'Hi',
                }}
                onChange={vi.fn()}
                onSubmit={vi.fn()}
                formError="Too many messages. Try again later."
                submitLabel="Send"
            />,
        );

        const alert = container.querySelector('[role="alert"]');
        expect(alert?.textContent).toContain(
            'Too many messages. Try again later.',
        );
    });

    it('hides the spam trap from people and assistive technology', async () => {
        const onSpamChange = vi.fn();
        const container = await render(
            <ContactSection
                title="Get in touch"
                labels={{ name: 'Name', email: 'Email', message: 'Message' }}
                errorSummaryTitle="Please fix the following"
                values={{ name: '', email: '', message: '' }}
                onChange={vi.fn()}
                onSubmit={vi.fn()}
                spamTrap={{
                    name: 'website',
                    label: 'Leave this field empty',
                    value: '',
                    onChange: onSpamChange,
                }}
                submitLabel="Send"
            />,
        );

        const trap = container.querySelector<HTMLInputElement>(
            'input[name="website"]',
        );
        expect(trap).not.toBeNull();
        expect(trap?.tabIndex).toBe(-1);
        expect(trap?.getAttribute('autocomplete')).toBe('off');
        expect(trap?.closest('[aria-hidden="true"]')).not.toBeNull();
        expect(
            container
                .querySelector('input[name="name"]')
                ?.getAttribute('autocomplete'),
        ).toBe('name');
    });

    it('focuses the first invalid field after every failed submit', async () => {
        let resolveRequest: (errors: Record<string, string>) => void = () => {};

        function Page() {
            const [errors, setErrors] = useState<Record<string, string>>({});
            const [isPending, setPending] = useState(false);
            resolveRequest = (next) => {
                setErrors(next);
                setPending(false);
            };

            return (
                <ContactSection
                    title="Get in touch"
                    labels={{
                        name: 'Name',
                        email: 'Email',
                        message: 'Message',
                    }}
                    errorSummaryTitle="Please fix the following"
                    values={{ name: '', email: 'ada', message: '' }}
                    onChange={vi.fn()}
                    onSubmit={() => setPending(true)}
                    errors={errors}
                    isPending={isPending}
                    submitLabel="Send"
                />
            );
        }

        const container = await render(<Page />);
        const form = container.querySelector('form')!;
        const submit = container.querySelector<HTMLButtonElement>(
            'button[type="submit"]',
        )!;

        const failedResponses: Record<string, string>[] = [
            { email: 'Email is invalid.', message: 'Message is required.' },
            { email: 'Email is invalid.' },
        ];

        for (const errors of failedResponses) {
            submit.focus();
            await act(async () => {
                form.dispatchEvent(
                    new Event('submit', { bubbles: true, cancelable: true }),
                );
            });
            await act(async () => resolveRequest(errors));

            expect(
                (document.activeElement as HTMLInputElement | null)?.name,
            ).toBe('email');
        }
    });

    it('renders configured extra fields after the message and lists their errors in the summary', async () => {
        const container = await render(
            <ContactSection
                title="Request a quote"
                labels={{ name: 'Name', email: 'Email', message: 'Message' }}
                errorSummaryTitle="Please fix the following"
                values={{
                    name: 'Ada',
                    email: 'ada@example.com',
                    message: 'Hi',
                }}
                onChange={vi.fn()}
                onSubmit={vi.fn()}
                submitLabel="Send"
                extraFields={[
                    {
                        name: 'fields[budget]',
                        type: 'select',
                        label: 'Budget',
                        required: true,
                        value: '',
                        onChange: vi.fn(),
                        options: [{ value: 'small', label: 'Small' }],
                        error: 'Choose a budget.',
                    },
                    {
                        name: 'fields[deadline]',
                        type: 'text',
                        label: 'Deadline',
                        value: 'March',
                        onChange: vi.fn(),
                    },
                    {
                        name: 'fields[phone_ok]',
                        type: 'checkbox',
                        label: 'You may call me',
                        value: true,
                        onChange: vi.fn(),
                    },
                ]}
            />,
        );

        // The extra fields load lazily; wait for the chunk.
        let deadline: HTMLInputElement | null = null;
        for (let attempt = 0; attempt < 50 && deadline === null; attempt++) {
            await act(async () => {
                await new Promise((resolve) => setTimeout(resolve, 10));
            });
            deadline = container.querySelector<HTMLInputElement>(
                'input[name="fields[deadline]"]',
            );
        }
        const message = container.querySelector('textarea[name="message"]');

        expect(deadline?.value).toBe('March');
        expect(message).not.toBeNull();
        expect(
            (message as Node).compareDocumentPosition(deadline as Node) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
        expect(
            container
                .querySelector('[role="checkbox"]')
                ?.getAttribute('aria-checked'),
        ).toBe('true');
        expect(container.textContent).toContain('Choose a budget.');
        expect(
            container.querySelector('a[href$="-extra-fields[budget]"]'),
        ).not.toBeNull();
    });
});
