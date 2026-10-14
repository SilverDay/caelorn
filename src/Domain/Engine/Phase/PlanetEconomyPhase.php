<?php

declare(strict_types=1);

namespace Caelorn\Domain\Engine\Phase;

use Caelorn\Domain\Engine\HostPhase;
use Caelorn\Domain\Engine\PhaseContext;

/**
 * Spec §5.5 phase 10: planet economy: allocate phase 10-11 drafts via the fallback queue, population/happiness/taxes/mining/supplies/structures. Stub pending M1.
 */
final class PlanetEconomyPhase implements HostPhase
{
    public function name(): string
    {
        return 'planet_economy';
    }

    public function handle(PhaseContext $context): PhaseContext
    {
        return $context;
    }
}
