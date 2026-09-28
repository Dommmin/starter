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
    case UserRoleChanged = 'user.role_changed';
    case MediaUploaded = 'media.uploaded';
    case MediaCleaned = 'media.cleaned';
    case MediaRejected = 'media.rejected';
    case MediaUpdated = 'media.updated';
    case MediaDeleted = 'media.deleted';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
