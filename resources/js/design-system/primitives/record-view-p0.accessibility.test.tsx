import { act, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it } from 'vitest';
import { DescriptionList } from './description-list';
import { RecordDetails } from './record-details';

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

describe('DescriptionList', () => {
    it('renders a placeholder for empty values', async () => {
        const container = await render(
            <DescriptionList
                items={[
                    { id: 'email', label: 'Email', value: 'ada@example.com' },
                    { id: 'phone', label: 'Phone', value: '' },
                ]}
            />,
        );

        const terms = Array.from(container.querySelectorAll('dt')).map(
            (dt) => dt.textContent,
        );
        const values = Array.from(container.querySelectorAll('dd')).map(
            (dd) => dd.textContent,
        );

        expect(terms).toEqual(['Email', 'Phone']);
        expect(values).toEqual(['ada@example.com', '—']);
    });
});

describe('RecordDetails', () => {
    it('renders the title, actions and description list', async () => {
        const container = await render(
            <RecordDetails
                title="Ada Lovelace"
                description="Customer since 2020"
                actions={<button type="button">Edit</button>}
                items={[
                    { id: 'email', label: 'Email', value: 'ada@example.com' },
                ]}
            />,
        );

        expect(container.querySelector('h3')?.textContent).toBe('Ada Lovelace');
        expect(container.textContent).toContain('Customer since 2020');
        expect(container.querySelector('button')?.textContent).toBe('Edit');
    });
});
