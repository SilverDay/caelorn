<?php

declare(strict_types=1);

namespace Caelorn\Domain\Engine\Phase;

use Caelorn\Domain\Engine\HostPhase;
use Caelorn\Domain\Engine\PhaseContext;

/**
 * Spec §5.5 phase 7: space combat: execute fleet battles, record immutable event logs and survivors. Stub pending M3.
 */
final class SpaceCombatPhase implements HostPhase
{
    public function name(): string
    {
        return 'space_combat';
    }

    public function handle(PhaseContext $context): PhaseContext
    {
        return $context;
    }
}
