/** Latin letters that Unicode NFD does not decompose into base + diacritic. */
const TRANSLITERATIONS: Record<string, string> = {
    ł: 'l',
    đ: 'd',
    ð: 'd',
    ø: 'o',
    æ: 'ae',
    œ: 'oe',
    ß: 'ss',
    þ: 'th',
};

/** Same limit as the `slug` validation rule (`max:200`). */
const MAX_SLUG_LENGTH = 200;

/**
 * Suggest a kebab-case slug from a title: lowercase ASCII letters and
 * digits separated by single hyphens, diacritics removed (`Zażółć gęślą` →
 * `zazolc-gesla`). Matches the backend `PageTranslationRules::SLUG_PATTERN`
 * or returns an empty string; the server stays the source of validation.
 */
export function slugify(value: string): string {
    return value
        .toLowerCase()
        .normalize('NFD')
        .replace(/\p{M}+/gu, '')
        .replace(/[łđðøæœßþ]/g, (letter) => TRANSLITERATIONS[letter] ?? '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, MAX_SLUG_LENGTH)
        .replace(/-+$/, '');
}
