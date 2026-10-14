<?php

declare(strict_types=1);

namespace Caelorn\Domain\State;

/**
 * Every slot's submitted orders for the turn being resolved. Order payload
 * shapes are schema-validated per order type starting M1 (spec §7.3); M0
 * only needs the envelope to exist so phases have a typed input.
 */
final class OrderSet
{
    /** @param array<string, mixed> $ordersBySlot */
    public function __construct(
        public readonly array $ordersBySlot = [],
    ) {}
}
