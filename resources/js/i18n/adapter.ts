const PLURAL_KEYS = new Set(['zero', 'one', 'two', 'few', 'many', 'other']);

function isPluralObject(obj: unknown): boolean {
    if (!obj || typeof obj !== 'object' || Array.isArray(obj)) {
        return false;
    }
    const keys = Object.keys(obj as Record<string, unknown>);
    return keys.length > 0 && keys.every((k) => PLURAL_KEYS.has(k));
}

export function convertPlaceholders(template: string): string {
    return template
        .replace(/:([a-zA-Z_]+)/g, '{{$1}}')
        .replace(/\{([a-zA-Z_]+)\}/g, '{{$1}}');
}

/**
 * Transforms Laravel message catalog structures into standard i18next format:
 * - Converts :param and {param} to {{param}}
 * - Expands plural objects { one: '...', few: '...', other: '...' } to key_one, key_few, key_other
 */
export function transformLaravelMessagesToI18next(
    messages: Record<string, unknown>,
): Record<string, unknown> {
    const result: Record<string, unknown> = {};

    function process(
        target: Record<string, unknown>,
        source: Record<string, unknown>,
    ) {
        for (const [key, value] of Object.entries(source)) {
            if (isPluralObject(value)) {
                for (const [cat, template] of Object.entries(
                    value as Record<string, unknown>,
                )) {
                    const strVal =
                        typeof template === 'string'
                            ? convertPlaceholders(template)
                            : template;
                    target[`${key}_${cat}`] = strVal;
                }
            } else if (
                value !== null &&
                typeof value === 'object' &&
                !Array.isArray(value)
            ) {
                target[key] = {};
                process(
                    target[key] as Record<string, unknown>,
                    value as Record<string, unknown>,
                );
            } else if (typeof value === 'string') {
                target[key] = convertPlaceholders(value);
            } else {
                target[key] = value;
            }
        }
    }

    process(result, messages);
    return result;
}
