import { homeIcons } from '@/components/home/home-icons';
import type { ResourceFormRepeaterItem } from '@/design-system/primitives';

type Section = App.Data.Admin.HomeSections.HomeSectionFormData;
type Action = App.Data.Home.HomeActionData;

/** Select sentinel for "no button" / "no icon" (options cannot be empty). */
export const NONE = 'none';

/** Mirrors the backend limits (the request validates them again). */
export const MAX_FEATURES = 12;
export const MAX_TESTIMONIALS = 6;
export const MAX_FAQ_LIMIT = 50;
export const MAX_ARTICLES_LIMIT = 6;

/**
 * Flat editor state shared by every section type; each type uses a subset.
 * Buttons are split into label/target/page fields, repeated items are flat
 * string records.
 */
export type SectionFormValues = {
    eyebrow: string;
    title: string;
    description: string;
    limit: string;
    primaryLabel: string;
    primaryTarget: string;
    primaryPageId: string;
    secondaryLabel: string;
    secondaryTarget: string;
    secondaryPageId: string;
    items: ResourceFormRepeaterItem[];
};

const blank: SectionFormValues = {
    eyebrow: '',
    title: '',
    description: '',
    limit: '',
    primaryLabel: '',
    primaryTarget: NONE,
    primaryPageId: '',
    secondaryLabel: '',
    secondaryTarget: NONE,
    secondaryPageId: '',
    items: [],
};

function actionValues(
    prefix: 'primary' | 'secondary',
    action: Action | null,
): Partial<SectionFormValues> {
    const label = action?.label ?? '';
    const target = action?.target ?? NONE;
    const pageId =
        action?.pageId === null || action?.pageId === undefined
            ? ''
            : String(action.pageId);

    return prefix === 'primary'
        ? { primaryLabel: label, primaryTarget: target, primaryPageId: pageId }
        : {
              secondaryLabel: label,
              secondaryTarget: target,
              secondaryPageId: pageId,
          };
}

export function toFormValues(section: Section): SectionFormValues {
    switch (section.type) {
        case 'hero':
            return {
                ...blank,
                eyebrow: section.content.eyebrow ?? '',
                title: section.content.title,
                description: section.content.description ?? '',
                ...actionValues('primary', section.content.primaryAction),
                ...actionValues('secondary', section.content.secondaryAction),
            };
        case 'cta':
            return {
                ...blank,
                title: section.content.title,
                description: section.content.description ?? '',
                ...actionValues('primary', section.content.primaryAction),
                ...actionValues('secondary', section.content.secondaryAction),
            };
        case 'features':
            return {
                ...blank,
                title: section.content.title ?? '',
                description: section.content.description ?? '',
                items: section.content.items.map((item) => ({
                    icon: item.icon ?? NONE,
                    title: item.title,
                    description: item.description,
                })),
            };
        case 'testimonials':
            return {
                ...blank,
                title: section.content.title ?? '',
                items: section.content.items.map((item) => ({
                    author: item.author,
                    role: item.role ?? '',
                    quote: item.quote,
                })),
            };
        case 'faq':
            return {
                ...blank,
                title: section.content.title ?? '',
                description: section.content.description ?? '',
                limit:
                    section.content.limit === null
                        ? ''
                        : String(section.content.limit),
            };
        case 'latest_articles':
            return {
                ...blank,
                title: section.content.title ?? '',
                limit: String(section.content.limit),
            };
        case 'contact':
            return {
                ...blank,
                title: section.content.title,
                description: section.content.description ?? '',
            };
    }
}

function isHomeIcon(value: string): value is App.Enums.HomeIcon {
    return Object.hasOwn(homeIcons, value);
}

const orNull = (value: string): string | null =>
    value.trim() === '' ? null : value;

const intOrNull = (value: string): number | null =>
    value.trim() === '' ? null : Number(value);

/**
 * Request payload of an action; an unknown target is sent as-is so the
 * backend rejects it next to the field.
 */
function toAction(
    values: SectionFormValues,
    prefix: 'primary' | 'secondary',
): Record<string, string | number | null> | null {
    const target = values[`${prefix}Target`];

    if (target === NONE) {
        return null;
    }

    return {
        label: values[`${prefix}Label`],
        target,
        pageId: target === 'page' ? intOrNull(values[`${prefix}PageId`]) : null,
    };
}

/**
 * `content` request payload of the section type (the backend validates
 * the closed schema and rejects anything else).
 */
export function toContent(
    type: App.Enums.HomeSectionType,
    values: SectionFormValues,
): Record<string, unknown> {
    switch (type) {
        case 'hero':
            return {
                eyebrow: orNull(values.eyebrow),
                title: values.title,
                description: orNull(values.description),
                primaryAction: toAction(values, 'primary'),
                secondaryAction: toAction(values, 'secondary'),
            };
        case 'cta':
            return {
                title: values.title,
                description: orNull(values.description),
                primaryAction: toAction(values, 'primary'),
                secondaryAction: toAction(values, 'secondary'),
            };
        case 'features':
            return {
                title: orNull(values.title),
                description: orNull(values.description),
                items: values.items.map((item) => ({
                    icon: item.icon && isHomeIcon(item.icon) ? item.icon : null,
                    title: item.title ?? '',
                    description: item.description ?? '',
                })),
            };
        case 'testimonials':
            return {
                title: orNull(values.title),
                items: values.items.map((item) => ({
                    author: item.author ?? '',
                    role: orNull(item.role ?? ''),
                    quote: item.quote ?? '',
                })),
            };
        case 'faq':
            return {
                title: orNull(values.title),
                description: orNull(values.description),
                limit: intOrNull(values.limit),
            };
        case 'latest_articles':
            return {
                title: orNull(values.title),
                limit: intOrNull(values.limit),
            };
        case 'contact':
            return {
                title: values.title,
                description: orNull(values.description),
            };
    }
}

const ACTION_FIELDS: Record<string, string> = {
    label: 'Label',
    target: 'Target',
    pageId: 'PageId',
};

/**
 * Map backend error keys (`content.primaryAction.label`,
 * `content.items.0.title`) to editor field names (`primaryLabel`,
 * `items.0.title`). Keys that match no field are returned unchanged.
 */
export function toFieldErrors(
    errors: Partial<Record<string, string>>,
): Partial<Record<string, string>> {
    const mapped: Partial<Record<string, string>> = {};

    for (const [key, message] of Object.entries(errors)) {
        const action = /^content\.(primary|secondary)Action(?:\.(\w+))?$/.exec(
            key,
        );

        if (action) {
            const suffix = ACTION_FIELDS[action[2] ?? 'target'] ?? 'Target';
            mapped[`${action[1]}${suffix}`] = message;
            continue;
        }

        mapped[key.startsWith('content.') ? key.slice(8) : key] = message;
    }

    return mapped;
}
