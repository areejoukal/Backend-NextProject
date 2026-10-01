<?php

namespace App\Services\AI\Prompts;

use App\Services\AI\DTOs\AIResponse;

class PromptResult
{
    public function __construct(
        public readonly mixed $data,
        public readonly AIResponse $raw,
    ) {}

    public function isJson(): bool
    {
        return is_array($this->data);
    }

    public function tokens(): int
    {
        return $this->raw->totalTokens;
    }

    public function latencyMs(): int
    {
        return $this->raw->latencyMs;
    }

    public function model(): string
    {
        return $this->raw->model;
    }
}