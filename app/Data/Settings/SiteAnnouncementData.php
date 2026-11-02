<?php

namespace App\Data\Settings;

use App\Enums\AnnouncementTone;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Announcement bar shown above the public header, resolved for the current
 * locale and time. `message` is plain text. `linkUrl` (a relative path or an
 * http(s) URL) and `linkLabel` are both set or both null; the label comes
 * from the same locale as the message.
 */
#[TypeScript]
class SiteAnnouncementData extends Data
{
    public function __construct(
        public string $message,
        public AnnouncementTone $tone,
        public ?string $linkUrl,
        public ?string $linkLabel,
    ) {}
}
