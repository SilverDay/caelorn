<?php

declare(strict_types=1);

namespace Caelorn\Domain\Ruleset;

use Caelorn\Domain\Engine\PhaseSchema;

/**
 * The immutable rules bundle a game pins at creation: ruleset + catalog
 * content hashes plus the phase-schema version (spec §5.3). Balance changes
 * to the underlying files never alter a game already pinned to a bundle.
 */
final class RulesetBundle
{
    public function __construct(
        public readonly string $rulesetHash,
        public readonly string $catalogHash,
        public readonly string $phaseSchemaVersion,
    ) {}

    public static function pin(JsonDocument $ruleset, JsonDocument $catalog): self
    {
        return new self($ruleset->contentHash, $catalog->contentHash, PhaseSchema::VERSION);
    }
}
