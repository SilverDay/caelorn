<?php

declare(strict_types=1);

namespace Caelorn\Tests\Unit\Math;

use Caelorn\Domain\Math\BasisPoints;
use PHPUnit\Framework\TestCase;

final class BasisPointsTest extends TestCase
{
    public function test_from_percent_converts_to_basis_points(): void
    {
        self::assertSame(1000, BasisPoints::fromPercent(10)->value());
    }

    public function test_apply_floor(): void
    {
        $tenPercent = BasisPoints::fromPercent(10);

        self::assertSame(5, $tenPercent->applyFloor(59));
    }

    public function test_apply_ceil(): void
    {
        $tenPercent = BasisPoints::fromPercent(10);

        self::assertSame(6, $tenPercent->applyCeil(51));
    }

    public function test_rejects_negative_basis_points(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new BasisPoints(-1);
    }
}
