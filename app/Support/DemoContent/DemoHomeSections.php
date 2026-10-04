<?php

namespace App\Support\DemoContent;

use App\Actions\Home\EnsureHomeSections;
use App\Actions\Home\ToggleHomeSection;
use App\Actions\Home\UpdateHomeSection;
use App\Contracts\DemoContent\DemoContentProvider;
use App\Models\HomeSection;
use App\Models\User;
use Database\Seeders\HomeSectionSeeder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Sample home page sections of {@see HomeSectionSeeder}. Every locale keeps
 * exactly one section per type, so a sample section is never deleted: it is
 * reset to the neutral {@see EnsureHomeSections::placeholder()} content and
 * hidden, through the audited update and toggle actions. A section already
 * holding the hidden placeholder is not listed; one whose content or
 * visibility differs from the seeded state counts as edited.
 */
class DemoHomeSections implements DemoContentProvider
{
    public function __construct(
        private readonly UpdateHomeSection $updateHomeSection,
        private readonly ToggleHomeSection $toggleHomeSection,
    ) {}

    public function label(): string
    {
        return 'home sections';
    }

    public function resetsInsteadOfDeleting(): bool
    {
        return true;
    }

    public function records(): array
    {
        return array_values(HomeSection::query()
            ->orderBy('locale')
            ->orderBy('position')
            ->get()
            ->reject(fn (HomeSection $section): bool => ! $section->enabled
                && $section->content->toArray() === EnsureHomeSections::placeholder($section->type, $section->locale)->toArray())
            ->all());
    }

    public function isModifiedSinceSeed(Model $record): bool
    {
        $section = $this->section($record);

        return ! HomeSectionSeeder::isDemo($section)
            || $section->enabled !== HomeSectionSeeder::startsEnabled($section->type, $section->locale);
    }

    public function describe(Model $record): string
    {
        $section = $this->section($record);

        return $section->locale.'/'.$section->type->value;
    }

    public function delete(Model $record, User $actor): void
    {
        $section = $this->section($record);
        $placeholder = EnsureHomeSections::placeholder($section->type, $section->locale);

        if ($section->content->toArray() !== $placeholder->toArray()) {
            $section = $this->updateHomeSection->handle(
                $section,
                $actor,
                $placeholder,
                (string) $section->updated_at?->toIso8601String(),
            );
        }

        $this->toggleHomeSection->handle($section, $actor, false);
    }

    private function section(Model $record): HomeSection
    {
        if (! $record instanceof HomeSection) {
            throw new InvalidArgumentException('Expected a home section.');
        }

        return $record;
    }
}
