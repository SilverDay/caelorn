<?php

declare(strict_types=1);

namespace Caelorn\Domain\Engine\Phase;

use Caelorn\Domain\Engine\HostPhase;
use Caelorn\Domain\Engine\PhaseContext;

/**
 * Spec §5.5 phase 3: pre-movement: cloak activation, same-location loading/refuelling/transfers, tow attachment. Stub pending M1.
 */
final class PreMovementPhase implements HostPhase
{
    public function name(): string
    {
        return 'pre_movement';
    }

    public function handle(PhaseContext $context): PhaseContext
    {
        return $context;
    }
}
