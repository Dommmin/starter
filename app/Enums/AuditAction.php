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
    case UserRoleChanged = 'user.role_changed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
