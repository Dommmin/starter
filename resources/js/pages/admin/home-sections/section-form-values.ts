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
export const MAX_HERO_STATS = 4;
export const MAX_TESTIMONIAL_STATS = 4;
export const MAX_PROJECTS = 12;
export const MAX_MEMBERS = 8;
export const MAX_PLANS = 4;

/** Mirrors `PricingContentData::CURRENCIES`. */
export const CURRENCIES = ['PLN', 'EUR', 'USD', 'GBP', 'CHF'] as const;

/** Select values of the plan price type and highlight. */
export const PRICE_EXACT = 'exact';
export const PRICE_FROM = 'from';
export const HIGHLIGHT_NO = 'no';
export const HIGHLIGHT_YES = 'yes';

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
    /** DAM asset id as a string; `''` means no image. */
    imageId: string;
    note: string;
    items: ResourceFormRepeaterItem[];
    stats: ResourceFormRepeaterItem[];
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
    imageId: '',
    note: '',
    items: [],
    stats: [],
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
                imageId:
                    section.content.imageId === null
                        ? ''
                        : String(section.content.imageId),
                stats: section.content.stats.map((stat) => ({
                    value: stat.value,
                    label: stat.label,
                })),
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
                stats: section.content.stats.map((stat) => ({
                    value: stat.value,
                    label: stat.label,
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
        case 'portfolio':
            return {
                ...blank,
                title: section.content.title ?? '',
                description: section.content.description ?? '',
                items: section.content.items.map((item) => ({
                    imageId: item.imageId === null ? '' : String(item.imageId),
                    title: item.title,
                    category: item.category ?? '',
                    description: item.description ?? '',
                    url: item.url ?? '',
                })),
            };
        case 'team':
            return {
                ...blank,
                title: section.content.title ?? '',
                description: section.content.description ?? '',
                items: section.content.items.map((member) => ({
                    photoId:
                        member.photoId === null ? '' : String(member.photoId),
                    name: member.name,
                    role: member.role,
                    bio: member.bio ?? '',
                    skills: member.skills.join(', '),
                })),
            };
        case 'pricing':
            return {
                ...blank,
                title: section.content.title ?? '',
                description: section.content.description ?? '',
                note: section.content.note ?? '',
                items: section.content.items.map((plan) => ({
                    name: plan.name,
                    price: priceValue(plan.priceMinor),
                    currency: plan.currency,
                    priceMode: plan.priceFrom ? PRICE_FROM : PRICE_EXACT,
                    period: plan.period ?? '',
                    description: plan.description ?? '',
                    features: plan.features.join('\n'),
                    highlighted: plan.highlighted
                        ? HIGHLIGHT_YES
                        : HIGHLIGHT_NO,
                    actionTarget: plan.action?.target ?? NONE,
                    actionLabel: plan.action?.label ?? '',
                    actionPageId:
                        plan.action?.pageId === null ||
                        plan.action?.pageId === undefined
                            ? ''
                            : String(plan.action.pageId),
                })),
            };
    }
}

function isHomeIcon(value: string): value is App.Enums.HomeIcon {
    return Object.hasOwn(homeIcons, value);
}

/**
 * Separated text (commas or new lines by default) as a list of trimmed,
 * non-empty entries.
 */
const toList = (value: string, separator: RegExp = /[,\n]/): string[] =>
    value
        .split(separator)
        .map((entry) => entry.trim())
        .filter((entry) => entry !== '');

/** Price in the smallest unit as an editable amount, e.g. 290050 → `2900.50`. */
function priceValue(priceMinor: number | null): string {
    if (priceMinor === null) {
        return '';
    }

    return priceMinor % 100 === 0
        ? String(priceMinor / 100)
        : (priceMinor / 100).toFixed(2);
}

/**
 * An amount such as `2 900,50` in the smallest unit; empty means a quote
 * on request. Anything else is sent as-is so the backend rejects it next
 * to the field.
 */
function toPriceMinor(value: string): number | string | null {
    const normalized = value.replace(/\s/g, '').replace(',', '.');

    if (normalized === '') {
        return null;
    }

    return /^\d+(\.\d{1,2})?$/.test(normalized)
        ? Math.round(Number(normalized) * 100)
        : value;
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
                imageId: intOrNull(values.imageId),
                stats: values.stats.map((stat) => ({
                    value: stat.value ?? '',
                    label: stat.label ?? '',
                })),
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
                stats: values.stats.map((stat) => ({
                    value: stat.value ?? '',
                    label: stat.label ?? '',
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
        case 'portfolio':
            return {
                title: orNull(values.title),
                description: orNull(values.description),
                items: values.items.map((item) => ({
                    title: item.title ?? '',
                    category: orNull(item.category ?? ''),
                    description: orNull(item.description ?? ''),
                    imageId: intOrNull(item.imageId ?? ''),
                    url: orNull(item.url ?? ''),
                })),
            };
        case 'team':
            return {
                title: orNull(values.title),
                description: orNull(values.description),
                items: values.items.map((member) => ({
                    name: member.name ?? '',
                    role: member.role ?? '',
                    bio: orNull(member.bio ?? ''),
                    photoId: intOrNull(member.photoId ?? ''),
                    skills: toList(member.skills ?? ''),
                })),
            };
        case 'pricing':
            return {
                title: orNull(values.title),
                description: orNull(values.description),
                note: orNull(values.note),
                items: values.items.map((plan) => {
                    const target = plan.actionTarget ?? NONE;

                    return {
                        name: plan.name ?? '',
                        priceMinor: toPriceMinor(plan.price ?? ''),
                        currency: plan.currency ?? '',
                        priceFrom: plan.priceMode === PRICE_FROM,
                        period: orNull(plan.period ?? ''),
                        description: orNull(plan.description ?? ''),
                        features: toList(plan.features ?? '', /\n/),
                        highlighted: plan.highlighted === HIGHLIGHT_YES,
                        action:
                            target === NONE
                                ? null
                                : {
                                      label: plan.actionLabel ?? '',
                                      target,
                                      pageId:
                                          target === 'page'
                                              ? intOrNull(
                                                    plan.actionPageId ?? '',
                                                )
                                              : null,
                                  },
                    };
                }),
            };
    }
}

const ACTION_FIELDS: Record<string, string> = {
    label: 'Label',
    target: 'Target',
    pageId: 'PageId',
};

/** Stored plan keys edited under another item field name. */
const PLAN_FIELDS: Record<string, string> = {
    priceMinor: 'price',
    priceFrom: 'priceMode',
    action: 'action',
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

        const field = key.startsWith('content.') ? key.slice(8) : key;
        const planField = /^(items\.\d+)\.(\w+)(?:\.(\w+))?$/.exec(field);

        if (planField && PLAN_FIELDS[planField[2]]) {
            const nested = planField[2] === 'action';
            const suffix = nested
                ? (ACTION_FIELDS[planField[3] ?? 'target'] ?? 'Target')
                : '';
            mapped[`${planField[1]}.${PLAN_FIELDS[planField[2]]}${suffix}`] ??=
                message;
            continue;
        }

        // A list entry inside an item (`items.0.skills.3`) is edited as one
        // text field (`items.0.skills`); keep the first message.
        const listEntry = /^(items\.\d+\.\w+)\.\d+$/.exec(field);

        if (listEntry) {
            mapped[listEntry[1]] ??= message;
            continue;
        }

        mapped[field] = message;
    }

    return mapped;
}
