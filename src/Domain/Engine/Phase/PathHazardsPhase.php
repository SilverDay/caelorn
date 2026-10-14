<?php

declare(strict_types=1);

namespace Caelorn\Domain\Engine\Phase;

use Caelorn\Domain\Engine\HostPhase;
use Caelorn\Domain\Engine\PhaseContext;

/**
 * Spec §5.5 phase 5: path hazards: minefield encounters against minefields active at turn start. Stub pending M3.
 */
final class PathHazardsPhase implements HostPhase
{
    public function name(): string
    {
        return 'path_hazards';
    }

    public function handle(PhaseContext $context): PhaseContext
    {
        return $context;
    }
}
