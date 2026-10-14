<?php

declare(strict_types=1);

namespace Caelorn\Domain\Engine\Phase;

use Caelorn\Domain\Engine\HostPhase;
use Caelorn\Domain\Engine\PhaseContext;

/**
 * Spec §5.5 phase 8: orbital/ground combat: resolve planetary defence and invasion after orbital combat. Stub pending M3.
 */
final class GroundCombatPhase implements HostPhase
{
    public function name(): string
    {
        return 'ground_combat';
    }

    public function handle(PhaseContext $context): PhaseContext
    {
        return $context;
    }
}
