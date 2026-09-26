import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
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
});
