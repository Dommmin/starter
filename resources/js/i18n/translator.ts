import type { TranslationFunction, TranslationParams } from './types';

const PLURAL_CATEGORIES = new Set([
    'zero',
    'one',
    'two',
    'few',
    'many',
    'other',
]);

// `{{name}}`, `{name}` and Laravel's `:name` — one pass, so a substituted
// value is never parsed again as a placeholder.
const PLACEHOLDER =
    /\{\{\s*([a-zA-Z_]+)\s*\}\}|\{([a-zA-Z_]+)\}|:([a-zA-Z_]+)/g;

type Messages = Record<string, unknown>;

function isMessages(value: unknown): value is Messages {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

/**
 * A Laravel plural group: `['one' => '...', 'few' => '...', 'other' => '...']`
 * keyed only by CLDR plural categories.
 */
function isPluralForms(value: unknown): value is Messages {
    if (!isMessages(value)) {
        return false;
    }
    const keys = Object.keys(value);
    return keys.length > 0 && keys.every((key) => PLURAL_CATEGORIES.has(key));
}

function lookup(messages: Messages, key: string): unknown {
    let current: unknown = messages;
    for (const segment of key.split('.')) {
        if (!isMessages(current)) {
            return undefined;
        }
        current = current[segment];
    }
    return current;
}

function pluralRules(locale: string): Intl.PluralRules {
    try {
        return new Intl.PluralRules(locale);
    } catch {
        return new Intl.PluralRules('en');
    }
}

/**
 * Fills placeholders from params. A placeholder without a matching param is
 * left as written, like Laravel's `__()`.
 */
export function interpolate(
    template: string,
    params: TranslationParams = {},
): string {
    return template.replace(
        PLACEHOLDER,
        (match, double?: string, single?: string, colon?: string) => {
            const name = double ?? single ?? colon ?? '';
            return Object.hasOwn(params, name) ? String(params[name]) : match;
        },
    );
}

/**
 * Translates dot-separated keys against the Laravel catalog shared by the
 * backend (already merged with the fallback locale). A missing key, a
 * non-string value or a missing plural form returns the key itself.
 */
export function createTranslator(
    locale: string,
    messages: Messages,
): TranslationFunction {
    const rules = pluralRules(locale);

    return (key, params, count) => {
        let value = lookup(messages, key);

        if (isPluralForms(value)) {
            if (count === undefined) {
                return key;
            }
            const category =
                count === 0 && typeof value.zero === 'string'
                    ? 'zero'
                    : rules.select(count);
            value = value[category] ?? value.other;
        }

        if (typeof value !== 'string' || value === '') {
            return key;
        }

        return interpolate(
            value,
            count === undefined ? params : { ...params, count },
        );
    };
}
