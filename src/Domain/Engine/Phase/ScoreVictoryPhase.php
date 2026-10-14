<?php

declare(strict_types=1);

namespace Caelorn\Domain\Engine\Phase;

use Caelorn\Domain\Engine\HostPhase;
use Caelorn\Domain\Engine\PhaseContext;

/**
 * Spec §5.5 phase 14: score/victory: update score, elimination and win state with documented tie-breaks. Stub pending M1.
 */
final class ScoreVictoryPhase implements HostPhase
{
    public function name(): string
    {
        return 'score_victory';
    }

    public function handle(PhaseContext $context): PhaseContext
    {
        return $context;
    }
}
