import { act, useState, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { describePasswordRules } from '@/lib/password-rules';
import { AuthHeading } from './auth-heading';
import { FormDialog } from './form-dialog';
import { OtpField } from './otp-field';
import { PasswordField } from './password-field';

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

/** Sets the value the way the browser does on typing/Backspace, then fires `input`. */
async function typeInto(input: HTMLInputElement, value: string) {
    await act(async () => {
        Object.getOwnPropertyDescriptor(
            HTMLInputElement.prototype,
            'value',
        )!.set!.call(input, value);
        input.setSelectionRange(value.length, value.length);
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });
}

async function paste(input: HTMLInputElement, text: string) {
    const event = new Event('paste', { bubbles: true, cancelable: true });
    Object.defineProperty(event, 'clipboardData', {
        value: { getData: () => text },
    });

    await act(async () => {
        input.focus();
        input.setSelectionRange(0, input.value.length);
        input.dispatchEvent(event);
    });
}

const SERVER_RULES =
    'minlength: 8; maxlength: 128; required: lower; required: upper; required: digit; required: special;';

/** Echoes the key and params, so the test sees what the helper asked for. */
const t = (key: string, params?: Record<string, string | number>) =>
    params ? `${key}(${Object.values(params).join('|')})` : key;

describe('PasswordField passwordRules', () => {
    it('passes the server rules to the passwordrules attribute and shows the hint as the description', async () => {
        const hint = describePasswordRules(SERVER_RULES, t);
        const container = await render(
            <PasswordField
                name="password"
                label="Password"
                value=""
                onChange={() => {}}
                passwordRules={SERVER_RULES}
                description={hint}
                showPasswordLabel="Show"
                hidePasswordLabel="Hide"
            />,
        );

        const input = container.querySelector('input');
        expect(input?.getAttribute('passwordrules')).toBe(SERVER_RULES);

        const descriptionId = input?.getAttribute('aria-describedby');
        const description = descriptionId
            ? document.getElementById(descriptionId)
            : null;
        expect(description?.textContent).toBe(
            'auth.passwordRules.hint(auth.passwordRules.minLength(8), auth.passwordRules.maxLength(128), auth.passwordRules.lower, auth.passwordRules.upper, auth.passwordRules.digit, auth.passwordRules.special)',
        );
    });

    it('omits the attribute and hint without rules and skips unknown rule properties', async () => {
        const container = await render(
            <PasswordField
                name="password"
                label="Password"
                value=""
                onChange={() => {}}
                showPasswordLabel="Show"
                hidePasswordLabel="Hide"
            />,
        );

        expect(
            container.querySelector('input')?.hasAttribute('passwordrules'),
        ).toBe(false);
        expect(describePasswordRules(undefined, t)).toBeUndefined();
        expect(
            describePasswordRules('allowed: ascii-printable;', t),
        ).toBeUndefined();
    });
});

describe('FormDialog tone', () => {
    function submitButton(): HTMLButtonElement | undefined {
        return Array.from(
            document.querySelectorAll<HTMLButtonElement>(
                'button[type="submit"]',
            ),
        ).find((button) => button.textContent?.includes('Delete account'));
    }

    it('renders the submit action in the destructive tone', async () => {
        await render(
            <FormDialog
                open
                onOpenChange={vi.fn()}
                tone="destructive"
                title="Delete account?"
                submitLabel="Delete account"
                cancelLabel="Cancel"
                closeLabel="Close"
                onSubmit={vi.fn()}
            >
                <input aria-label="Password" />
            </FormDialog>,
        );

        expect(submitButton()?.className).toContain('bg-destructive');
        expect(submitButton()?.className).not.toContain('bg-primary');
    });

    it('keeps the primary tone by default', async () => {
        await render(
            <FormDialog
                open
                onOpenChange={vi.fn()}
                title="Delete account?"
                submitLabel="Delete account"
                cancelLabel="Cancel"
                closeLabel="Close"
                onSubmit={vi.fn()}
            >
                <input aria-label="Password" />
            </FormDialog>,
        );

        expect(submitButton()?.className).toContain('bg-primary');
    });

    it('blocks Escape while the destructive request is pending', async () => {
        const onOpenChange = vi.fn();
        await render(
            <FormDialog
                open
                onOpenChange={onOpenChange}
                tone="destructive"
                title="Delete account?"
                submitLabel="Delete account"
                cancelLabel="Cancel"
                closeLabel="Close"
                onSubmit={vi.fn()}
                isPending
            >
                <input aria-label="Password" />
            </FormDialog>,
        );

        await act(async () => {
            document.activeElement?.dispatchEvent(
                new KeyboardEvent('keydown', {
                    key: 'Escape',
                    bubbles: true,
                    cancelable: true,
                }),
            );
        });

        expect(onOpenChange).not.toHaveBeenCalled();
        expect(document.querySelector('[role="dialog"]')).not.toBeNull();
    });
});

function ControlledOtp({
    onComplete,
    error,
    isPending,
}: {
    onComplete?: (value: string) => void;
    error?: string;
    isPending?: boolean;
}) {
    const [value, setValue] = useState('');

    return (
        <>
            <OtpField
                id="code"
                name="code"
                label="Code"
                value={value}
                onChange={setValue}
                onComplete={onComplete}
                error={error}
                isPending={isPending}
                required
            />
            <output data-testid="value">{value}</output>
        </>
    );
}

function currentValue(container: HTMLElement): string {
    return container.querySelector('output')?.textContent ?? '';
}

describe('OtpField', () => {
    it('is one labelled numeric input with one-time-code autofill', async () => {
        const container = await render(<ControlledOtp />);
        const input = container.querySelector<HTMLInputElement>('#code');

        expect(input?.name).toBe('code');
        expect(input?.getAttribute('autocomplete')).toBe('one-time-code');
        expect(input?.getAttribute('inputmode')).toBe('numeric');
        expect(input?.maxLength).toBe(6);
        expect(input?.required).toBe(true);
        expect(input?.labels?.[0]?.textContent).toContain('Code');
        expect(container.querySelectorAll('input')).toHaveLength(1);
    });

    it('fills every cell from a pasted code, ignoring separators, and reports completion', async () => {
        const onComplete = vi.fn();
        const container = await render(
            <ControlledOtp onComplete={onComplete} />,
        );
        const input = container.querySelector<HTMLInputElement>('#code')!;

        await paste(input, '123 456');

        expect(currentValue(container)).toBe('123456');
        expect(onComplete).toHaveBeenCalledWith('123456');
    });

    it('accepts typed digits, rejects letters and deletes with Backspace', async () => {
        const container = await render(<ControlledOtp />);
        const input = container.querySelector<HTMLInputElement>('#code')!;

        await typeInto(input, '12');
        expect(currentValue(container)).toBe('12');

        await typeInto(input, '12a');
        expect(currentValue(container)).toBe('12');

        await typeInto(input, '1');
        expect(currentValue(container)).toBe('1');
    });

    it('links the error to the input and marks it invalid', async () => {
        const container = await render(
            <ControlledOtp error="The code is invalid." />,
        );
        const input = container.querySelector<HTMLInputElement>('#code');
        const errorId = input?.getAttribute('aria-describedby');

        expect(input?.getAttribute('aria-invalid')).toBe('true');
        expect(
            errorId ? document.getElementById(errorId)?.textContent : '',
        ).toBe('The code is invalid.');
        expect(container.querySelector('[role="alert"]')?.textContent).toBe(
            'The code is invalid.',
        );
    });

    it('locks the input while pending', async () => {
        const container = await render(<ControlledOtp isPending />);
        const input = container.querySelector<HTMLInputElement>('#code');

        expect(input?.disabled).toBe(true);
        expect(input?.getAttribute('aria-busy')).toBe('true');
    });
});

describe('AuthHeading', () => {
    it('renders the title as the page h1 followed by the description', async () => {
        const container = await render(
            <AuthHeading title="Log in" description="Enter your e-mail" />,
        );

        const heading = container.querySelector('h1');
        expect(heading?.textContent).toBe('Log in');
        expect(container.querySelectorAll('h1')).toHaveLength(1);
        expect(heading?.nextElementSibling?.textContent).toBe(
            'Enter your e-mail',
        );
    });
});
