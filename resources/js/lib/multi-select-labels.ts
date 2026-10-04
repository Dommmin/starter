import type { MultiSelectFieldLabels } from '@/design-system/primitives';
import type { TranslationFunction } from '@/i18n';

/** Shared labels of `MultiSelectField` (`common.multiSelect.*`). */
export function multiSelectLabels(
    t: TranslationFunction,
): MultiSelectFieldLabels {
    return {
        noResults: t('multiSelect.noResults'),
        remove: (label) => t('multiSelect.remove', { label }),
        selected: (count) => t('multiSelect.selected', {}, count),
    };
}
