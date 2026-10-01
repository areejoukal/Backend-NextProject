<?php

namespace App\Services\AI\Exceptions;

class AIRateLimitException extends AIException
{
    public static function retryAfter(int $seconds): self
    {
        return new self("AI rate limit exceeded. Retry after {$seconds} seconds.");
    }
}