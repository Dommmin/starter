import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it } from 'vitest';
import { ErrorSummary } from './error-summary';
import { FormActions } from './form-actions';
import { FormSection } from './form-section';

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

describe('FormSection', () => {
    it('labels the section landmark with its heading', async () => {
        const container = await render(
            <FormSection title="Contact" description="How we reach you">
                <p>field</p>
            </FormSection>,
        );

        const section = container.querySelector('section');
        const heading = container.querySelector('h3');
        expect(section?.getAttribute('aria-labelledby')).toBe(heading?.id);
        expect(heading?.textContent).toBe('Contact');
    });
});

describe('FormActions', () => {
    it('renders its actions', async () => {
        const container = await render(
            <FormActions>
                <button type="button">Cancel</button>
                <button type="submit">Save</button>
            </FormActions>,
        );

        expect(container.querySelectorAll('button')).toHaveLength(2);
    });
});

describe('ErrorSummary', () => {
    it('renders nothing when there are no errors', async () => {
        const container = await render(
            <ErrorSummary title="Problems" items={[]} />,
        );

        expect(container.querySelector('[role="alert"]')).toBeNull();
    });

    it('moves focus to the invalid field when an item is activated', async () => {
        document.body.innerHTML =
            '<input id="email-field" aria-label="Email" />';
        const container = await render(
            <ErrorSummary
                title="2 problems with your submission"
                autoFocus={false}
                items={[
                    {
                        fieldId: 'email-field',
                        label: 'Email',
                        message: 'Required',
                    },
                ]}
            />,
        );

        const link = container.querySelector('a');
        await act(async () => {
            link?.dispatchEvent(
                new MouseEvent('click', { bubbles: true, cancelable: true }),
            );
        });

        expect(document.activeElement?.id).toBe('email-field');
    });

    it('auto-focuses the first invalid field as soon as errors appear', async () => {
        document.body.innerHTML =
            '<input id="email-field" aria-label="Email" /><input id="name-field" aria-label="Name" />';

        await render(<ErrorSummary title="Problems" items={[]} />);
        expect(document.activeElement?.id).not.toBe('email-field');

        await render(
            <ErrorSummary
                title="2 problems with your submission"
                items={[
                    {
                        fieldId: 'email-field',
                        label: 'Email',
                        message: 'Required',
                    },
                    {
                        fieldId: 'name-field',
                        label: 'Name',
                        message: 'Required',
                    },
                ]}
            />,
        );

        expect(document.activeElement?.id).toBe('email-field');
    });

    it('does not steal focus again on re-renders while the same errors persist', async () => {
        document.body.innerHTML =
            '<input id="email-field" aria-label="Email" /><input id="other" />';
        const errorContainer = document.createElement('div');
        document.body.append(errorContainer);
        const root = createRoot(errorContainer);
        mountedRoots.push(root);

        const items = [
            { fieldId: 'email-field', label: 'Email', message: 'Required' },
        ];

        await act(async () => {
            root.render(
                <ErrorSummary
                    title="2 problems with your submission"
                    items={items}
                />,
            );
        });

        const otherInput = document.getElementById('other') as HTMLInputElement;
        otherInput.focus();
        expect(document.activeElement).toBe(otherInput);

        await act(async () => {
            root.render(
                <ErrorSummary
                    title="2 problems with your submission"
                    items={items}
                />,
            );
        });

        expect(document.activeElement).toBe(otherInput);
    });
});
