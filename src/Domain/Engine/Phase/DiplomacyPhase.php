<?php

declare(strict_types=1);

namespace Caelorn\Domain\Engine\Phase;

use Caelorn\Domain\Engine\HostPhase;
use Caelorn\Domain\Engine\PhaseContext;

/**
 * Spec §5.5 phase 2: diplomacy: resolve declarations, mutual treaty acceptance and visibility grants/revocations. Stub pending M4.
 */
final class DiplomacyPhase implements HostPhase
{
    public function name(): string
    {
        return 'diplomacy';
    }

    public function handle(PhaseContext $context): PhaseContext
    {
        return $context;
    }
}
