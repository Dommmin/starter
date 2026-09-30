<?php

namespace App\Enums;

/**
 * Closed set of audited administrative operations.
 */
enum AuditAction: string
{
    case PageCreated = 'page.created';
    case PageUpdated = 'page.updated';
    case PagePublished = 'page.published';
    case PageUnpublished = 'page.unpublished';
    case PageDeleted = 'page.deleted';
    case ArticleCreated = 'article.created';
    case ArticleUpdated = 'article.updated';
    case ArticlePublished = 'article.published';
    case ArticleUnpublished = 'article.unpublished';
    case ArticleDeleted = 'article.deleted';
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserRoleChanged = 'user.role_changed';
    case UserDeleted = 'user.deleted';
    case MediaUploaded = 'media.uploaded';
    case MediaCleaned = 'media.cleaned';
    case MediaRejected = 'media.rejected';
    case MediaUpdated = 'media.updated';
    case MediaDeleted = 'media.deleted';
    case ResourceExported = 'resource.exported';
    case SiteSettingsUpdated = 'site_settings.updated';
    case NavigationItemCreated = 'navigation.item_created';
    case NavigationItemUpdated = 'navigation.item_updated';
    case NavigationItemDeleted = 'navigation.item_deleted';
    case NavigationReordered = 'navigation.reordered';
    case HomeSectionUpdated = 'home_section.updated';
    case HomeSectionToggled = 'home_section.toggled';
    case HomeSectionsReordered = 'home_section.reordered';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
