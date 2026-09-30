<?php

namespace App\Contracts\DemoContent;

use App\Models\User;
use Database\Seeders\DemoContent;
use Illuminate\Database\Eloquent\Model;

/**
 * One type of seeded sample content removable by
 * `app:init-project --remove-demo`, registered in {@see DemoContent::PROVIDERS}.
 * Methods receive only records returned by {@see self::records()}.
 */
interface DemoContentProvider
{
    /**
     * Plural name of the content type shown in the command output.
     */
    public function label(): string;

    /**
     * Seeded records that still exist.
     *
     * @return list<Model>
     */
    public function records(): array;

    /**
     * Whether the record was edited after seeding (then it is kept).
     */
    public function isModifiedSinceSeed(Model $record): bool;

    /**
     * Short identification of the record for the command output.
     */
    public function describe(Model $record): string;

    /**
     * Whether {@see self::delete()} resets the record to neutral content
     * instead of deleting it (e.g. home sections, one per type and locale).
     */
    public function resetsInsteadOfDeleting(): bool;

    /**
     * Delete (or reset, see above) the record through its audited domain action.
     */
    public function delete(Model $record, User $actor): void;
}
