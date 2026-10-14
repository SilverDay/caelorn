<?php

declare(strict_types=1);

namespace Caelorn\Domain\Math;

/**
 * Deterministic integer rounding helpers for the authoritative host (spec §5.4, §15.2).
 *
 * Every formula in the engine must use one of these named modes instead of PHP's
 * unparameterised round(): Trunc (toward zero), Round (half away from zero) and
 * ERnd (ties to even), plus explicit floor/ceil division. All operations are
 * integer-only; there is no float path through the authoritative host.
 */
final class Rounding
{
    private function __construct() {}

    public static function floorDiv(int $numerator, int $denominator): int
    {
        self::assertPositiveDenominator($denominator);

        $quotient = intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;

        if ($remainder !== 0 && $numerator < 0) {
            --$quotient;
        }

        return $quotient;
    }

    public static function ceilDiv(int $numerator, int $denominator): int
    {
        self::assertPositiveDenominator($denominator);

        return -self::floorDiv(-$numerator, $denominator);
    }

    /** Trunc: rounds toward zero. */
    public static function trunc(int $numerator, int $denominator): int
    {
        self::assertPositiveDenominator($denominator);

        return intdiv($numerator, $denominator);
    }

    /** Round: half away from zero. */
    public static function roundHalfUp(int $numerator, int $denominator): int
    {
        self::assertPositiveDenominator($denominator);

        $sign = $numerator < 0 ? -1 : 1;
        $magnitude = abs($numerator);

        return $sign * self::floorDiv(2 * $magnitude + $denominator, 2 * $denominator);
    }

    /** ERnd: ties to even, symmetric around zero. */
    public static function roundTiesToEven(int $numerator, int $denominator): int
    {
        self::assertPositiveDenominator($denominator);

        $sign = $numerator < 0 ? -1 : 1;
        $magnitude = abs($numerator);

        $floor = intdiv($magnitude, $denominator);
        $remainder = $magnitude - $floor * $denominator;
        $twiceRemainder = 2 * $remainder;

        if ($twiceRemainder < $denominator) {
            $result = $floor;
        } elseif ($twiceRemainder > $denominator) {
            $result = $floor + 1;
        } else {
            $result = $floor % 2 === 0 ? $floor : $floor + 1;
        }

        return $sign * $result;
    }

    /** Integer square root (floor of the real square root) of a nonnegative integer. */
    public static function isqrt(int $n): int
    {
        if ($n < 0) {
            throw new \InvalidArgumentException('isqrt() requires a nonnegative integer.');
        }

        if ($n === 0) {
            return 0;
        }

        $x = (int) floor(sqrt((float) $n));

        while ($x > 0 && $x * $x > $n) {
            --$x;
        }

        while (($x + 1) * ($x + 1) <= $n) {
            ++$x;
        }

        return $x;
    }

    private static function assertPositiveDenominator(int $denominator): void
    {
        if ($denominator <= 0) {
            throw new \InvalidArgumentException('Denominator must be positive.');
        }
    }
}
