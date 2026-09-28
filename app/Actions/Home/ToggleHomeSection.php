<?php

namespace App\Actions\Home;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\HomeSection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Show or hide a home section, auditing `home_section.toggled` in the same
 * transaction. Setting the current value again changes nothing.
 */
class ToggleHomeSection
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(HomeSection $section, User $actor, bool $enabled): HomeSection
    {
        return DB::transaction(function () use ($section, $actor, $enabled): HomeSection {
            $locked = HomeSection::query()->whereKey($section->id)->lockForUpdate()->firstOrFail();

            if ($locked->enabled === $enabled) {
                return $locked;
            }

            $locked->forceFill(['enabled' => $enabled])->save();

            $this->audit->handle(AuditAction::HomeSectionToggled, $locked, $actor, [
                'enabled' => RecordAuditEvent::change(! $enabled, $enabled),
            ]);

            return $locked;
        });
    }
}
