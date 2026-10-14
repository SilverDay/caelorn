<?php

declare(strict_types=1);

namespace Caelorn\Tests\Golden;

use Caelorn\Domain\Engine\HostTurnEngine;
use Caelorn\Domain\Engine\PhaseContext;
use Caelorn\Domain\Hashing\StateHasher;
use Caelorn\Domain\Rng\Seed;
use Caelorn\Domain\Ruleset\RulesetBundle;
use Caelorn\Domain\State\GameState;
use Caelorn\Domain\State\OrderSet;
use Caelorn\Domain\State\TurnEvents;
use PHPUnit\Framework\TestCase;

/**
 * The M0 acceptance gate's golden test (spec §12): running the same frozen
 * input through the host engine twice must yield bit-for-bit identical
 * state hashes (spec §5.4).
 */
final class DeterministicTurnTest extends TestCase
{
    public function test_identical_input_produces_identical_state_hash_on_rerun(): void
    {
        $makeContext = static fn(): PhaseContext => new PhaseContext(
            new GameState(turnNumber: 1),
            new OrderSet(),
            new RulesetBundle('fixture-ruleset-hash', 'fixture-catalog-hash', '1.0.0'),
            new Seed('golden-turn-fixture-seed'),
            new TurnEvents(),
        );

        $engine = HostTurnEngine::withDefaultPhases();

        $firstRunHash = StateHasher::hash($engine->run($makeContext())->state->toArray());
        $secondRunHash = StateHasher::hash($engine->run($makeContext())->state->toArray());

        self::assertSame($firstRunHash, $secondRunHash);
    }
}
