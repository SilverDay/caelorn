<?php

declare(strict_types=1);

namespace Caelorn\Domain\State;

/**
 * Immutable accumulator for the turn's event/report stream, appended to by
 * host phases as they run (spec §5.5 step 15, §5.7 player-safe reports).
 */
final class TurnEvents
{
    /** @param list<array<string, mixed>> $events */
    public function __construct(
        private readonly array $events = [],
    ) {}

    /** @param array<string, mixed> $event */
    public function with(array $event): self
    {
        return new self([...$this->events, $event]);
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->events;
    }
}
