<?php

declare(strict_types=1);

namespace Caelorn\Tests\Fixtures;

use Caelorn\Domain\Math\Rounding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the Math primitives against the locally-derived numeric fixture
 * table in spec §15.11 ("locally derived from the stated formulas, not
 * official PHost fixtures"). These exercise arithmetic helpers the
 * REF/ADAPT formulas will be built from (§15); the formulas themselves are
 * reference-only until wired into a `phost-inspired` profile (§15.9) and
 * are therefore evaluated here directly rather than through host phases,
 * which stay stubs until M1+.
 *
 * Two fixtures (accurate fuel, population growth's sin-based rate) use
 * PHP's native float math on purpose: both are explicitly REF-tagged
 * reference/analytical formulas, never part of the integer-only
 * authoritative host path.
 */
final class ReferenceFormulaFixturesTest extends TestCase
{
    public function test_movement_distance_is_warp_squared(): void
    {
        self::assertSame(49, 7 ** 2);
    }

    public function test_non_accurate_fuel_model(): void
    {
        $mass = 100;
        $distance = 49;
        $engineFuelUsage = 1000;
        $maxDist = 49;

        $erndMassOverTen = Rounding::roundTiesToEven($mass, 10);
        $fuel = Rounding::trunc($erndMassOverTen * $distance * $engineFuelUsage, 10_000 * $maxDist);

        self::assertSame(1, $fuel);
    }

    public function test_accurate_fuel_model_reference_value(): void
    {
        $mass = 100;
        $distance = 49;
        $engineFuelUsage = 1000;
        $maxDist = 49;

        $fuel = $mass * (1 - exp(-$engineFuelUsage * $distance / ($maxDist * 100_000)));

        self::assertEqualsWithDelta(0.9950, $fuel, 0.0001);
    }

    public function test_happiness_delta(): void
    {
        $clans = 10_000;
        $temperature = 50;
        $targetTemp = 50;
        $tempDivisor = 33;
        $mines = 0;
        $factories = 0;
        $taxPercent = 5;

        $crowding = Rounding::isqrt(intdiv($clans, 10_000));
        $climatePenalty = intdiv(abs($temperature - $targetTemp), $tempDivisor);
        $buildingPenalty = intdiv($mines + $factories, 300);
        $taxPenalty = Rounding::trunc($taxPercent * 8, 10);

        $delta = Rounding::trunc(10 - $crowding - $climatePenalty - $buildingPenalty - $taxPenalty, 1);

        self::assertSame(5, $delta);
    }

    public function test_colonist_tax_revenue(): void
    {
        $clans = 10_000;
        $taxPercent = 5;
        $colonistTaxRatePercent = 100;

        $stepOne = Rounding::roundHalfUp($clans * $taxPercent, 1_000);
        $income = Rounding::roundHalfUp($stepOne * $colonistTaxRatePercent, 100);

        self::assertSame(50, $income);
    }

    public function test_population_growth(): void
    {
        $temperature = 50;
        $taxPercent = 0;
        $clans = 10_000;
        $raceGrowthRatePercent = 100;

        // growth_rate_percent is sin-based and therefore float/reference-only (§15.3 REF);
        // only the final Trunc(Round(...) * rate / 100) step uses the integer helpers.
        $growthRatePercent = 5 * sin($temperature * M_PI / 100) / (1 + $taxPercent / 5);
        $roundedShare = (int) round($growthRatePercent * $clans / 100);
        $growthClans = Rounding::trunc($roundedShare * $raceGrowthRatePercent, 100);

        self::assertSame(500, $growthClans);
    }

    public function test_temperature_cap_at_fifty(): void
    {
        $temperature = 50;

        $maxColonistsPeople = (int) floor(10_000_000 * sin($temperature * M_PI / 100));
        $maxColonistClans = Rounding::trunc($maxColonistsPeople, 100);

        self::assertSame(100_000, $maxColonistClans);
    }

    public function test_ground_combat_defender_survivors(): void
    {
        $attackingClans = 100;
        $defendingClans = 80;
        $defencePosts = 20;
        $attackFactor = 1;
        $defenceFactor = 1;

        $attackPower = $attackingClans * $attackFactor;
        $weightDefence = $defenceFactor + intdiv($defencePosts, 20);
        $result = $attackPower - $defendingClans * $weightDefence;

        self::assertLessThan(0, $result);
        self::assertSame(30, Rounding::trunc(-$result, $weightDefence));
    }

    public function test_minefield_radius_and_decay(): void
    {
        $mineUnits = 400;
        $decayRatePercent = 5;

        $radius = Rounding::isqrt($mineUnits);
        $afterDecay = Rounding::trunc($mineUnits * (100 - $decayRatePercent), 100);

        self::assertSame(20, $radius);
        self::assertSame(380, $afterDecay);
    }

    public function test_reference_score(): void
    {
        $planets = 10;
        $armedShips = 5;
        $freighters = 2;
        $starbases = 1;

        $score = 10 * $planets + 10 * $armedShips + $freighters + 120 * $starbases;

        self::assertSame(272, $score);
    }

    /**
     * Below its threshold, a structure's cap is simply C (no formula to
     * exercise). At the threshold, spec §15.3 switches to
     * Round(threshold + sqrt(C - threshold)); this checks that branch's
     * boundary value for each structure type's own threshold.
     *
     * @return iterable<string, array{int}> structure type => [threshold]
     */
    public static function structureCapThresholds(): iterable
    {
        yield 'defence posts (threshold 50)' => [50];
        yield 'factories (threshold 100)' => [100];
        yield 'mines (threshold 200)' => [200];
    }

    #[DataProvider('structureCapThresholds')]
    public function test_structure_cap_at_its_own_threshold(int $threshold): void
    {
        self::assertSame(
            $threshold,
            Rounding::roundHalfUp($threshold + Rounding::isqrt($threshold - $threshold), 1),
        );
    }
}
