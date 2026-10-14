<?php

declare(strict_types=1);

namespace Caelorn\Tests\Unit\Rng;

use Caelorn\Domain\Rng\Seed;
use PHPUnit\Framework\TestCase;

final class SeededStreamTest extends TestCase
{
    public function test_identical_tuple_reproduces_identical_bytes(): void
    {
        $seed = new Seed('fixture-seed');

        $streamA = $seed->streamFor(5, 'movement', 'ship-1');
        $streamB = $seed->streamFor(5, 'movement', 'ship-1');

        self::assertSame($streamA->bytes(32), $streamB->bytes(32));
    }

    public function test_different_phase_produces_independent_stream(): void
    {
        $seed = new Seed('fixture-seed');

        $movement = $seed->streamFor(5, 'movement', 'ship-1');
        $combat = $seed->streamFor(5, 'space_combat', 'ship-1');

        self::assertNotSame($movement->bytes(32), $combat->bytes(32));
    }

    public function test_different_event_key_produces_independent_stream(): void
    {
        $seed = new Seed('fixture-seed');

        $shipOne = $seed->streamFor(5, 'movement', 'ship-1');
        $shipTwo = $seed->streamFor(5, 'movement', 'ship-2');

        self::assertNotSame($shipOne->bytes(32), $shipTwo->bytes(32));
    }

    public function test_uniform_int_stays_within_bounds_and_is_reproducible(): void
    {
        $seed = new Seed('fixture-seed');

        $values = [];
        $stream = $seed->streamFor(1, 'combat', 'tie-break');
        for ($i = 0; $i < 50; ++$i) {
            $values[] = $stream->uniformInt(7);
        }

        foreach ($values as $value) {
            self::assertGreaterThanOrEqual(0, $value);
            self::assertLessThan(7, $value);
        }

        $replayStream = $seed->streamFor(1, 'combat', 'tie-break');
        $replayValues = [];
        for ($i = 0; $i < 50; ++$i) {
            $replayValues[] = $replayStream->uniformInt(7);
        }

        self::assertSame($values, $replayValues);
    }

    public function test_empty_seed_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Seed('');
    }
}
