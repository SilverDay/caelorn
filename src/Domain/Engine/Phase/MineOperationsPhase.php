<?php

declare(strict_types=1);

namespace Caelorn\Domain\Engine\Phase;

use Caelorn\Domain\Engine\HostPhase;
use Caelorn\Domain\Engine\PhaseContext;

/**
 * Spec §5.5 phase 12: mine operations: lay/sweep/decay, effective for movement hazards from the next turn. Stub pending M3.
 */
final class MineOperationsPhase implements HostPhase
{
    public function name(): string
    {
        return 'mine_operations';
    }

    public function handle(PhaseContext $context): PhaseContext
    {
        return $context;
    }
}
