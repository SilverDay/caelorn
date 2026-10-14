<?php

declare(strict_types=1);

namespace Caelorn\Tests\Unit\Math;

use Caelorn\Domain\Math\Rounding;
use PHPUnit\Framework\TestCase;

final class RoundingTest extends TestCase
{
    public function test_floor_div_rounds_toward_negative_infinity(): void
    {
        self::assertSame(3, Rounding::floorDiv(7, 2));
        self::assertSame(-4, Rounding::floorDiv(-7, 2));
        self::assertSame(-3, Rounding::floorDiv(-6, 2));
    }

    public function test_ceil_div_rounds_toward_positive_infinity(): void
    {
        self::assertSame(4, Rounding::ceilDiv(7, 2));
        self::assertSame(-3, Rounding::ceilDiv(-7, 2));
        self::assertSame(1, Rounding::ceilDiv(1, 49));
    }

    public function test_trunc_rounds_toward_zero(): void
    {
        self::assertSame(3, Rounding::trunc(7, 2));
        self::assertSame(-3, Rounding::trunc(-7, 2));
    }

    public function test_round_half_up_rounds_half_away_from_zero(): void
    {
        self::assertSame(3, Rounding::roundHalfUp(5, 2));
        self::assertSame(-3, Rounding::roundHalfUp(-5, 2));
        self::assertSame(2, Rounding::roundHalfUp(9, 4));
    }

    public function test_round_ties_to_even(): void
    {
        self::assertSame(2, Rounding::roundTiesToEven(5, 2));
        self::assertSame(4, Rounding::roundTiesToEven(7, 2));
        self::assertSame(-2, Rounding::roundTiesToEven(-5, 2));
        self::assertSame(3, Rounding::roundTiesToEven(10, 3));
    }

    public function test_isqrt_boundaries(): void
    {
        self::assertSame(0, Rounding::isqrt(0));
        self::assertSame(2, Rounding::isqrt(4));
        self::assertSame(2, Rounding::isqrt(8));
        self::assertSame(3, Rounding::isqrt(9));
        self::assertSame(20, Rounding::isqrt(400));
    }

    public function test_division_rejects_nonpositive_denominator(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Rounding::trunc(1, 0);
    }
}
