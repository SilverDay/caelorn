<?php

declare(strict_types=1);

namespace Caelorn\Domain\Hashing;

/**
 * Hashes authoritative turn state for determinism checks (spec §5.4). Callers
 * must exclude runtime timestamps and notification IDs before hashing.
 */
final class StateHasher
{
    private function __construct() {}

    public static function hash(mixed $state): string
    {
        return hash('sha256', CanonicalJson::encode($state));
    }
}
