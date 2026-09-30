import { actionsFamily } from './actions';
import { foundationsFamily } from './foundations';
import { recordViewFamily } from './record-view';
import type { ShowcaseFamily } from './showcase';
import { tablesFamily } from './tables';

/**
 * Component families shown on /admin/design-system, in registry order
 * (docs/foundation/13-component-registry.md). Add a family: create
 * `sections/<family>.tsx` exporting a `ShowcaseFamily`, then list it here.
 */
export const showcaseFamilies: ShowcaseFamily[] = [
    foundationsFamily,
    tablesFamily,
    recordViewFamily,
    actionsFamily,
];
