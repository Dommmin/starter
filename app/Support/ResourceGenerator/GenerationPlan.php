<?php

namespace App\Support\ResourceGenerator;

/**
 * Everything a generation would change, plus the reasons it must not run.
 */
final readonly class GenerationPlan
{
    /**
     * @param  list<PlannedChange>  $changes
     * @param  list<string>  $conflicts
     */
    public function __construct(
        public array $changes,
        public array $conflicts,
    ) {}
}
