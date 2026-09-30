import { choiceFieldsFamily } from './choice-fields';
import { formsFamily } from './forms';
import { foundationsFamily } from './foundations';
import type { ShowcaseFamily } from './showcase';
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
];
