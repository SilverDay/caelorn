<?php

declare(strict_types=1);

namespace Caelorn\Domain\Ruleset;

use Caelorn\Domain\Hashing\StateHasher;

/**
 * A schema-validated, content-hashed JSON document — used for both the
 * ruleset and the ship/component catalog, which a game pins immutably at
 * creation alongside its content hash (spec §5.3, B.5).
 */
final class JsonDocument
{
    /** @param array<string, mixed> $data */
    private function __construct(
        public readonly array $data,
        public readonly string $contentHash,
    ) {}

    public static function loadValidated(string $dataPath, string $schemaPath, SchemaValidator $validator): self
    {
        $raw = file_get_contents($dataPath);

        if ($raw === false) {
            throw new \RuntimeException("Unable to read JSON document: {$dataPath}");
        }

        $validator->assertValid($raw, $schemaPath);

        /** @var array<string, mixed> $data */
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return new self($data, StateHasher::hash($data));
    }
}
