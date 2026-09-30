<?php

namespace App\Enums;

/**
 * Allowlist of social networks linkable from the site settings. Values are
 * the keys of `site_settings.social_links`; the order of the cases is the
 * display order.
 */
enum SocialNetwork: string
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case LinkedIn = 'linkedin';
    case X = 'x';
    case YouTube = 'youtube';
    case TikTok = 'tiktok';
    case GitHub = 'github';

    /**
     * Brand name shown as the link label (not translated).
     */
    public function label(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::LinkedIn => 'LinkedIn',
            self::X => 'X',
            self::YouTube => 'YouTube',
            self::TikTok => 'TikTok',
            self::GitHub => 'GitHub',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
