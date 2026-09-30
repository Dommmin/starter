import { contentMediaFamily } from './content-media';
import { feedbackFamily } from './feedback';
import { foundationsFamily } from './foundations';
import { layoutsFamily } from './layouts';
import { panelNavigationFamily } from './panel-navigation';
import type { ShowcaseFamily } from './showcase';

/**
 * Component families shown on /admin/design-system, in registry order
 * (docs/foundation/13-component-registry.md). Add a family: create
 * `sections/<family>.tsx` exporting a `ShowcaseFamily`, then list it here.
 */
export const showcaseFamilies: ShowcaseFamily[] = [
    foundationsFamily,
    layoutsFamily,
    contentMediaFamily,
    panelNavigationFamily,
    feedbackFamily,
];
