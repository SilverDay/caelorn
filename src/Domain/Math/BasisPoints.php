<?php

declare(strict_types=1);

namespace Caelorn\Domain\Math;

/**
 * A percentage represented as integer basis points, 10,000 = 100% (spec §A.2).
 */
final class BasisPoints
{
    public const SCALE = 10_000;

    public function __construct(
        private readonly int $bps,
    ) {
        if ($bps < 0) {
            throw new \InvalidArgumentException('Basis points must be nonnegative.');
        }
    }

    public static function fromPercent(int $percent): self
    {
        return new self($percent * 100);
    }

    public function value(): int
    {
        return $this->bps;
    }

    public function applyFloor(int $amount): int
    {
        return Rounding::floorDiv($amount * $this->bps, self::SCALE);
    }

    public function applyCeil(int $amount): int
    {
        return Rounding::ceilDiv($amount * $this->bps, self::SCALE);
    }

    public function applyRoundHalfUp(int $amount): int
    {
        return Rounding::roundHalfUp($amount * $this->bps, self::SCALE);
    }
}
