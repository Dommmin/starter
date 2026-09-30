import { actionsFamily } from './actions';
import { choiceFieldsFamily } from './choice-fields';
import { formsFamily } from './forms';
import { foundationsFamily } from './foundations';
import { recordViewFamily } from './record-view';
import type { ShowcaseFamily } from './showcase';
import { tablesFamily } from './tables';
import { textFieldsFamily } from './text-fields';

/**
 * Component families shown on /admin/design-system, in registry order
 * (docs/foundation/13-component-registry.md). Add a family: create
 * `sections/<family>.tsx` exporting a `ShowcaseFamily`, then list it here.
 */
export const showcaseFamilies: ShowcaseFamily[] = [
    foundationsFamily,
    textFieldsFamily,
    choiceFieldsFamily,
    formsFamily,
    tablesFamily,
    recordViewFamily,
    actionsFamily,
];
