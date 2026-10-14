<?php

declare(strict_types=1);

namespace Caelorn\Domain\Engine\Phase;

use Caelorn\Domain\Engine\HostPhase;
use Caelorn\Domain\Engine\PhaseContext;

/**
 * Spec §5.5 phase 15: commit: atomically persist new authoritative state, reports, order outcomes and notification outbox. Stub pending M2.
 */
final class CommitPhase implements HostPhase
{
    public function name(): string
    {
        return 'commit';
    }

    public function handle(PhaseContext $context): PhaseContext
    {
        return $context;
    }
}
