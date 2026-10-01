<?php

namespace App\Services\AI\Exceptions;

class AITimeoutException extends AIException
{
    public static function afterSeconds(int $seconds): self
    {
        return new self("AI request timed out after {$seconds} seconds.");
    }
}