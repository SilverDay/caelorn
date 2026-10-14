<?php

declare(strict_types=1);

namespace Caelorn\Domain\Engine;

use Caelorn\Domain\Engine\Phase\CommitPhase;
use Caelorn\Domain\Engine\Phase\DiplomacyPhase;
use Caelorn\Domain\Engine\Phase\EncounterGroupingPhase;
use Caelorn\Domain\Engine\Phase\GroundCombatPhase;
use Caelorn\Domain\Engine\Phase\LockPhase;
use Caelorn\Domain\Engine\Phase\MineOperationsPhase;
use Caelorn\Domain\Engine\Phase\MovementPhase;
use Caelorn\Domain\Engine\Phase\PathHazardsPhase;
use Caelorn\Domain\Engine\Phase\PlanetEconomyPhase;
use Caelorn\Domain\Engine\Phase\PostCombatPhase;
use Caelorn\Domain\Engine\Phase\PreMovementPhase;
use Caelorn\Domain\Engine\Phase\ScoreVictoryPhase;
use Caelorn\Domain\Engine\Phase\SpaceCombatPhase;
use Caelorn\Domain\Engine\Phase\StarbaseActionsPhase;
use Caelorn\Domain\Engine\Phase\VisibilityPhase;

/**
 * Runs the 15 host phases in the fixed order defined by spec §5.5 over a
 * frozen input snapshot. A later phase never retroactively affects an
 * earlier one — each phase only sees what the previous phases produced.
 */
final class HostTurnEngine
{
    /** @param list<HostPhase> $phases */
    public function __construct(
        private readonly array $phases,
    ) {}

    public static function withDefaultPhases(): self
    {
        return new self([
            new LockPhase(),
            new DiplomacyPhase(),
            new PreMovementPhase(),
            new MovementPhase(),
            new PathHazardsPhase(),
            new EncounterGroupingPhase(),
            new SpaceCombatPhase(),
            new GroundCombatPhase(),
            new PostCombatPhase(),
            new PlanetEconomyPhase(),
            new StarbaseActionsPhase(),
            new MineOperationsPhase(),
            new VisibilityPhase(),
            new ScoreVictoryPhase(),
            new CommitPhase(),
        ]);
    }

    public function run(PhaseContext $context): PhaseContext
    {
        foreach ($this->phases as $phase) {
            $context = $phase->handle($context);
        }

        return $context;
    }

    /** @return list<HostPhase> */
    public function phases(): array
    {
        return $this->phases;
    }
}
