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
                    locales: App.Data.Content.ContentLocalesData;
                };
                export type FaqFormData = {
                    id: number | null;
                    updatedAt: string | null;
                    question: string | null;
                    answer: string | null;
                    locale: string | null;
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
            namespace HomeSections {
                export type HomePageOptionData = {
                    id: number;
                    title: string;
                };
                export type HomeSectionAbilitiesData = {
                    update: boolean;
                    reorder: boolean;
                };
                export type HomeSectionEditorData = {
                    section: App.Data.Admin.HomeSections.HomeSectionFormData;
                    locale: App.Data.Content.ContentLocaleData;
                    linkTargets: App.Enums.HomeLinkTarget[];
                    pages: App.Data.Admin.HomeSections.HomePageOptionData[];
                    can: App.Data.Admin.HomeSections.HomeSectionAbilitiesData;
                };
                export type HomeSectionFormData = {
                    id: number;
                    locale: string;
                    enabled: boolean;
                    updatedAt: string | null;
                } & (
                    | { type: 'hero'; content: App.Data.Home.HeroContentData }
                    | {
                          type: 'features';
                          content: App.Data.Home.FeaturesContentData;
                      }
                    | { type: 'faq'; content: App.Data.Home.FaqContentData }
                    | {
                          type: 'testimonials';
                          content: App.Data.Home.TestimonialsContentData;
                      }
                    | {
                          type: 'latest_articles';
                          content: App.Data.Home.LatestArticlesContentData;
                      }
                    | {
                          type: 'contact';
                          content: App.Data.Home.ContactContentData;
                      }
                    | { type: 'cta'; content: App.Data.Home.CtaContentData }
                );
                export type HomeSectionIndexData = {
                    items: App.Data.Admin.HomeSections.HomeSectionListItemData[];
                    locale: string;
                    locales: App.Data.Content.ContentLocalesData;
                    can: App.Data.Admin.HomeSections.HomeSectionAbilitiesData;
                };
                export type HomeSectionListItemData = {
                    id: number;
                    type: App.Enums.HomeSectionType;
                    anchor: App.Enums.HomeSectionAnchor;
                    enabled: boolean;
                    position: number;
                    title: string | null;
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
            namespace Navigation {
                export type MenuAbilitiesData = {
                    create: boolean;
                    reorder: boolean;
                    delete: boolean;
                };
                export type MenuIndexData = {
                    location: App.Enums.MenuLocation;
                    locale: string;
                    locales: App.Data.Content.ContentLocalesData;
                    items: App.Data.Admin.Navigation.MenuTreeItemData[];
                    can: App.Data.Admin.Navigation.MenuAbilitiesData;
                };
                export type MenuItemEditorData = {
                    item: App.Data.Admin.Navigation.MenuItemFormData;
                    locales: App.Data.Content.ContentLocalesData;
                    pages: App.Data.Admin.Navigation.MenuTargetOptionData[];
                    articles: App.Data.Admin.Navigation.MenuTargetOptionData[];
                    parents: App.Data.Admin.Navigation.MenuParentOptionData[];
                    can: App.Data.Admin.Navigation.MenuAbilitiesData;
                };
                export type MenuItemFormData = {
                    id: number | null;
                    updatedAt: string | null;
                    location: App.Enums.MenuLocation;
                    locale: string;
                    parentId: number | null;
                    type: App.Enums.MenuItemType;
                    pageId: number | null;
                    articleId: number | null;
                    anchor: string | null;
                    url: string | null;
                    label: string | null;
                    openInNewTab: boolean;
                    targetMissing: boolean;
                    draftTarget: boolean;
                    hasChildren: boolean;
                };
                export type MenuParentOptionData = {
                    id: number;
                    label: string | null;
                    type: App.Enums.MenuItemType;
                };
                export type MenuTargetOptionData = {
                    id: number;
                    title: string;
                    draft: boolean;
                };
                export type MenuTreeItemData = {
                    id: number;
                    type: App.Enums.MenuItemType;
                    label: string | null;
                    anchor: string | null;
                    url: string | null;
                    openInNewTab: boolean;
                    targetMissing: boolean;
                    draftTarget: boolean;
                    children: App.Data.Admin.Navigation.MenuTreeItemData[];
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
            namespace Settings {
                export type SiteSettingTranslationFormData = {
                    tagline: string | null;
                    footerText: string | null;
                    seoTitle: string | null;
                    seoDescription: string | null;
                };
                export type SiteSettingsEditorData = {
                    settings: App.Data.Admin.Settings.SiteSettingsFormData;
                    locales: App.Data.Content.ContentLocalesData;
                    socialNetworks: App.Data.Admin.Settings.SocialNetworkOptionData[];
                };
                export type SiteSettingsFormData = {
                    updatedAt: string | null;
                    siteName: string;
                    logoMediaId: number | null;
                    ogImageMediaId: number | null;
                    contactEmail: string | null;
                    contactPhone: string | null;
                    addressLine: string | null;
                    postalCode: string | null;
                    city: string | null;
                    countryCode: string | null;
                    contactRecipientEmail: string | null;
                    socialLinks: {
                        [network in App.Enums.SocialNetwork]: string;
                    };
                    translations: Record<
                        string,
                        App.Data.Admin.Settings.SiteSettingTranslationFormData
                    >;
                };
                export type SocialNetworkOptionData = {
                    network: App.Enums.SocialNetwork;
                    label: string;
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
                sections: App.Data.Home.HomeSectionData[];
            };
        }
        namespace Errors {
            export type ErrorPageData = {
                status: 403 | 404 | 500 | 503;
            };
        }
        namespace Home {
            export type ContactContentData = {
                title: string;
                description: string | null;
            };
            export type CtaContentData = {
                title: string;
                description: string | null;
                primaryAction: App.Data.Home.HomeActionData | null;
                secondaryAction: App.Data.Home.HomeActionData | null;
            };
            export type FaqContentData = {
                title: string | null;
                description: string | null;
                limit: number | null;
            };
            export type FeatureItemData = {
                title: string;
                description: string;
                icon: App.Enums.HomeIcon | null;
            };
            export type FeaturesContentData = {
                items: App.Data.Home.FeatureItemData[];
                title: string | null;
                description: string | null;
            };
            export type HeroContentData = {
                title: string;
                eyebrow: string | null;
                description: string | null;
                primaryAction: App.Data.Home.HomeActionData | null;
                secondaryAction: App.Data.Home.HomeActionData | null;
            };
            export type HomeActionData = {
                label: string;
                target: App.Enums.HomeLinkTarget;
                pageId: number | null;
            };
            export type HomeCtaData = {
                title: string;
                description: string | null;
                primaryAction: App.Data.Home.HomeLinkData | null;
                secondaryAction: App.Data.Home.HomeLinkData | null;
            };
            export type HomeFaqData = {
                title: string | null;
                description: string | null;
                items: App.Data.Home.HomeFaqItemData[];
            };
            export type HomeFaqItemData = {
                id: number;
                question: string;
                answer: string;
            };
            export type HomeHeroData = {
                eyebrow: string | null;
                title: string;
                description: string | null;
                primaryAction: App.Data.Home.HomeLinkData | null;
                secondaryAction: App.Data.Home.HomeLinkData | null;
            };
            export type HomeLatestArticlesData = {
                title: string | null;
                items: App.Data.Content.ArticleSummaryData[];
                listUrl: string;
            };
            export type HomeLinkData = {
                label: string;
                url: string;
            };
            export type HomeSectionData = {
                id: number;
                anchor: App.Enums.HomeSectionAnchor;
            } & (
                | { type: 'hero'; content: App.Data.Home.HomeHeroData }
                | {
                      type: 'features';
                      content: App.Data.Home.FeaturesContentData;
                  }
                | { type: 'faq'; content: App.Data.Home.HomeFaqData }
                | {
                      type: 'testimonials';
                      content: App.Data.Home.TestimonialsContentData;
                  }
                | {
                      type: 'latest_articles';
                      content: App.Data.Home.HomeLatestArticlesData;
                  }
                | { type: 'contact'; content: App.Data.Home.ContactContentData }
                | { type: 'cta'; content: App.Data.Home.HomeCtaData }
            );
            export type LatestArticlesContentData = {
                limit: number;
                title: string | null;
            };
            export type TestimonialItemData = {
                author: string;
                quote: string;
                role: string | null;
            };
            export type TestimonialsContentData = {
                items: App.Data.Home.TestimonialItemData[];
                title: string | null;
            };
        }
        namespace Listing {
            export type ListPaginationData = {
                page: number;
                totalPages: number;
                total: number;
                perPage: number;
            };
            export type RecordOptionData = {
                id: number;
                label: string;
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
        namespace Navigation {
            export type MenuItemInputData = {
                parentId: number | null;
                type: App.Enums.MenuItemType;
                pageId: number | null;
                articleId: number | null;
                anchor: string | null;
                url: string | null;
                label: string | null;
                openInNewTab: boolean;
            };
            export type NavigationData = {
                header: App.Data.Navigation.NavigationItemData[];
                footer: App.Data.Navigation.NavigationItemData[];
            };
            export type NavigationItemData = {
                id: number;
                label: string;
                href?: string;
                kind: 'internal' | 'anchor' | 'external' | 'group';
                newTab: boolean;
                children: App.Data.Navigation.NavigationItemData[];
            };
        }
        namespace Seo {
            export type SeoDefaultsData = {
                siteName: string;
                canonical: string;
                defaultImage: string | null;
                defaultTitle: string;
                defaultDescription: string | null;
                organization: App.Data.Seo.SeoOrganizationData;
            };
            export type SeoOrganizationData = {
                name: string;
                url: string;
                logo: string | null;
            };
        }
        namespace Settings {
            export type SiteContactData = {
                email: string | null;
                phone: string | null;
                address: string | null;
            };
            export type SiteSettingsData = {
                name: string;
                isCustomized: boolean;
                logo: App.Data.Media.MediaImageData | null;
                tagline: string | null;
                footerText: string | null;
                contact: App.Data.Settings.SiteContactData;
                social: App.Data.Settings.SiteSocialLinkData[];
            };
            export type SiteSocialLinkData = {
                network: App.Enums.SocialNetwork;
                label: string;
                url: string;
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
            | 'media.deleted'
            | 'resource.exported'
            | 'site_settings.updated'
            | 'navigation.item_created'
            | 'navigation.item_updated'
            | 'navigation.item_deleted'
            | 'navigation.reordered'
            | 'home_section.updated'
            | 'home_section.toggled'
            | 'home_section.reordered';
        export type ContactMessageStatus = 'pending' | 'sent' | 'failed';
        export type HealthCheckStatus = 'ok' | 'fail' | 'skipped';
        export type HealthStatus = 'ok' | 'degraded' | 'fail';
        export type HomeIcon =
            | 'palette'
            | 'lock'
            | 'zap'
            | 'accessibility'
            | 'shield-check'
            | 'rocket'
            | 'sparkles'
            | 'globe';
        export type HomeLinkTarget =
            | 'contact'
            | 'articles'
            | 'login'
            | 'register'
            | 'page';
        export type HomeSectionAnchor =
            | 'hero'
            | 'features'
            | 'faq'
            | 'testimonials'
            | 'latest-articles'
            | 'contact'
            | 'cta';
        export type HomeSectionType =
            | 'hero'
            | 'features'
            | 'faq'
            | 'testimonials'
            | 'latest_articles'
            | 'contact'
            | 'cta';
        export type MediaStatus = 'quarantine' | 'clean' | 'rejected';
        export type MenuItemType =
            | 'page'
            | 'article'
            | 'article_index'
            | 'anchor'
            | 'external'
            | 'group';
        export type MenuLocation = 'header' | 'footer';
        export type PublicationStatus = 'draft' | 'published';
        export type SocialNetwork =
            | 'facebook'
            | 'instagram'
            | 'linkedin'
            | 'x'
            | 'youtube'
            | 'tiktok'
            | 'github';
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
