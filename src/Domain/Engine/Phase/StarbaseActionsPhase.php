<?php

declare(strict_types=1);

namespace Caelorn\Domain\Engine\Phase;

use Caelorn\Domain\Engine\HostPhase;
use Caelorn\Domain\Engine\PhaseContext;

/**
 * Spec §5.5 phase 11: starbase actions: upgrades, repairs, recycling, ammunition/fighter production, at most one ship per starbase. Stub pending M1.
 */
final class StarbaseActionsPhase implements HostPhase
{
    public function name(): string
    {
        return 'starbase_actions';
    }

    public function handle(PhaseContext $context): PhaseContext
    {
        return $context;
    }
}
