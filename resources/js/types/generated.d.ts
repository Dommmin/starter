declare namespace App {
    namespace Data {
        namespace Admin {
            namespace Articles {
                export type ArticleAbilitiesData = {
                    create: boolean;
                    publish: boolean;
                    delete: boolean;
                };
                export type ArticleEditorData = {
                    article: App.Data.Admin.Articles.ArticleFormData;
                    locales: App.Data.Content.ContentLocalesData;
                    can: App.Data.Admin.Articles.ArticleAbilitiesData;
                };
                export type ArticleFormData = {
                    id: number | null;
                    updatedAt: string | null;
                    coverMediaId: number | null;
                    translations: Record<
                        string,
                        App.Data.Admin.Articles.ArticleTranslationFormData
                    >;
                };
                export type ArticleIndexData = {
                    items: App.Data.Admin.Articles.ArticleListItemData[];
                    pagination: App.Data.Listing.ListPaginationData;
                    filters: App.Data.Admin.Articles.ArticleListFiltersData;
                    locales: App.Data.Content.ContentLocalesData;
                    can: App.Data.Admin.Articles.ArticleAbilitiesData;
                };
                export type ArticleListFiltersData = {
                    search: string;
                    sort: 'title' | 'updated_at';
                    direction: 'asc' | 'desc';
                    status: 'all' | 'draft' | 'published';
                    locale: string;
                };
                export type ArticleListItemData = {
                    id: number;
                    title: string;
                    slug: string;
                    status: App.Enums.PublicationStatus;
                    scheduled: boolean;
                    publishedAt: string | null;
                    locale: string;
                    locales: string[];
                    updatedAt: string | null;
                };
                export type ArticleTranslationFormData = {
                    title: string;
                    slug: string;
                    excerpt: string | null;
                    metaDescription: string | null;
                    body: { [key: string]: unknown } | null;
                    status: App.Enums.PublicationStatus;
                    publishedOn: string | null;
                };
            }
            namespace Audit {
                export type AuditLogIndexData = {
                    items: App.Data.Admin.Audit.AuditLogListItemData[];
                    pagination: App.Data.Listing.ListPaginationData;
                    filters: App.Data.Admin.Audit.AuditLogListFiltersData;
                    actions: App.Enums.AuditAction[];
                };
                export type AuditLogListFiltersData = {
                    search: string;
                    sort: 'created_at';
                    direction: 'asc' | 'desc';
                    action: 'all' | App.Enums.AuditAction;
                };
                export type AuditLogListItemData = {
                    id: number;
                    createdAt: string | null;
                    actorName: string | null;
                    action: App.Enums.AuditAction;
                    subjectType: string;
                    subjectId: number | null;
                    changedFields: string[];
                };
            }
            namespace Contact {
                export type ContactMessageAbilitiesData = {
                    retry: boolean;
                    delete: boolean;
                };
                export type ContactMessageDetailData = {
                    id: number;
                    name: string;
                    email: string;
                    message: string;
                    locale: string;
                    status: App.Enums.ContactMessageStatus;
                    attempts: number;
                    lastError: string | null;
                    sentAt: string | null;
                    createdAt: string | null;
                };
                export type ContactMessageIndexData = {
                    items: App.Data.Admin.Contact.ContactMessageListItemData[];
                    pagination: App.Data.Listing.ListPaginationData;
                    filters: App.Data.Admin.Contact.ContactMessageListFiltersData;
                };
                export type ContactMessageListFiltersData = {
                    search: string;
                    sort: 'created_at';
                    direction: 'asc' | 'desc';
                    status: 'all' | App.Enums.ContactMessageStatus;
                };
                export type ContactMessageListItemData = {
                    id: number;
                    name: string;
                    email: string;
                    locale: string;
                    status: App.Enums.ContactMessageStatus;
                    attempts: number;
                    createdAt: string | null;
                };
                export type ContactMessageShowData = {
                    contactMessage: App.Data.Admin.Contact.ContactMessageDetailData;
                    can: App.Data.Admin.Contact.ContactMessageAbilitiesData;
                };
            }
            namespace Faqs {
                export type FaqAbilitiesData = {
                    create: boolean;
                    delete: boolean;
                };
                export type FaqEditorData = {
                    faq: App.Data.Admin.Faqs.FaqFormData;
                    can: App.Data.Admin.Faqs.FaqAbilitiesData;
                };
                export type FaqFormData = {
                    id: number | null;
                    updatedAt: string | null;
                    question: string | null;
                    answer: string | null;
                    position: number | null;
                    published: boolean;
                };
                export type FaqIndexData = {
                    items: App.Data.Admin.Faqs.FaqListItemData[];
                    pagination: App.Data.Listing.ListPaginationData;
                    filters: App.Data.Admin.Faqs.FaqListFiltersData;
                    can: App.Data.Admin.Faqs.FaqAbilitiesData;
                };
                export type FaqListFiltersData = {
                    search: string;
                    sort: 'question' | 'position' | 'created_at';
                    direction: 'asc' | 'desc';
                    published: 'all' | 'yes' | 'no';
                };
                export type FaqListItemData = {
                    id: number;
                    question: string;
                    position: number | null;
                    published: boolean;
                    createdAt: string | null;
                    updatedAt: string | null;
                };
            }
            namespace Media {
                export type MediaAssetAbilitiesData = {
                    create: boolean;
                    update: boolean;
                    delete: boolean;
                };
                export type MediaAssetDetailData = {
                    id: number;
                    updatedAt: string | null;
                    originalName: string;
                    mime: string;
                    isImage: boolean;
                    size: number;
                    width: number | null;
                    height: number | null;
                    checksum: string;
                    status: App.Enums.MediaStatus;
                    scanError: string | null;
                    alt: string | null;
                    ownerName: string | null;
                    createdAt: string | null;
                    image: App.Data.Media.MediaImageData | null;
                    variants: App.Data.Admin.Media.MediaVariantData[];
                    downloadUrl: string | null;
                };
                export type MediaAssetEditorData = {
                    asset: App.Data.Admin.Media.MediaAssetDetailData;
                    can: App.Data.Admin.Media.MediaAssetAbilitiesData;
                };
                export type MediaAssetIndexData = {
                    items: App.Data.Admin.Media.MediaAssetListItemData[];
                    pagination: App.Data.Listing.ListPaginationData;
                    filters: App.Data.Admin.Media.MediaAssetListFiltersData;
                    can: App.Data.Admin.Media.MediaAssetAbilitiesData;
                    upload: App.Data.Admin.Media.MediaUploadRulesData;
                };
                export type MediaAssetListFiltersData = {
                    search: string;
                    sort: 'original_name' | 'size' | 'created_at';
                    direction: 'asc' | 'desc';
                    status: 'all' | 'quarantine' | 'clean' | 'rejected';
                    type: 'all' | 'image' | 'document';
                };
                export type MediaAssetListItemData = {
                    id: number;
                    originalName: string;
                    mime: string;
                    isImage: boolean;
                    size: number;
                    width: number | null;
                    height: number | null;
                    status: App.Enums.MediaStatus;
                    hasScanError: boolean;
                    alt: string | null;
                    thumbnailUrl: string | null;
                    createdAt: string | null;
                };
                export type MediaPickerData = {
                    items: App.Data.Admin.Media.MediaPickerItemData[];
                    pagination: App.Data.Listing.ListPaginationData;
                };
                export type MediaPickerItemData = {
                    id: number;
                    name: string;
                    alt: string | null;
                    thumbnailUrl: string;
                    width: number;
                    height: number;
                };
                export type MediaUploadRulesData = {
                    maxBytes: number;
                    extensions: string[];
                };
                export type MediaVariantData = {
                    format: string;
                    width: number;
                    height: number;
                    size: number;
                    url: string;
                };
            }
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
            namespace Users {
                export type UserAbilitiesData = {
                    delete: boolean;
                    changeRole: boolean;
                };
                export type UserEditorData = {
                    user: App.Data.Admin.Users.UserFormData;
                    can: App.Data.Admin.Users.UserAbilitiesData;
                };
                export type UserFormData = {
                    id: number | null;
                    updatedAt: string | null;
                    name: string;
                    email: string;
                    role: App.Enums.UserRole | null;
                    emailVerified: boolean;
                    twoFactorEnabled: boolean;
                };
            }
        }
        namespace Contact {
            export type ContactFormData = {
                token: string;
            };
        }
        namespace Content {
            export type ArticleSummaryData = {
                title: string;
                url: string;
                excerpt: string | null;
                publishedAt: string | null;
                cover: App.Data.Media.MediaImageData | null;
                coverAlt: string;
            };
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
            export type PublicArticleData = {
                title: string;
                excerpt: string | null;
                metaDescription: string | null;
                bodyHtml: string;
                locale: string;
                publishedAt: string | null;
                updatedAt: string | null;
                cover: App.Data.Media.MediaImageData | null;
                coverAlt: string;
                listUrl: string;
                alternates: Record<string, string>;
            };
            export type PublicArticleListData = {
                items: App.Data.Content.ArticleSummaryData[];
                pagination: App.Data.Listing.ListPaginationData;
                locale: string;
            };
            export type PublicPageData = {
                title: string;
                metaDescription: string | null;
                bodyHtml: string;
                locale: string;
                publishedAt: string | null;
                alternates: Record<string, string>;
            };
            export type WelcomePageData = {
                contactForm: App.Data.Contact.ContactFormData;
            };
        }
        namespace Errors {
            export type ErrorPageData = {
                status: 403 | 404 | 500 | 503;
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
        namespace Media {
            export type MediaImageData = {
                sources: App.Data.Media.MediaImageSourceData[];
                src: string;
                srcset: string;
                width: number;
                height: number;
            };
            export type MediaImageSourceData = {
                type: string;
                srcset: string;
            };
        }
        namespace Seo {
            export type SeoDefaultsData = {
                siteName: string;
                canonical: string;
                defaultImage: string | null;
                organization: App.Data.Seo.SeoOrganizationData;
            };
            export type SeoOrganizationData = {
                name: string;
                url: string;
                logo: string | null;
            };
        }
    }
    namespace Enums {
        export type AuditAction =
            | 'page.created'
            | 'page.updated'
            | 'page.published'
            | 'page.unpublished'
            | 'page.deleted'
            | 'article.created'
            | 'article.updated'
            | 'article.published'
            | 'article.unpublished'
            | 'article.deleted'
            | 'user.created'
            | 'user.updated'
            | 'user.role_changed'
            | 'user.deleted'
            | 'media.uploaded'
            | 'media.cleaned'
            | 'media.rejected'
            | 'media.updated'
            | 'media.deleted';
        export type ContactMessageStatus = 'pending' | 'sent' | 'failed';
        export type HealthCheckStatus = 'ok' | 'fail' | 'skipped';
        export type HealthStatus = 'ok' | 'degraded' | 'fail';
        export type MediaStatus = 'quarantine' | 'clean' | 'rejected';
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
