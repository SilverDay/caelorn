<?php

declare(strict_types=1);

namespace Caelorn\Domain\Rng;

/**
 * A game's secret per-game seed (spec §5.4). Never exposed to players, since
 * that would let them predict host outcomes.
 */
final class Seed
{
    public function __construct(
        private readonly string $value,
    ) {
        if ($value === '') {
            throw new \InvalidArgumentException('Seed must not be empty.');
        }
    }

    public function streamFor(int $turnNumber, string $phase, string $eventKey): SeededStream
    {
        return new SeededStream($this->value, $turnNumber, $phase, $eventKey);
    }
}
