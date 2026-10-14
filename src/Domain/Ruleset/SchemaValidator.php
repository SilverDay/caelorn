<?php

declare(strict_types=1);

namespace Caelorn\Domain\Ruleset;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/**
 * Validates raw JSON against a JSON Schema file (spec §5.3, §7.3). Rulesets
 * and catalogs fail closed: invalid data must never be used by the engine.
 */
final class SchemaValidator
{
    private readonly Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator();
    }

    public function assertValid(string $rawJson, string $schemaPath): void
    {
        $schema = json_decode(
            (string) file_get_contents($schemaPath),
            false,
            512,
            JSON_THROW_ON_ERROR,
        );
        $document = json_decode($rawJson, false, 512, JSON_THROW_ON_ERROR);

        $result = $this->validator->validate($document, $schema);

        if (!$result->isValid()) {
            $formatter = new ErrorFormatter();
            $errors = $formatter->formatOutput($result->error(), 'flat');

            throw new SchemaValidationException($schemaPath, $errors);
        }
    }
}
