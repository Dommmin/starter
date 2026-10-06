import type { ShowcaseFamily } from '../../admin/design-system/sections/showcase';
import { contactFamily } from './contact';
import { contentFamily, seoFamily } from './content';
import { landingFamily } from './landing';
import { navigationFamily } from './navigation';

/**
 * Public (WEB) component families shown on /_design-system, in registry
 * order (docs/foundation/13-component-registry.md). Add a family: create
 * `sections/<family>.tsx` exporting a `ShowcaseFamily`, then list it here.
 */
export const publicShowcaseFamilies: ShowcaseFamily[] = [
    navigationFamily,
    landingFamily,
    contentFamily,
    seoFamily,
    contactFamily,
];
