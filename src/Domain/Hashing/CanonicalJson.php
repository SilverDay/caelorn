<?php

declare(strict_types=1);

namespace Caelorn\Domain\Hashing;

/**
 * Deterministic canonical JSON encoding for authoritative state hashing (spec §5.4):
 * sorted object keys, stable list order as given, and no floats — the authoritative
 * host is integer/fixed-point only, so a float reaching this encoder is a bug.
 */
final class CanonicalJson
{
    private function __construct() {}

    public static function encode(mixed $value): string
    {
        return json_encode(
            self::normalize($value),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    private static function normalize(mixed $value): mixed
    {
        if (is_float($value)) {
            throw new \InvalidArgumentException(
                'Floats are not permitted in canonical/authoritative state; use integer or fixed-point units.',
            );
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map(self::normalize(...), $value);
            }

            ksort($value, SORT_STRING);

            return array_map(self::normalize(...), $value);
        }

        return $value;
    }
}
