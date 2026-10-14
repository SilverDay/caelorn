<?php

declare(strict_types=1);

namespace Caelorn\Domain\Engine\Phase;

use Caelorn\Domain\Engine\HostPhase;
use Caelorn\Domain\Engine\PhaseContext;

/**
 * Spec §5.5 phase 9: post-combat actions: colonisation, capture, salvage and unloading. Stub pending M1.
 */
final class PostCombatPhase implements HostPhase
{
    public function name(): string
    {
        return 'post_combat';
    }

    public function handle(PhaseContext $context): PhaseContext
    {
        return $context;
    }
}
