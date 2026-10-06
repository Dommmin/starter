import type { DataTableSortLabels } from '@/design-system/primitives';
import type { TranslationFunction } from '@/i18n';

/** Shared labels of the card-layout sort select (`common.table.*`). */
export function tableSortLabels(t: TranslationFunction): DataTableSortLabels {
    return {
        label: t('table.sortBy'),
        option: (column, direction) =>
            t(
                direction === 'asc'
                    ? 'table.sortAscending'
                    : 'table.sortDescending',
                { column },
            ),
    };
}
