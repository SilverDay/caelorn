<?php

declare(strict_types=1);

namespace Caelorn\Domain\Engine;

use Caelorn\Domain\Rng\Seed;
use Caelorn\Domain\Ruleset\RulesetBundle;
use Caelorn\Domain\State\GameState;
use Caelorn\Domain\State\OrderSet;
use Caelorn\Domain\State\TurnEvents;

/**
 * The frozen input snapshot and accumulating output passed through the
 * 15 host phases (spec §5.5). Phases replace $state/$events via the
 * `with*` methods rather than mutating in place.
 */
final class PhaseContext
{
    public function __construct(
        public readonly GameState $state,
        public readonly OrderSet $orders,
        public readonly RulesetBundle $rulesetBundle,
        public readonly Seed $seed,
        public readonly TurnEvents $events,
    ) {}

    public function withState(GameState $state): self
    {
        return new self($state, $this->orders, $this->rulesetBundle, $this->seed, $this->events);
    }

    public function withEvents(TurnEvents $events): self
    {
        return new self($this->state, $this->orders, $this->rulesetBundle, $this->seed, $events);
    }
}
