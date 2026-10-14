<?php

declare(strict_types=1);

namespace Caelorn\Domain\Ruleset;

final class SchemaValidationException extends \RuntimeException
{
    /** @param array<array-key, mixed> $errors */
    public function __construct(string $schemaPath, array $errors)
    {
        parent::__construct(sprintf(
            'Document failed validation against schema "%s": %s',
            $schemaPath,
            json_encode($errors, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ));
    }
}
