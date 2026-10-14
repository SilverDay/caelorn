<?php

declare(strict_types=1);

namespace Caelorn\Domain\Engine;

/**
 * Version of the 15-phase host execution order (spec §5.5). Bump whenever
 * the phase sequence, or a phase's inputs/outputs, changes incompatibly —
 * it is one of the fields a game's pinned rules bundle records (spec §5.3).
 */
final class PhaseSchema
{
    public const VERSION = '1.0.0';

    private function __construct() {}
}
