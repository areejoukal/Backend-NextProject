<?php

namespace App\Services\AI\Exceptions;

class AIInvalidResponseException extends AIException
{
    public static function missingChoices(): self
    {
        return new self('AI response has no choices.');
    }

    public static function invalidJson(string $raw): self
    {
        return new self(
            'AI returned invalid JSON.',
            context: ['raw' => substr($raw, 0, 500)]
        );
    }

    public static function schemaValidationFailed(array $errors): self
    {
        return new self(
            'AI response failed schema validation.',
            context: ['errors' => $errors]
        );
    }
}