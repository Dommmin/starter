import type { TranslationFunction } from '@/i18n/types';

const REQUIRED_CLASSES = ['lower', 'upper', 'digit', 'special'] as const;

type RequiredClass = (typeof REQUIRED_CLASSES)[number];

function isRequiredClass(value: string): value is RequiredClass {
    return (REQUIRED_CLASSES as readonly string[]).includes(value);
}

/**
 * Turns the server's `passwordrules` string (from
 * `Password::defaults()->toPasswordRulesString()`, e.g.
 * `minlength: 8; maxlength: 128; required: lower; required: digit;`) into a
 * translated hint for the field description. The rules stay defined only on
 * the backend; unknown properties are skipped. Returns `undefined` when the
 * string carries nothing to describe.
 */
export function describePasswordRules(
    passwordRules: string | undefined,
    t: TranslationFunction,
): string | undefined {
    if (!passwordRules) {
        return undefined;
    }

    const parts: string[] = [];

    for (const declaration of passwordRules.split(';')) {
        const [property, rawValue] = declaration.split(':');
        const name = property?.trim();
        const value = rawValue?.trim() ?? '';

        if (name === 'minlength' && /^\d+$/.test(value)) {
            parts.push(
                t('auth.passwordRules.minLength', { length: Number(value) }),
            );
        } else if (name === 'maxlength' && /^\d+$/.test(value)) {
            parts.push(
                t('auth.passwordRules.maxLength', { length: Number(value) }),
            );
        } else if (name === 'required' && isRequiredClass(value)) {
            parts.push(t(`auth.passwordRules.${value}`));
        }
    }

    if (parts.length === 0) {
        return undefined;
    }

    return t('auth.passwordRules.hint', { rules: parts.join(', ') });
}
