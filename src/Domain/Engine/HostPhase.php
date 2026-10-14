<?php

declare(strict_types=1);

namespace Caelorn\Domain\Engine;

/**
 * One stage of the host's 15-phase turn resolution (spec §5.5). A phase
 * reads the frozen snapshot carried by $context and returns the context
 * with its own effects applied — it must never reach back into an earlier
 * phase's intermediate state.
 */
interface HostPhase
{
    /** Stable machine-readable key, e.g. "planet_economy". */
    public function name(): string;

    public function handle(PhaseContext $context): PhaseContext;
}
