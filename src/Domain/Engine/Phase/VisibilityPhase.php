<?php

declare(strict_types=1);

namespace Caelorn\Domain\Engine\Phase;

use Caelorn\Domain\Engine\HostPhase;
use Caelorn\Domain\Engine\PhaseContext;

/**
 * Spec §5.5 phase 13: visibility: generate current observations and update per-slot intelligence memory. Stub pending M2.
 */
final class VisibilityPhase implements HostPhase
{
    public function name(): string
    {
        return 'visibility';
    }

    public function handle(PhaseContext $context): PhaseContext
    {
        return $context;
    }
}
