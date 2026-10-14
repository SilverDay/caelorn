<?php

declare(strict_types=1);

namespace Caelorn\Tests\Gate;

use Caelorn\Domain\Engine\HostPhase;
use Caelorn\Domain\Engine\HostTurnEngine;
use Caelorn\Domain\Ruleset\JsonDocument;
use Caelorn\Domain\Ruleset\SchemaValidator;
use PHPUnit\Framework\TestCase;

/**
 * The M0 acceptance gate (spec §12): every host phase has a defined
 * contract, in the spec's exact order, and both the MPR-1 ruleset and
 * SCC-1 catalog are versioned documents that validate against their
 * schemas. The golden deterministic-turn check lives in
 * tests/Golden/DeterministicTurnTest.php.
 */
final class M0GateTest extends TestCase
{
    public function test_all_fifteen_host_phases_are_defined_in_spec_order(): void
    {
        $expectedOrder = [
            'lock', 'diplomacy', 'pre_movement', 'movement', 'path_hazards',
            'encounter_grouping', 'space_combat', 'ground_combat', 'post_combat',
            'planet_economy', 'starbase_actions', 'mine_operations', 'visibility',
            'score_victory', 'commit',
        ];

        $phases = HostTurnEngine::withDefaultPhases()->phases();

        self::assertCount(15, $phases);

        foreach ($phases as $phase) {
            self::assertInstanceOf(HostPhase::class, $phase);
        }

        self::assertSame(
            $expectedOrder,
            array_map(static fn(HostPhase $phase): string => $phase->name(), $phases),
        );
    }

    public function test_mpr1_ruleset_validates_against_its_schema(): void
    {
        $root = dirname(__DIR__, 2);

        $document = JsonDocument::loadValidated(
            $root . '/rulesets/mpr1/ruleset.json',
            $root . '/schema/ruleset.schema.json',
            new SchemaValidator(),
        );

        self::assertSame('mpr1', $document->data['id']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $document->contentHash);
    }

    public function test_scc1_catalog_validates_against_its_schema(): void
    {
        $root = dirname(__DIR__, 2);

        $document = JsonDocument::loadValidated(
            $root . '/catalogs/scc1/catalog.json',
            $root . '/schema/catalog.schema.json',
            new SchemaValidator(),
        );

        self::assertSame('scc1', $document->data['id']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $document->contentHash);
    }
}
