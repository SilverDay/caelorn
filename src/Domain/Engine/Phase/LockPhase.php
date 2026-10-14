<?php

declare(strict_types=1);

namespace Caelorn\Domain\Engine\Phase;

use Caelorn\Domain\Engine\HostPhase;
use Caelorn\Domain\Engine\PhaseContext;

/**
 * Spec §5.5 phase 1: acquire/lock. Stub pending M2 (lease/fencing, §5.5.1).
 */
final class LockPhase implements HostPhase
{
    public function name(): string
    {
        return 'lock';
    }

    public function handle(PhaseContext $context): PhaseContext
    {
        return $context;
    }
}
