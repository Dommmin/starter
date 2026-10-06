import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { CheckboxGroupField } from './checkbox-group-field';

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

const channels = [
    { value: 'email', label: 'Email' },
    { value: 'sms', label: 'SMS', description: 'Only urgent messages' },
    { value: 'push', label: 'Push', disabled: true },
];

describe('CheckboxGroupField', () => {
    it('names the group and links its description and error', async () => {
        const container = await render(
            <CheckboxGroupField
                name="channels"
                label="Channels"
                value={[]}
                onChange={vi.fn()}
                options={channels}
                description="Pick any"
                error="Choose at least one channel"
                required
            />,
        );

        const group = container.querySelector<HTMLElement>('[role="group"]');
        const labelId = group?.getAttribute('aria-labelledby') ?? '';
        expect(document.getElementById(labelId)?.textContent).toContain(
            'Channels',
        );
        expect(group?.getAttribute('aria-invalid')).toBe('true');
        expect(group?.getAttribute('aria-required')).toBe('true');

        const described = (group?.getAttribute('aria-describedby') ?? '')
            .split(' ')
            .map((id) => document.getElementById(id)?.textContent);
        expect(described).toEqual(['Pick any', 'Choose at least one channel']);
        expect(container.querySelector('[role="alert"]')?.textContent).toBe(
            'Choose at least one channel',
        );
    });

    it('gives every checkbox its own label and keeps disabled options out of reach', async () => {
        const container = await render(
            <CheckboxGroupField
                name="channels"
                label="Channels"
                value={['sms']}
                onChange={vi.fn()}
                options={channels}
            />,
        );

        const boxes = [
            ...container.querySelectorAll<HTMLButtonElement>(
                'button[role="checkbox"]',
            ),
        ];
        expect(boxes).toHaveLength(3);
        expect(
            boxes.map(
                (box) =>
                    container.querySelector(`label[for="${box.id}"]`)
                        ?.textContent,
            ),
        ).toEqual(['Email', 'SMSOnly urgent messages', 'Push']);
        expect(boxes.map((box) => box.getAttribute('aria-checked'))).toEqual([
            'false',
            'true',
            'false',
        ]);
        expect(boxes[2]?.disabled).toBe(true);
        expect(boxes.every((box) => box.tabIndex === 0)).toBe(true);
    });

    it('toggles options and keeps the value in option order', async () => {
        const onChange = vi.fn();
        const container = await render(
            <CheckboxGroupField
                name="channels"
                label="Channels"
                value={['sms']}
                onChange={onChange}
                options={channels}
            />,
        );

        const boxes = container.querySelectorAll<HTMLButtonElement>(
            'button[role="checkbox"]',
        );

        await act(async () => {
            boxes[0]?.click();
        });
        expect(onChange).toHaveBeenLastCalledWith(['email', 'sms']);

        await act(async () => {
            boxes[1]?.click();
        });
        expect(onChange).toHaveBeenLastCalledWith([]);
    });
});
