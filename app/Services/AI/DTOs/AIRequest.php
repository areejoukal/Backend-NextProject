<?php

namespace App\Services\AI\DTOs;

class AIRequest
{
    /**
     * @param AIMessage[] $messages
     */
    public function __construct(
        public readonly array $messages,
        public readonly ?string $model = null,
        public readonly float $temperature = 0.3,
        public readonly ?int $maxTokens = null,
        public readonly bool $jsonMode = false,
        public readonly ?array $jsonSchema = null,
        public readonly ?string $purpose = null, // للـ logging
    ) {}

    public static function make(array $messages): self
    {
        return new self(messages: $messages);
    }

    public function withModel(string $model): self
    {
        return new self(
            messages: $this->messages,
            model: $model,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            jsonMode: $this->jsonMode,
            jsonSchema: $this->jsonSchema,
            purpose: $this->purpose,
        );
    }

    public function withTemperature(float $temperature): self
    {
        return new self(
            messages: $this->messages,
            model: $this->model,
            temperature: $temperature,
            maxTokens: $this->maxTokens,
            jsonMode: $this->jsonMode,
            jsonSchema: $this->jsonSchema,
            purpose: $this->purpose,
        );
    }

    public function asJson(?array $schema = null): self
    {
        return new self(
            messages: $this->messages,
            model: $this->model,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            jsonMode: true,
            jsonSchema: $schema,
            purpose: $this->purpose,
        );
    }

    public function forPurpose(string $purpose): self
    {
        return new self(
            messages: $this->messages,
            model: $this->model,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            jsonMode: $this->jsonMode,
            jsonSchema: $this->jsonSchema,
            purpose: $purpose,
        );
    }
}