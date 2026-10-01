<?php

namespace App\Services\AI\Exceptions;

use Exception;

class AIException extends Exception
{
    protected ?array $context = null;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        ?array $context = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): ?array
    {
        return $this->context;
    }
}