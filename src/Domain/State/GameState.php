<?php

declare(strict_types=1);

namespace Caelorn\Domain\State;

/**
 * Authoritative per-turn game state. Minimal placeholder for M0: enough
 * structure to flow through all 15 host phases and be hashed. Planet/ship
 * state shapes are filled in starting M1 (spec §5.6).
 */
final class GameState
{
    /**
     * @param array<string, mixed> $planets
     * @param array<string, mixed> $ships
     */
    public function __construct(
        public readonly int $turnNumber,
        public readonly array $planets = [],
        public readonly array $ships = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'turn_number' => $this->turnNumber,
            'planets' => $this->planets,
            'ships' => $this->ships,
        ];
    }
}
