import type { LucideIcon } from 'lucide-react';
import {
    CircleHelp,
    FileText,
    Images,
    Newspaper,
    Tags,
    Users,
} from 'lucide-react';
import { useMemo } from 'react';
import type { CommandPaletteSearch } from '@/design-system/primitives';
import { useTranslation } from '@/i18n';
import { search } from '@/routes/admin';

type ResultType = App.Data.Admin.Search.AdminSearchResultData['type'];

const icons: Record<ResultType, LucideIcon> = {
    pages: FileText,
    articles: Newspaper,
    tags: Tags,
    faqs: CircleHelp,
    media: Images,
    users: Users,
};

/**
 * Record search of the admin command palette: `admin.search` (JSON,
 * same-origin session) returns only the modules the user may list.
 */
export function useAdminSearch(): CommandPaletteSearch {
    const { t } = useTranslation();

    return useMemo<CommandPaletteSearch>(
        () => ({
            load: async (query, signal) => {
                const response = await fetch(
                    search.url({ query: { q: query } }),
                    {
                        signal,
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    },
                );

                if (!response.ok) {
                    throw new Error(`Admin search failed: ${response.status}`);
                }

                const data =
                    (await response.json()) as App.Data.Admin.Search.AdminSearchData;

                return data.results.map((result) => ({
                    id: `${result.type}-${result.id}`,
                    label: result.label,
                    description: result.description,
                    group: t(`admin.command.groups.${result.type}`),
                    href: result.url,
                    icon: icons[result.type],
                }));
            },
            labels: {
                loading: t('admin.command.searching'),
                error: t('admin.command.searchError'),
            },
        }),
        [t],
    );
}
