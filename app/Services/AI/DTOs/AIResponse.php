<?php

namespace App\Services\AI\DTOs;

class AIResponse
{
    public function __construct(
        public readonly string $content,
        public readonly string $model,
        public readonly int $promptTokens,
        public readonly int $completionTokens,
        public readonly int $totalTokens,
        public readonly int $latencyMs,
        public readonly ?array $json = null,
        public readonly array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'json' => $this->json,
            'model' => $this->model,
            'tokens' => [
                'prompt' => $this->promptTokens,
                'completion' => $this->completionTokens,
                'total' => $this->totalTokens,
            ],
            'latency_ms' => $this->latencyMs,
        ];
    }

    public function hasJson(): bool
    {
        return $this->json !== null;
    }
}