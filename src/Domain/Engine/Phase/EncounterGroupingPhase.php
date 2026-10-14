<?php

declare(strict_types=1);

namespace Caelorn\Domain\Engine\Phase;

use Caelorn\Domain\Engine\HostPhase;
use Caelorn\Domain\Engine\PhaseContext;

/**
 * Spec §5.5 phase 6: intercept and encounters: group co-located hostile units and fix combat rosters. Stub pending M3.
 */
final class EncounterGroupingPhase implements HostPhase
{
    public function name(): string
    {
        return 'encounter_grouping';
    }

    public function handle(PhaseContext $context): PhaseContext
    {
        return $context;
    }
}
