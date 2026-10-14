<?php

declare(strict_types=1);

namespace Caelorn\Domain\Engine\Phase;

use Caelorn\Domain\Engine\HostPhase;
use Caelorn\Domain\Engine\PhaseContext;

/**
 * Spec §5.5 phase 4: movement: simultaneous route calculation, fuel consumption, towing and waypoint progress. Stub pending M1.
 */
final class MovementPhase implements HostPhase
{
    public function name(): string
    {
        return 'movement';
    }

    public function handle(PhaseContext $context): PhaseContext
    {
        return $context;
    }
}
