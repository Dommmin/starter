declare namespace App {
    namespace Data {
        namespace Admin {
            namespace Pages {
                export type PageAbilitiesData = {
                    create: boolean;
                    publish: boolean;
                    delete: boolean;
                };
                export type PageEditorData = {
                    page: App.Data.Admin.Pages.PageFormData;
                    locales: App.Data.Content.ContentLocalesData;
                    can: App.Data.Admin.Pages.PageAbilitiesData;
                };
                export type PageFormData = {
                    id: number | null;
                    updatedAt: string | null;
                    translations: Record<
                        string,
                        App.Data.Admin.Pages.PageTranslationFormData
                    >;
                };
                export type PageIndexData = {
                    items: App.Data.Admin.Pages.PageListItemData[];
                    pagination: App.Data.Listing.ListPaginationData;
                    filters: App.Data.Admin.Pages.PageListFiltersData;
                    locales: App.Data.Content.ContentLocalesData;
                    can: App.Data.Admin.Pages.PageAbilitiesData;
                };
                export type PageListFiltersData = {
                    search: string;
                    sort: 'title' | 'updated_at';
                    direction: 'asc' | 'desc';
                    status: 'all' | 'draft' | 'published';
                    locale: string;
                };
                export type PageListItemData = {
                    id: number;
                    title: string;
                    slug: string;
                    status: App.Enums.PublicationStatus;
                    locale: string;
                    locales: string[];
                    updatedAt: string | null;
                };
                export type PageTranslationFormData = {
                    title: string;
                    slug: string;
                    metaDescription: string | null;
                    body: { [key: string]: unknown } | null;
                    status: App.Enums.PublicationStatus;
                    publishedAt: string | null;
                };
            }
        }
        namespace Content {
            export type ContentLocaleData = {
                code: string;
                name: string;
                native: string;
                dir: 'ltr' | 'rtl';
            };
            export type ContentLocalesData = {
                available: App.Data.Content.ContentLocaleData[];
                default: string;
            };
            export type PublicPageData = {
                title: string;
                metaDescription: string | null;
                bodyHtml: string;
                locale: string;
                publishedAt: string | null;
                alternates: Record<string, string>;
            };
        }
        namespace Listing {
            export type ListPaginationData = {
                page: number;
                totalPages: number;
                total: number;
                perPage: number;
            };
        }
    }
    namespace Enums {
        export type PublicationStatus = 'draft' | 'published';
        export type UserRole = 'admin' | 'editor';
    }
}
declare namespace Illuminate {
    export type CursorPaginator<TKey, TValue> = {
        data: TKey extends string ? Record<TKey, TValue> : TValue[];
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
        meta: {
            path: string;
            per_page: number;
            next_cursor: string | null;
            next_page_url: string | null;
            prev_cursor: string | null;
            prev_page_url: string | null;
        };
    };
    export type CursorPaginatorInterface<TKey, TValue> =
        Illuminate.CursorPaginator<TKey, TValue>;
    export type LengthAwarePaginator<TKey, TValue> = {
        data: TKey extends string ? Record<TKey, TValue> : TValue[];
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
        meta: {
            total: number;
            current_page: number;
            first_page_url: string;
            from: number | null;
            last_page: number;
            last_page_url: string;
            next_page_url: string | null;
            path: string;
            per_page: number;
            prev_page_url: string | null;
            to: number | null;
        };
    };
    export type LengthAwarePaginatorInterface<TKey, TValue> =
        Illuminate.LengthAwarePaginator<TKey, TValue>;
}
declare namespace Spatie {
    namespace LaravelData {
        export type CursorPaginatedDataCollection<TKey, TValue> =
            Illuminate.CursorPaginator<TKey, TValue>;
        export type PaginatedDataCollection<TKey, TValue> =
            Illuminate.LengthAwarePaginator<TKey, TValue>;
    }
}
